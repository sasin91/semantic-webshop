<?php
require_once APPPATH . 'modules/search/traits/Search_settings.php';

/**
 * The client for the embeddings service (llama.cpp's llama-server running
 * EmbeddingGemma 2), plus indexing products into the vector store.
 * Not reachable by URL; the shop, search and admin modules load it.
 */
class Embeddings extends Trongate {

    use Search_settings;

    // Prompts from the EmbeddingGemma 2 model card for retrieval ("search result").
    // The model is trained with them: leaving them out costs a lot of quality.
    // Documents get 'title: ... | text: ...', see Embeddings_model::document_text().
    const QUERY_PROMPT = 'task: search result | query: ';

    function __construct(?string $module_name = null) {
        parent::__construct($module_name);
        block_url('embeddings');
    }

    /**
     * Embed texts. Queries are answered fast or not at all (a visitor is
     * waiting); documents may take longer and are retried.
     *
     * @param list<string> $texts for 'document', each one formatted by document_text()
     * @param string $mode 'query' or 'document'
     * @return list<list<float>> one unit-length vector per text, in order
     * @throws RuntimeException when the service cannot be reached or answers badly
     */
    function embed(array $texts, string $mode): array {
        if ($texts === []) {
            return [];
        }

        if ($mode === 'query') {
            $inputs = array_map(fn($text) => self::QUERY_PROMPT . $text, $texts);
            return $this->_request($inputs, (float) $this->_setting('query_timeout_seconds'), 0);
        }

        if ($mode !== 'document') {
            throw new InvalidArgumentException("Unknown embedding mode: {$mode}");
        }

        $vectors = [];
        foreach (array_chunk($texts, max(1, (int) $this->_setting('document_batch_size'))) as $batch) {
            array_push($vectors, ...$this->_request(
                $batch,
                (float) $this->_setting('document_timeout_seconds'),
                (int) $this->_setting('document_retries')
            ));
        }

        return $vectors;
    }

    /**
     * The text embedded for a product (see Embeddings_model::document_text())
     */
    function document_text(array $product): string {
        return $this->model->document_text($product);
    }

    /**
     * The vector of a search query, from the cache when it was asked before
     *
     * @return array{vector: list<float>, cached: bool}
     * @throws RuntimeException when it is not cached and the service fails or is slow
     */
    function query_vector(string $query): array {
        $query = preg_replace('/\s+/u', ' ', trim($query));
        $cached = $this->model->cached_query($query);

        if ($cached !== null) {
            return ['vector' => $cached, 'cached' => true];
        }

        $vector = $this->embed([$query], 'query')[0];
        $this->model->cache_query($query, $vector);

        return ['vector' => $vector, 'cached' => false];
    }

    /**
     * Products closest in meaning to a vector (see Embeddings_model::nearest())
     */
    function nearest(array $vector, array $filters, int $limit, ?int $exclude_id = null): array {
        return $this->model->nearest($vector, $filters, $limit, $exclude_id);
    }

    /**
     * A product's stored vector, or null when it has none from the current model
     */
    function product_vector(int $product_id): ?array {
        return $this->model->product_vector($product_id);
    }

    /**
     * Embedding state of the catalogue, for the admin (see Embeddings_model::stats())
     */
    function stats(): array {
        return $this->model->stats();
    }

    /**
     * Embed one batch of products and store their vectors.
     *
     * @param bool $all true re-embeds every product; false only those without a current vector
     * @param int $after_id continue after this product id (the caller passes back last_id)
     * @param list<int>|null $product_ids only these products
     * @return array{embedded: int, last_id: int, done: bool, pending: int}
     * @throws RuntimeException when the service fails (nothing of this batch is stored)
     */
    function index(bool $all = false, int $after_id = 0, ?array $product_ids = null): array {
        $batch_size = max(1, (int) $this->_setting('document_batch_size'));
        $products = $this->model->products_to_index($all, $after_id, $batch_size, $product_ids);

        if ($products !== []) {
            $texts = array_map(fn($product) => $this->document_text($product), $products);
            $vectors = $this->embed($texts, 'document');

            foreach ($products as $i => $product) {
                $this->model->save((int) $product['id'], $vectors[$i], hash('sha256', $texts[$i]));
            }
        }

        return [
            'embedded' => count($products),
            'last_id' => $products === [] ? $after_id : (int) end($products)['id'],
            'done' => count($products) < $batch_size,
            'pending' => count($this->model->products_to_index(false, 0, PHP_INT_MAX)),
        ];
    }

    /**
     * Is the service up, and how fast does it embed one short query?
     *
     * @return array{ok: bool, latency_ms: int, error: ?string, url: string}
     */
    function health(): array {
        $started = hrtime(true);
        $error = null;

        try {
            $this->_request([self::QUERY_PROMPT . 'health check'], 5.0, 0);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        return [
            'ok' => $error === null,
            'latency_ms' => (int) ((hrtime(true) - $started) / 1e6),
            'error' => $error,
            'url' => $this->_url(),
        ];
    }

    /**
     * Create the vector table (see Embeddings_model::install())
     */
    function install(): string {
        return $this->model->install();
    }

    /**
     * Which vector store is in use: 'native' (MariaDB VECTOR) or 'blob' (cosine in PHP)
     */
    function backend(): string {
        return $this->model->backend();
    }

    /**
     * POST the inputs to the OpenAI-compatible /v1/embeddings endpoint.
     *
     * @return list<list<float>>
     */
    function _request(array $inputs, float $timeout, int $retries): array {
        $payload = json_encode(['input' => array_values($inputs), 'model' => 'embeddinggemma-2'], JSON_UNESCAPED_UNICODE);
        $error = '';

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            if ($attempt > 0) {
                usleep(250_000 * 2 ** ($attempt - 1)); // 0.25 s, 0.5 s, 1 s, ...
            }

            $curl = curl_init($this->_url() . '/v1/embeddings');
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT_MS => (int) min(1000, $timeout * 1000),
                CURLOPT_TIMEOUT_MS => (int) ($timeout * 1000),
            ]);
            $body = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $curl_error = curl_error($curl);

            if ($body === false) {
                $error = "embeddings service unreachable: {$curl_error}";
                continue;
            }

            if ($status !== 200) {
                $error = "embeddings service answered HTTP {$status}";
                if ($status >= 400 && $status < 500) {
                    break; // our request is wrong; trying again will not help
                }
                continue;
            }

            $data = json_decode($body, true)['data'] ?? null;
            if (!is_array($data) || count($data) !== count($inputs)) {
                $error = 'embeddings service sent an unexpected response';
                continue;
            }

            usort($data, fn($a, $b) => $a['index'] <=> $b['index']);

            return array_map(fn($item) => $this->_normalize($item['embedding']), $data);
        }

        throw new RuntimeException($error);
    }

    /**
     * Shorten to the configured size (EmbeddingGemma is a Matryoshka model, so a
     * prefix of the vector is a valid smaller embedding) and scale to unit length,
     * so cosine similarity is a dot product.
     */
    function _normalize(array $vector): array {
        $vector = array_slice($vector, 0, (int) $this->_setting('embeddings_dimensions'));
        $length = sqrt(array_sum(array_map(fn($x) => $x * $x, $vector))) ?: 1.0;

        return array_map(fn($x) => $x / $length, $vector);
    }

    function _url(): string {
        return rtrim((string) $this->_setting('embeddings_url'), '/');
    }
}
