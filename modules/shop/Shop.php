<?php
/**
 * The shop front: product grid with search and filters, and product pages.
 */
class Shop extends Trongate {

    const PER_PAGE = 24;

    // Queries that show where each kind of search shines, with why
    const EXAMPLES = [
        'warm jacket for hiking in the rain' => 'No word in common with the best match',
        'gift for a coffee lover' => 'Intent, not keywords',
        'gave til en kaffeelsker' => 'Danish query, English catalogue',
        'something to help me fall asleep' => 'A need, not a product name',
        'back hurts from sitting all day' => 'A problem the products solve',
        'keep my drink hot on the go' => 'Synonyms: flask, travel mug',
        'CP-1001' => 'Exact SKU: keyword search wins',
        'Kestrel K-200' => 'Brand and model number',
    ];

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
            'examples' => self::EXAMPLES,
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

        $this->module('search');

        $this->_render('product', [
            'product' => $product,
            'similar' => $this->search->_similar($product['id']),
            'page_title' => $product['name'],
            'query' => '',
        ]);
    }

    /**
     * The same query as keyword, semantic and hybrid search, side by side
     */
    function compare(): void {
        if (!$this->model->has_catalogue()) {
            $this->_render('not_installed', ['page_title' => 'Not installed yet']);
            return;
        }

        $query = $this->_query();
        if ($query === '') {
            $query = array_key_first(self::EXAMPLES);
        }

        $this->module('search');
        $columns = [];
        foreach (['keyword' => 'Keyword only', 'semantic' => 'Semantic', 'hybrid' => 'Hybrid'] as $mode => $title) {
            $columns[$mode] = ['title' => $title] + $this->search->_run($query, [], $mode, 8);
        }

        $this->_render('compare', [
            'query' => $query,
            'columns' => $columns,
            'examples' => self::EXAMPLES,
            'page_title' => "Compare: {$query}",
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
