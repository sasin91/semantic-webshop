<?php
require_once APPPATH . 'modules/search/traits/Search_settings.php';

/**
 * The shop's back office, behind one password (ADMIN_PASSWORD or
 * config/search.php): install and seed the catalogue.
 */
class Admin extends Trongate {

    use Search_settings;

    function index(): void {
        $this->_make_sure_allowed();

        $this->_render('dashboard', [
            'page_title' => 'Admin',
            'status' => $this->model->status(),
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
        set_flashdata("Installed. Added {$result['categories']} categories and {$result['products']} products.");
        redirect('admin');
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
        $this->module('templates');
        $this->templates->admin($data);
    }
}
