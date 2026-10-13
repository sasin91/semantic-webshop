<?php
require_once __DIR__ . '/traits/Product_queries.php';
require_once __DIR__ . '/traits/Search_settings.php';

/**
 * Retrieval: keyword search with MariaDB FULLTEXT.
 */
class Search_model extends Model {

    use Product_queries;
    use Search_settings;

    /**
     * Products whose text matches the query's words, best first. An exact SKU
     * always comes first, so a pasted product code finds its product.
     *
     * @return list<array> product rows with keyword_score
     */
    function keyword(string $query, array $filters, int $limit): array {
        $params = ['q' => $query, 'q_where' => $query, 'sku' => $query, 'limit' => $limit];
        $where = $this->_filter_sql($filters, $params);

        $match = 'MATCH (p.name, p.brand, p.description, p.attributes)';
        $sql = $this->_product_select("{$match} AGAINST (:q IN NATURAL LANGUAGE MODE) + (p.sku = :sku) * 100 AS keyword_score") . <<<SQL
             WHERE ({$match} AGAINST (:q_where IN NATURAL LANGUAGE MODE) OR p.sku = :sku_where)
               AND {$where}
             ORDER BY keyword_score DESC, p.id
             LIMIT :limit
            SQL;
        $params['sku_where'] = $query;

        $rows = $this->db->query_bind($sql, $params, 'array');
        foreach ($rows as &$row) {
            $row['keyword_score'] = (float) $row['keyword_score'];
            $row['exact_sku'] = strcasecmp($row['sku'], $query) === 0;
        }

        return $rows;
    }
}
