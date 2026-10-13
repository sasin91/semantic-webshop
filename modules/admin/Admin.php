<?php
require_once APPPATH . 'modules/search/traits/Search_settings.php';

/**
 * The shop's back office, behind one password (ADMIN_PASSWORD or
 * config/search.php): install and seed the catalogue, edit products,
 * embed them, and watch the embeddings service.
 */
class Admin extends Trongate {

    use Search_settings;

    const PER_PAGE = 50;

    function index(): void {
        $this->_make_sure_allowed();

        $status = $this->model->status();
        $this->module('embeddings');

        $this->_render('dashboard', [
            'page_title' => 'Admin',
            'status' => $status,
            'embeddings' => $status['installed'] ? $this->embeddings->stats() : null,
            'backend' => $this->embeddings->backend(),
            'health' => $this->embeddings->health(),
            'settings' => [
                'model' => $this->_setting('embeddings_model'),
                'version' => $this->_setting('embeddings_model_version'),
                'dimensions' => $this->_setting('embeddings_dimensions'),
            ],
        ]);
    }

    /**
     * All products with their embedding status, searchable by name, brand or SKU
     */
    function products(): void {
        $this->_make_sure_allowed();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filter = trim((string) ($_GET['q'] ?? ''));
        $list = $this->model->product_page($filter, $page, self::PER_PAGE);
        $this->module('embeddings');

        $this->_render('products', [
            'page_title' => 'Products',
            'products' => $list['rows'],
            'status' => $this->embeddings->status_of($list['rows']),
            'filter' => $filter,
            'page' => $page,
            'num_pages' => max(1, (int) ceil($list['total'] / self::PER_PAGE)),
        ]);
    }

    /**
     * Create (admin/edit) or update (admin/edit/{id}) a product. A saved
     * product is embedded straight away; when the embeddings service is
     * unavailable it stays pending and the dashboard can embed it later.
     */
    function edit(): void {
        $this->_make_sure_allowed();
        $id = (int) segment(3, 'int');
        $product = $id > 0 ? $this->model->find_product($id) : null;

        if ($id > 0 && $product === null) {
            redirect('admin/products');
        }

        if (REQUEST_TYPE === 'POST') {
            $this->validation->set_rules('sku', 'SKU', 'required|max_length[32]|callback_sku_check');
            $this->validation->set_rules('name', 'name', 'required|max_length[160]');
            $this->validation->set_rules('brand', 'brand', 'required|max_length[80]');
            $this->validation->set_rules('category_id', 'category', 'required|integer');
            $this->validation->set_rules('description', 'description', 'required');
            $this->validation->set_rules('price', 'price', 'required|numeric|greater_than[0]');
            $this->validation->set_rules('stock', 'stock', 'required|integer');

            if ($this->validation->run()) {
                $id = $this->model->save_product($id, $this->_posted_product());
                set_flashdata("Saved. {$this->_embed_now($id)}");
                redirect("admin/edit/{$id}");
            }

            $product = $this->_posted_product();
        }

        $this->_render('edit', [
            'page_title' => $id > 0 ? "Edit {$product['name']}" : 'New product',
            'id' => $id,
            'product' => $product ?? ['sku' => '', 'name' => '', 'brand' => '', 'category_id' => 0, 'description' => '', 'attributes' => '{}', 'price' => '', 'stock' => 0],
            'categories' => array_column($this->model->categories(), 'name', 'id'),
        ]);
    }

    function delete(): void {
        $this->_make_sure_allowed();
        $this->_require_post();
        $this->model->delete_product((int) segment(3, 'int'));
        set_flashdata('Product deleted.');
        redirect('admin/products');
    }

    /**
     * Validation callback: a SKU belongs to one product
     */
    function sku_check(string $sku): string|bool {
        $owner = $this->model->product_id_by_sku(trim($sku));

        return $owner === null || $owner === (int) segment(3, 'int') ? true : 'Another product already has this SKU.';
    }

    function login(): void {
        if ($this->_setting('admin_password') === '') {
            $this->_render('locked', ['page_title' => 'Admin is locked']);
            return;
        }

        if (REQUEST_TYPE === 'POST') {
            $this->validation->set_rules('password', 'password', 'required');

            if ($this->validation->run() && hash_equals((string) $this->_setting('admin_password'), (string) post('password'))) {
                session_regenerate_id(true);
                $_SESSION['admin_signed_in'] = true;
                redirect('admin');
            }

            set_flashdata('That password is not right.');
            redirect('admin/login');
        }

        $this->_render('login', ['page_title' => 'Sign in']);
    }

    function logout(): void {
        unset($_SESSION['admin_signed_in']);
        redirect('');
    }

    /**
     * Create the tables (idempotent) and load the demo products that are missing
     */
    function install(): void {
        $this->_make_sure_allowed();
        $this->_require_post();

        $result = $this->model->install();
        set_flashdata("Installed with the {$result['vector_backend']} vector store. Added {$result['categories']} categories and {$result['products']} products. Now embed them below.");
        redirect('admin');
    }

    /**
     * POST, answers JSON: embed the next batch of products. The dashboard calls
     * it in a loop, passing back last_id, until done, to show progress.
     * all=1 re-embeds every product; otherwise only missing or outdated ones.
     */
    function embed(): void {
        $this->_make_sure_allowed();
        $this->_require_post();
        $this->module('embeddings');
        header('Content-Type: application/json');

        try {
            echo json_encode($this->embeddings->index(post('all') === '1', (int) post('after_id')));
        } catch (RuntimeException $e) {
            http_response_code(503);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Embed one product right after it is saved
     *
     * @return string a sentence for the flash message
     */
    function _embed_now(int $id): string {
        $this->module('embeddings');

        try {
            $this->embeddings->index(false, 0, [$id]);
            return 'Its vector is up to date.';
        } catch (RuntimeException $e) {
            return 'The embeddings service is unavailable, so it is waiting to be embedded (see the dashboard).';
        }
    }

    /**
     * The product form's fields; attributes come as "Key: value" lines
     */
    function _posted_product(): array {
        $attributes = [];
        foreach (preg_split('/\R/', (string) post('attributes')) as $line) {
            [$key, $value] = array_map('trim', explode(':', $line, 2)) + [1 => ''];
            if ($key !== '' && $value !== '') {
                $attributes[$key] = $value;
            }
        }

        return [
            'sku' => trim((string) post('sku')),
            'name' => trim((string) post('name')),
            'brand' => trim((string) post('brand')),
            'category_id' => (int) post('category_id'),
            'description' => trim((string) post('description')),
            'attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE),
            'price' => (string) post('price'),
            'stock' => (int) post('stock'),
        ];
    }

    function _make_sure_allowed(): void {
        if ($this->_setting('admin_password') === '') {
            $this->_render('locked', ['page_title' => 'Admin is locked']);
            die();
        }

        if (empty($_SESSION['admin_signed_in'])) {
            redirect('admin/login');
        }
    }

    /**
     * A POST with a valid CSRF token, else stop
     */
    function _require_post(): void {
        if (REQUEST_TYPE !== 'POST') {
            http_response_code(405);
            die('Method not allowed');
        }

        $this->validation->run();
    }

    function _render(string $view_file, array $data): void {
        $data['view_module'] = 'admin';
        $data['view_file'] = $view_file;
        $data['additional_includes_top'] = ['shop_module/css/shop.css'];
        $data['additional_includes_btm'] = ['admin_module/js/admin.js'];
        $this->module('templates');
        $this->templates->admin($data);
    }
}
