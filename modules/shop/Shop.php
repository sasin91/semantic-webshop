<?php
/**
 * The shop front: product grid with search and filters, and product pages.
 */
class Shop extends Trongate {

    const PER_PAGE = 24;

    function index(): void {
        if (!$this->model->has_catalogue()) {
            $this->_render('not_installed', ['page_title' => 'Not installed yet']);
            return;
        }

        $query = $this->_query();
        $filters = $this->_filters();
        $data = [
            'query' => $query,
            'filters' => $filters,
            'categories' => $this->model->categories(),
            'mode' => '',
            'page_title' => $query === '' ? 'All products' : "Search: {$query}",
        ];

        if ($query === '') {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $browse = $this->model->browse($filters, $page, self::PER_PAGE);
            $data += [
                'products' => $browse['rows'],
                'total' => $browse['total'],
                'page' => $page,
                'num_pages' => max(1, (int) ceil($browse['total'] / self::PER_PAGE)),
            ];
        } else {
            $this->module('search');
            $search = $this->search->_run($query, $filters, $this->_mode());
            $data += [
                'products' => $search['results'],
                'total' => count($search['results']),
                'search' => $search,
            ];
            $data['mode'] = $search['mode'];
        }

        $this->_render('index', $data);
    }

    function product(): void {
        $product = $this->model->has_catalogue() ? $this->model->find(segment(3, 'int')) : null;

        if ($product === null) {
            http_response_code(404);
            $this->_render('product_not_found', ['page_title' => 'Product not found']);
            return;
        }

        $this->_render('product', [
            'product' => $product,
            'page_title' => $product['name'],
            'query' => '',
        ]);
    }

    function _render(string $view_file, array $data): void {
        $data['view_module'] = 'shop';
        $data['view_file'] = $view_file;
        $data['additional_includes_top'] = ['shop_module/css/shop.css'];
        $this->module('templates');
        $this->templates->public($data);
    }

    function _query(): string {
        $query = trim((string) ($_GET['q'] ?? ''));

        return mb_substr($query, 0, 200);
    }

    function _mode(): string {
        $this->module('search');
        $mode = (string) ($_GET['mode'] ?? '');

        return in_array($mode, Search::MODES, true) ? $mode : Search::MODES[0];
    }

    /**
     * The filters in the query string; anything invalid is ignored
     */
    function _filters(): array {
        $price = fn(string $key) => isset($_GET[$key]) && is_numeric($_GET[$key]) && $_GET[$key] >= 0 ? (float) $_GET[$key] : null;
        $category = (string) ($_GET['category'] ?? '');

        return [
            'category' => preg_match('/^[a-z0-9-]{1,80}$/', $category) ? $category : '',
            'min_price' => $price('min_price'),
            'max_price' => $price('max_price'),
            'in_stock' => ($_GET['in_stock'] ?? '') === '1',
        ];
    }
}
