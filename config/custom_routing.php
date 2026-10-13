<?php
$routes = [
    'product/(:num)' => 'shop/product/$1',
    'compare' => 'shop/compare'
];
define('CUSTOM_ROUTES', $routes);
