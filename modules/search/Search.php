<?php
require_once __DIR__ . '/traits/Search_settings.php';

/**
 * Search over the catalogue, used by the shop front. Not reachable by URL.
 *
 * keyword:  MariaDB FULLTEXT (exact words, SKUs, brands, model numbers)
 * semantic: nearest product vectors to the query's EmbeddingGemma 2 vector
 * hybrid:   both lists merged with Reciprocal Rank Fusion
 */
class Search extends Trongate {

    use Search_settings;

    const MODES = ['hybrid', 'semantic', 'keyword'];

    function __construct(?string $module_name = null) {
        parent::__construct($module_name);
        block_url('search');
    }

    /**
     * Run a search and say how each result matched (matched_by, score_label).
     * When the embeddings service is down or slow, semantic and hybrid search
     * fall back to keyword results with a notice instead of failing.
     *
     * @return array{results: list<array>, mode: string, notice: ?string, took_ms: int, cached: bool}
     */
    function _run(string $query, array $filters, string $mode, int $limit = 24): array {
        $started = hrtime(true);
        $candidates = max($limit, (int) $this->_setting('candidates'));
        $semantic = null;
        $notice = null;
        $cached = false;

        if ($mode !== 'keyword') {
            try {
                ['rows' => $semantic, 'cached' => $cached] = $this->_semantic($query, $filters, $mode === 'hybrid' ? $candidates : $limit);
            } catch (RuntimeException $e) {
                $notice = 'Semantic search is unavailable right now (the embeddings service did not answer in time), so these are keyword results only.';
                $mode = 'keyword';
            }
        }

        $results = match ($mode) {
            'semantic' => $semantic,
            'keyword' => $this->_keyword($query, $filters, $limit),
            'hybrid' => $this->_fuse($this->_keyword($query, $filters, $candidates), $semantic, $limit),
        };

        return [
            'results' => $results,
            'mode' => $mode,
            'notice' => $notice,
            'took_ms' => (int) ((hrtime(true) - $started) / 1e6),
            'cached' => $cached,
        ];
    }

    /**
     * The products most similar in meaning to a product, for "More like this"
     *
     * @return list<array> rows with similarity; empty when the product has no vector yet
     */
    function _similar(int $product_id, int $limit = 6): array {
        $this->module('embeddings');
        $vector = $this->embeddings->product_vector($product_id);

        return $vector === null ? [] : $this->embeddings->nearest($vector, [], $limit, $product_id);
    }

    function _keyword(string $query, array $filters, int $limit): array {
        $rows = $this->model->keyword($query, $filters, $limit);

        foreach ($rows as &$row) {
            $row['matched_by'] = 'keyword';
            $row['score'] = $row['keyword_score'];
            $row['score_label'] = $row['exact_sku'] ? 'exact SKU' : 'relevance ' . number_format($row['keyword_score'], 2);
        }

        return $rows;
    }

    /**
     * @return array{rows: list<array>, cached: bool}
     * @throws RuntimeException when the query cannot be embedded
     */
    function _semantic(string $query, array $filters, int $limit): array {
        $this->module('embeddings');
        ['vector' => $vector, 'cached' => $cached] = $this->embeddings->query_vector($query);
        $min_similarity = (float) $this->_setting('min_similarity');

        $rows = array_filter(
            $this->embeddings->nearest($vector, $filters, $limit),
            fn($row) => $row['similarity'] >= $min_similarity
        );

        foreach ($rows as &$row) {
            $row['matched_by'] = 'semantic';
            $row['score'] = $row['similarity'];
            $row['score_label'] = 'similarity ' . number_format($row['similarity'], 2);
        }

        return ['rows' => array_values($rows), 'cached' => $cached];
    }

    /**
     * Reciprocal Rank Fusion: each list adds weight / (k + rank) for every
     * product it found, so a product near the top of both lists wins, and the
     * two very different score scales never have to be compared.
     * A pasted SKU that matches a product exactly always comes first.
     */
    function _fuse(array $keyword, array $semantic, int $limit): array {
        $k = (int) $this->_setting('rrf_k');
        $weights = ['keyword' => (float) $this->_setting('keyword_weight'), 'semantic' => (float) $this->_setting('semantic_weight')];
        $fused = [];

        foreach (['keyword' => $keyword, 'semantic' => $semantic] as $list => $rows) {
            foreach ($rows as $rank => $row) {
                $fused[$row['id']] ??= $row + ['rrf' => 0.0, 'ranks' => [], 'exact_sku' => false];
                $fused[$row['id']]['rrf'] += $weights[$list] / ($k + $rank + 1);
                $fused[$row['id']]['ranks'][$list] = $rank + 1;
            }
        }

        foreach ($fused as &$row) {
            $row['matched_by'] = count($row['ranks']) === 2 ? 'both' : array_key_first($row['ranks']);
            $row['score'] = $row['rrf'];
            $row['score_label'] = $row['exact_sku'] ? 'exact SKU' : 'RRF ' . number_format($row['rrf'] * 1000, 1);
            $row['score_detail'] = implode(' · ', array_map(
                fn($list, $rank) => "{$list} #{$rank}",
                array_keys($row['ranks']),
                $row['ranks']
            ));
        }
        unset($row);

        usort($fused, fn($a, $b) => [$b['exact_sku'], $b['rrf']] <=> [$a['exact_sku'], $a['rrf']]);

        return array_slice($fused, 0, $limit);
    }
}
