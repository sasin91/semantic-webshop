<?php
require_once APPPATH . 'modules/search/traits/Search_settings.php';

/**
 * The shop's back office, behind one password (ADMIN_PASSWORD or
 * config/search.php): install and seed the catalogue, embed the products.
 */
class Admin extends Trongate {

    use Search_settings;

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
