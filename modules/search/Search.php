<?php
/**
 * Search over the catalogue, used by the shop front. Not reachable by URL.
 */
class Search extends Trongate {

    const MODES = ['keyword'];

    function __construct(?string $module_name = null) {
        parent::__construct($module_name);
        block_url('search');
    }

    /**
     * Run a search and say how each result matched.
     *
     * @return array{results: list<array>, mode: string, notice: ?string, took_ms: int}
     */
    function _run(string $query, array $filters, string $mode, int $limit = 24): array {
        $started = hrtime(true);
        $rows = $this->model->keyword($query, $filters, $limit);

        foreach ($rows as &$row) {
            $row['matched_by'] = 'keyword';
            $row['score'] = $row['keyword_score'];
            $row['score_label'] = 'relevance ' . number_format($row['keyword_score'], 2);
        }

        return [
            'results' => $rows,
            'mode' => 'keyword',
            'notice' => null,
            'took_ms' => (int) ((hrtime(true) - $started) / 1e6),
        ];
    }
}
