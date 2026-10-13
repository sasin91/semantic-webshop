<?php
/**
 * Installing the catalogue and reporting its state.
 */
class Admin_model extends Model {

    /**
     * Create the tables and add the seed data that is not there yet. Safe to
     * run again: existing categories and products (matched by slug and SKU)
     * are left as they are, so edits made in the admin survive.
     *
     * @return array{categories: int, products: int} rows added
     */
    function install(): array {
        $this->_run_sql_file(APPPATH . 'db/schema.sql');

        $seed = json_decode(file_get_contents(APPPATH . 'db/seed/products.json'), true, flags: JSON_THROW_ON_ERROR);
        $before = $this->_counts();

        foreach ($seed['categories'] as $category) {
            $this->db->query_bind(
                'INSERT IGNORE INTO categories (name, slug, icon) VALUES (:name, :slug, :icon)',
                ['name' => $category['name'], 'slug' => $category['slug'], 'icon' => $category['icon']]
            );
        }

        $category_ids = array_column($this->db->query('SELECT id, slug FROM categories', 'array'), 'id', 'slug');
        $sql = <<<SQL
            INSERT IGNORE INTO products (sku, name, brand, category_id, description, attributes, price, stock)
            VALUES (:sku, :name, :brand, :category_id, :description, :attributes, :price, :stock)
            SQL;

        foreach ($seed['products'] as $product) {
            $this->db->query_bind($sql, [
                'sku' => $product['sku'],
                'name' => $product['name'],
                'brand' => $product['brand'],
                'category_id' => (int) $category_ids[$product['category']],
                'description' => $product['description'],
                'attributes' => json_encode($product['attributes'], JSON_UNESCAPED_UNICODE),
                'price' => (string) $product['price'],
                'stock' => (int) $product['stock'],
            ]);
        }

        $after = $this->_counts();

        return [
            'categories' => $after['categories'] - $before['categories'],
            'products' => $after['products'] - $before['products'],
        ];
    }

    /**
     * @return array{installed: bool, categories: int, products: int, database_version: string}
     */
    function status(): array {
        $installed = $this->db->table_exists('products');

        return [
            'installed' => $installed,
            'database_version' => $this->db->query('SELECT VERSION() AS version', 'array')[0]['version'],
        ] + ($installed ? $this->_counts() : ['categories' => 0, 'products' => 0]);
    }

    function _counts(): array {
        $row = $this->db->query('SELECT (SELECT COUNT(*) FROM categories) AS categories, (SELECT COUNT(*) FROM products) AS products', 'array')[0];

        return ['categories' => (int) $row['categories'], 'products' => (int) $row['products']];
    }

    /**
     * Run each statement of a .sql file (statements end with ; at the end of a line)
     */
    function _run_sql_file(string $path, array $replacements = []): void {
        $sql = strtr(file_get_contents($path), $replacements);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);

        foreach (preg_split('/;\s*$/m', $sql) as $statement) {
            if (trim($statement) !== '') {
                $this->db->query($statement);
            }
        }
    }
}
