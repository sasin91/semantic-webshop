<?php
require_once APPPATH . 'modules/search/traits/Product_queries.php';
require_once APPPATH . 'modules/search/traits/Sql_files.php';

/**
 * Installing the catalogue, reporting its state, and editing products.
 */
class Admin_model extends Model {

    use Product_queries;
    use Sql_files;

    /**
     * Create the tables and add the seed data that is not there yet. Safe to
     * run again: existing categories and products (matched by slug and SKU)
     * are left as they are, so edits made in the admin survive.
     *
     * @return array{categories: int, products: int, vector_backend: string} rows added, and the vector store used
     */
    function install(): array {
        $this->_run_sql_file(APPPATH . 'db/schema.sql');
        $this->module('embeddings');
        $vector_backend = $this->embeddings->install();

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
            'vector_backend' => $vector_backend,
        ];
    }

    /**
     * @return array{installed: bool, categories: int, products: int, database_version: string}
     */
    function status(): array {
        $installed = $this->db->table_exists('products') && $this->db->table_exists('product_embeddings');

        return [
            'installed' => $installed,
            'database_version' => $this->db->query('SELECT VERSION() AS version', 'array')[0]['version'],
        ] + ($installed ? $this->_counts() : ['categories' => 0, 'products' => 0]);
    }

    /**
     * @return array{rows: list<array>, total: int} products whose name, brand or SKU contains $filter
     */
    function product_page(string $filter, int $page, int $per_page): array {
        $where = 'p.name LIKE :f_name OR p.sku LIKE :f_sku OR p.brand LIKE :f_brand';
        $like = '%' . addcslashes($filter, '%_\\') . '%';
        $params = ['f_name' => $like, 'f_sku' => $like, 'f_brand' => $like];

        $total = (int) $this->db->query_bind("SELECT COUNT(*) AS n FROM products p WHERE {$where}", $params, 'array')[0]['n'];
        $sql = $this->_product_select() . " WHERE {$where} ORDER BY p.id LIMIT :limit OFFSET :offset";
        $rows = $this->db->query_bind($sql, $params + ['limit' => $per_page, 'offset' => ($page - 1) * $per_page], 'array');

        return ['rows' => $rows, 'total' => $total];
    }

    function find_product(int $id): ?array {
        $rows = $this->db->query_bind('SELECT * FROM products WHERE id = :id', ['id' => $id], 'array');

        return $rows[0] ?? null;
    }

    function product_id_by_sku(string $sku): ?int {
        $rows = $this->db->query_bind('SELECT id FROM products WHERE sku = :sku', ['sku' => $sku], 'array');

        return isset($rows[0]) ? (int) $rows[0]['id'] : null;
    }

    /**
     * Insert ($id 0) or update a product
     *
     * @return int the product's id
     */
    function save_product(int $id, array $product): int {
        if ($id === 0) {
            return $this->db->insert($product, 'products');
        }

        $this->db->update($id, $product, 'products');

        return $id;
    }

    function delete_product(int $id): void {
        $this->db->delete($id, 'products'); // its vector goes with it (ON DELETE CASCADE)
    }

    function categories(): array {
        return $this->db->query('SELECT id, name FROM categories ORDER BY name', 'array');
    }

    function _counts(): array {
        $row = $this->db->query('SELECT (SELECT COUNT(*) FROM categories) AS categories, (SELECT COUNT(*) FROM products) AS products', 'array')[0];

        return ['categories' => (int) $row['categories'], 'products' => (int) $row['products']];
    }
}
