<?php
require_once APPPATH . 'modules/search/traits/Product_queries.php';

/**
 * Catalogue reads for the shop front: categories, browsing and product pages.
 */
class Shop_model extends Model {

    use Product_queries;

    function categories(): array {
        $sql = <<<SQL
            SELECT c.id, c.name, c.slug, c.icon, COUNT(p.id) AS num_products
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id
            GROUP BY c.id, c.name, c.slug, c.icon
            ORDER BY c.name
            SQL;

        return $this->db->query($sql, 'array');
    }

    /**
     * One page of products matching the filters, newest first
     *
     * @return array{rows: array, total: int}
     */
    function browse(array $filters, int $page, int $per_page): array {
        $params = [];
        $where = $this->_filter_sql($filters, $params);

        $count_sql = "SELECT COUNT(*) AS total FROM products p JOIN categories c ON c.id = p.category_id WHERE {$where}";
        $total = (int) $this->db->query_bind($count_sql, $params, 'array')[0]['total'];

        $params['limit'] = $per_page;
        $params['offset'] = ($page - 1) * $per_page;
        $sql = $this->_product_select() . " WHERE {$where} ORDER BY c.name, p.name LIMIT :limit OFFSET :offset";

        return ['rows' => $this->db->query_bind($sql, $params, 'array'), 'total' => $total];
    }

    function find(int $id): ?array {
        $rows = $this->db->query_bind($this->_product_select() . ' WHERE p.id = :id', ['id' => $id], 'array');

        return $rows[0] ?? null;
    }

    function has_catalogue(): bool {
        return $this->db->table_exists('products');
    }
}
