<?php
/**
 * The product columns every listing shows, and the shop's filters as SQL.
 * Shared by the shop (browsing) and search (keyword and semantic retrieval),
 * so a filter always means the same thing in every search mode.
 * Queries alias products as p and categories as c.
 */
trait Product_queries {

    /**
     * @param string $extra_columns more select expressions, e.g. a relevance score
     */
    function _product_select(string $extra_columns = ''): string {
        $extra_columns = $extra_columns === '' ? '' : ", {$extra_columns}";

        return <<<SQL
            SELECT p.id, p.sku, p.name, p.brand, p.description, p.attributes, p.price, p.stock,
                   p.updated_at, c.name AS category, c.slug AS category_slug, c.icon AS category_icon{$extra_columns}
            FROM products p
            JOIN categories c ON c.id = p.category_id
            SQL;
    }

    /**
     * Structured filters as a WHERE condition; the bound values are added to $params.
     *
     * @param array{category?: string, min_price?: ?float, max_price?: ?float, in_stock?: bool} $filters
     */
    function _filter_sql(array $filters, array &$params): string {
        $where = ['1 = 1'];

        if (($filters['category'] ?? '') !== '') {
            $where[] = 'c.slug = :f_category';
            $params['f_category'] = $filters['category'];
        }

        if (isset($filters['min_price'])) {
            $where[] = 'p.price >= :f_min_price';
            $params['f_min_price'] = (string) $filters['min_price'];
        }

        if (isset($filters['max_price'])) {
            $where[] = 'p.price <= :f_max_price';
            $params['f_max_price'] = (string) $filters['max_price'];
        }

        if (!empty($filters['in_stock'])) {
            $where[] = 'p.stock > 0';
        }

        return implode(' AND ', $where);
    }
}
