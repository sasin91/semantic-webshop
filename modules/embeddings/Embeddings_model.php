<?php
require_once APPPATH . 'modules/search/traits/Product_queries.php';
require_once APPPATH . 'modules/search/traits/Search_settings.php';
require_once APPPATH . 'modules/search/traits/Sql_files.php';

/**
 * The vector store: product vectors and cached query vectors.
 *
 * Two backends behind the same methods, picked when the table is created:
 * - native: MariaDB 11.7+ VECTOR column, VEC_DISTANCE_COSINE and a vector index
 * - blob:   packed float32 BLOBs, cosine similarity computed in PHP
 *
 * Every vector is stored with the model, model version and dimensions that
 * made it; vectors from another model are ignored and re-embedded.
 */
class Embeddings_model extends Model {

    use Product_queries;
    use Search_settings;
    use Sql_files;

    const NATIVE_MIN_VERSION = '11.7';

    private ?string $backend = null;

    /**
     * Create the vector table for the backend this database supports.
     * Safe to run again. Recreates the table when the configured dimensions
     * no longer fit a native VECTOR column (the vectors are derived data).
     */
    function install(): string {
        $backend = $this->_wanted_backend();
        $dimensions = (int) $this->_setting('embeddings_dimensions');
        $column = $this->_embedding_column_type();

        $this->backend = null;
        if ($column !== null && ($backend !== $this->_backend_of($column) || ($backend === 'native' && $column !== "vector({$dimensions})"))) {
            $this->db->query('DROP TABLE product_embeddings');
        }

        $this->_run_sql_file(APPPATH . "db/vectors_{$backend}.sql", ['{dimensions}' => (string) $dimensions]);

        return $backend;
    }

    /**
     * 'native' or 'blob': what the existing table uses, else what would be created
     */
    function backend(): string {
        if ($this->backend === null) {
            $column = $this->_embedding_column_type();
            $this->backend = $column === null ? $this->_wanted_backend() : $this->_backend_of($column);
        }

        return $this->backend;
    }

    /**
     * The text that is embedded for a product: title + category + brand,
     * description and key attributes, in the model card's document format
     * ("title: ... | text: ..."). Change it and every product is re-embedded,
     * because its hash no longer matches the stored one.
     *
     * @param array $product a row with name, brand, category, description, attributes (JSON)
     */
    function document_text(array $product): string {
        $attributes = json_decode((string) $product['attributes'], true) ?: [];
        $facts = array_map(fn($key, $value) => "{$key}: {$value}", array_keys($attributes), $attributes);

        $text = "{$product['category']}. {$product['brand']}. " . trim($product['description']);
        if ($facts !== []) {
            $text .= ' ' . implode('; ', $facts) . '.';
        }

        return "title: {$product['name']} | text: {$text}";
    }

    /**
     * Products whose vector is missing, from another model, or made from older text
     * (or every product when $all), in id order after $after_id.
     *
     * @param list<int>|null $product_ids only these products
     * @return list<array> product rows (with category) ready for document_text()
     */
    function products_to_index(bool $all, int $after_id, int $limit, ?array $product_ids = null): array {
        $params = ['after_id' => $after_id] + $this->_model_params();
        $sql = $this->_product_select('e.text_hash')
            . ' LEFT JOIN product_embeddings e ON e.product_id = p.id AND ' . $this->_current_model_sql()
            . ' WHERE p.id > :after_id';

        if ($product_ids !== null) {
            $ids = array_map('intval', $product_ids) ?: [0];
            $sql .= ' AND p.id IN (' . implode(',', $ids) . ')';
        }

        $rows = $this->db->query_bind($sql . ' ORDER BY p.id', $params, 'array');
        $pending = [];

        foreach ($rows as $row) {
            if ($all || $row['text_hash'] !== hash('sha256', $this->document_text($row))) {
                $pending[] = $row;
                if (count($pending) >= $limit) {
                    break;
                }
            }
        }

        return $pending;
    }

    function save(int $product_id, array $vector, string $text_hash): void {
        $value = $this->backend() === 'native' ? 'VEC_FromText(:embedding)' : 'UNHEX(:embedding)';
        $sql = <<<SQL
            REPLACE INTO product_embeddings (product_id, model, model_version, dimensions, text_hash, embedding, embedded_at)
            VALUES (:product_id, :m_model, :m_version, :m_dimensions, :text_hash, {$value}, NOW())
            SQL;

        $this->db->query_bind($sql, [
            'product_id' => $product_id,
            'text_hash' => $text_hash,
            'embedding' => $this->_encode($vector),
        ] + $this->_model_params());
    }

    /**
     * The products closest in meaning to a vector, most similar first,
     * restricted by the shop's filters.
     *
     * @return list<array> product rows with similarity (cosine, -1..1)
     */
    function nearest(array $vector, array $filters, int $limit, ?int $exclude_id = null): array {
        $params = $this->_model_params();
        $where = $this->_filter_sql($filters, $params);

        if ($exclude_id !== null) {
            $where .= ' AND p.id <> :exclude_id';
            $params['exclude_id'] = $exclude_id;
        }

        $join = ' JOIN product_embeddings e ON e.product_id = p.id AND ' . $this->_current_model_sql();

        if ($this->backend() === 'native') {
            $params['vector'] = $this->_encode($vector);
            $params['limit'] = $limit;
            $sql = $this->_product_select('1 - VEC_DISTANCE_COSINE(e.embedding, VEC_FromText(:vector)) AS similarity')
                . "{$join} WHERE {$where} ORDER BY VEC_DISTANCE_COSINE(e.embedding, VEC_FromText(:vector2)) LIMIT :limit";
            $params['vector2'] = $params['vector'];
            $rows = $this->db->query_bind($sql, $params, 'array');
        } else {
            $rows = $this->db->query_bind($this->_product_select('e.embedding') . "{$join} WHERE {$where}", $params, 'array');
            foreach ($rows as &$row) {
                $row['similarity'] = $this->_dot($vector, unpack('g*', $row['embedding']));
                unset($row['embedding']);
            }
            unset($row);
            usort($rows, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
            $rows = array_slice($rows, 0, $limit);
        }

        foreach ($rows as &$row) {
            $row['similarity'] = (float) $row['similarity'];
        }

        return $rows;
    }

    /**
     * A product's own stored vector, or null when it has none from the current model
     *
     * @return list<float>|null
     */
    function product_vector(int $product_id): ?array {
        $column = $this->backend() === 'native' ? 'VEC_ToText(e.embedding)' : 'e.embedding';
        $sql = "SELECT {$column} AS embedding FROM product_embeddings e WHERE e.product_id = :product_id AND " . $this->_current_model_sql();
        $rows = $this->db->query_bind($sql, ['product_id' => $product_id] + $this->_model_params(), 'array');

        if ($rows === []) {
            return null;
        }

        return $this->backend() === 'native'
            ? array_map('floatval', json_decode($rows[0]['embedding'], true))
            : array_values(unpack('g*', $rows[0]['embedding']));
    }

    /**
     * @return list<float>|null
     */
    function cached_query(string $query): ?array {
        $key = $this->_cache_key($query);
        $rows = $this->db->query_bind('SELECT embedding FROM query_embeddings WHERE cache_key = :key', ['key' => $key], 'array');

        if ($rows === []) {
            return null;
        }

        $this->db->query_bind('UPDATE query_embeddings SET hits = hits + 1, last_used_at = NOW() WHERE cache_key = :key', ['key' => $key]);

        return array_values(unpack('g*', $rows[0]['embedding']));
    }

    function cache_query(string $query, array $vector): void {
        $sql = <<<SQL
            INSERT IGNORE INTO query_embeddings (cache_key, query, model, dimensions, embedding)
            VALUES (:key, :query, :model, :dimensions, UNHEX(:embedding))
            SQL;

        $this->db->query_bind($sql, [
            'key' => $this->_cache_key($query),
            'query' => mb_substr($query, 0, 500),
            'model' => $this->_setting('embeddings_model') . ' ' . $this->_setting('embeddings_model_version'),
            'dimensions' => (int) $this->_setting('embeddings_dimensions'),
            'embedding' => bin2hex(pack('g*', ...$vector)),
        ]);
    }

    /**
     * Embedding state per product, for the admin
     *
     * @return array{total: int, current: int, stale: int, missing: int, cached_queries: int}
     */
    function stats(): array {
        $total = (int) $this->db->query('SELECT COUNT(*) AS n FROM products', 'array')[0]['n'];
        $stored = (int) $this->db->query('SELECT COUNT(*) AS n FROM product_embeddings', 'array')[0]['n'];
        $pending = count($this->products_to_index(false, 0, PHP_INT_MAX));

        return [
            'total' => $total,
            'current' => $total - $pending,
            'stale' => $pending - ($total - $stored),
            'missing' => $total - $stored,
            'cached_queries' => (int) $this->db->query('SELECT COUNT(*) AS n FROM query_embeddings', 'array')[0]['n'],
        ];
    }

    /**
     * When each product was embedded and whether its vector is current
     *
     * @param list<array> $products rows with id, name, brand, category, description, attributes
     * @return array<int, array{status: string, embedded_at: ?string}> by product id
     */
    function status_of(array $products): array {
        if ($products === []) {
            return [];
        }

        $ids = implode(',', array_map(fn($product) => (int) $product['id'], $products));
        $sql = "SELECT e.product_id, e.text_hash, e.embedded_at, " . $this->_current_model_sql() . " AS current_model
                FROM product_embeddings e WHERE e.product_id IN ({$ids})";
        $stored = array_column($this->db->query_bind($sql, $this->_model_params(), 'array'), null, 'product_id');
        $status = [];

        foreach ($products as $product) {
            $row = $stored[$product['id']] ?? null;
            $status[$product['id']] = [
                'status' => match (true) {
                    $row === null => 'missing',
                    !$row['current_model'] => 'other model',
                    $row['text_hash'] !== hash('sha256', $this->document_text($product)) => 'text changed',
                    default => 'current',
                },
                'embedded_at' => $row['embedded_at'] ?? null,
            ];
        }

        return $status;
    }

    function _wanted_backend(): string {
        $setting = $this->_setting('vector_backend');
        if ($setting === 'native' || $setting === 'blob') {
            return $setting;
        }

        $version = $this->db->query('SELECT VERSION() AS version', 'array')[0]['version'];
        $is_mariadb = stripos($version, 'mariadb') !== false;

        return $is_mariadb && version_compare(strtok($version, '-'), self::NATIVE_MIN_VERSION, '>=') ? 'native' : 'blob';
    }

    function _embedding_column_type(): ?string {
        $sql = <<<SQL
            SELECT LOWER(COLUMN_TYPE) AS type FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_embeddings' AND COLUMN_NAME = 'embedding'
            SQL;
        $rows = $this->db->query($sql, 'array');

        return $rows[0]['type'] ?? null;
    }

    function _backend_of(string $column_type): string {
        return str_starts_with($column_type, 'vector') ? 'native' : 'blob';
    }

    function _current_model_sql(): string {
        return '(e.model = :m_model AND e.model_version = :m_version AND e.dimensions = :m_dimensions)';
    }

    function _model_params(): array {
        return [
            'm_model' => $this->_setting('embeddings_model'),
            'm_version' => $this->_setting('embeddings_model_version'),
            'm_dimensions' => (int) $this->_setting('embeddings_dimensions'),
        ];
    }

    function _cache_key(string $query): string {
        $model = $this->_setting('embeddings_model') . '|' . $this->_setting('embeddings_model_version') . '|' . $this->_setting('embeddings_dimensions');

        return hash('sha256', $model . "\n" . $query);
    }

    /**
     * A vector as the SQL above expects it: JSON text for VEC_FromText, hex for UNHEX
     */
    function _encode(array $vector): string {
        return $this->backend() === 'native' ? json_encode(array_values($vector)) : bin2hex(pack('g*', ...$vector));
    }

    /**
     * Cosine similarity of two unit vectors ($b as unpack() returns it, 1-indexed)
     */
    function _dot(array $a, array $b): float {
        $sum = 0.0;
        foreach ($a as $i => $value) {
            $sum += $value * $b[$i + 1];
        }

        return $sum;
    }

}
