<?php
require_once __DIR__ . '/traits/Search_settings.php';

/**
 * Search over the catalogue, used by the shop front. Not reachable by URL.
 *
 * keyword:  MariaDB FULLTEXT (exact words, SKUs, brands)
 * semantic: nearest product vectors to the query's EmbeddingGemma 2 vector
 */
class Search extends Trongate {

    use Search_settings;

    const MODES = ['semantic', 'keyword'];

    function __construct(?string $module_name = null) {
        parent::__construct($module_name);
        block_url('search');
    }

    /**
     * Run a search and say how each result matched. When the embeddings
     * service is down or slow, semantic search falls back to keyword results
     * with a notice instead of failing.
     *
     * @return array{results: list<array>, mode: string, notice: ?string, took_ms: int, cached: bool}
     */
    function _run(string $query, array $filters, string $mode, int $limit = 24): array {
        $started = hrtime(true);
        $notice = null;
        $cached = false;

        if ($mode === 'semantic') {
            try {
                ['rows' => $results, 'cached' => $cached] = $this->_semantic($query, $filters, $limit);
            } catch (RuntimeException $e) {
                $mode = 'keyword';
                $notice = 'Semantic search is unavailable right now (the embeddings service did not answer in time), so these are keyword results only.';
            }
        }

        if ($mode === 'keyword') {
            $results = $this->_keyword($query, $filters, $limit);
        }

        return [
            'results' => $results,
            'mode' => $mode,
            'notice' => $notice,
            'took_ms' => (int) ((hrtime(true) - $started) / 1e6),
            'cached' => $cached,
        ];
    }

    function _keyword(string $query, array $filters, int $limit): array {
        $rows = $this->model->keyword($query, $filters, $limit);

        foreach ($rows as &$row) {
            $row['matched_by'] = 'keyword';
            $row['score'] = $row['keyword_score'];
            $row['score_label'] = 'relevance ' . number_format($row['keyword_score'], 2);
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
}
