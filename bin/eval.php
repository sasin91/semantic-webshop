<?php
/**
 * Search quality check: recall@10 of keyword, semantic and hybrid search
 * over ~20 queries with the products a good answer must contain.
 *
 *   php bin/eval.php            # from the app folder, with the catalogue installed and embedded
 *   php bin/eval.php -v         # also list each mode's top 10 per query
 *
 * Tune the hybrid merge by overriding settings from config/search.php with
 * environment variables, e.g.  KEYWORD_WEIGHT=0.7 MIN_SIMILARITY=0.6 php bin/eval.php
 */

// Expected products by SKU (stable across installs, unlike auto-increment ids)
const CASES = [
    'warm jacket for hiking in the rain' => ['OC-1001', 'OC-1002'],
    'gift for a coffee lover' => ['CT-1018', 'CT-1003', 'CT-1001', 'CT-1019'],
    'gave til en kaffeelsker' => ['CT-1018', 'CT-1003', 'CT-1001', 'CT-1019'],
    'something to help me fall asleep' => ['BB-1002', 'EL-1031', 'BB-1034', 'BB-1001', 'BB-1033'],
    'back hurts from sitting all day' => ['OF-1001', 'OF-1003', 'OF-1002', 'OF-1037'],
    'keep my drink hot on the go' => ['CT-1014', 'CP-1016'],
    'cook dinner on a camping trip' => ['CP-1012', 'CP-1013', 'CP-1015', 'CP-1014'],
    'clean drinking water in the wilderness' => ['CP-1017', 'CP-1018'],
    'noget til at dyrke krydderurter på altanen' => ['GT-1001', 'GT-1002', 'GT-1004'],
    'make fresh pasta at home' => ['KT-1017'],
    'lights for riding a bike at night' => ['SF-1027', 'OC-1025'],
    'work out at home without a gym' => ['SF-1006', 'SF-1008', 'SF-1009', 'SF-1007'],
    'better video calls when working from home' => ['EL-1013', 'OF-1020', 'EL-1014', 'OF-1021'],
    'sharpen my kitchen knives' => ['KT-1009'],
    'rain gear for children' => ['OC-1011', 'OC-1038', 'OC-1040'],
    'bake my own sourdough bread' => ['KT-1019'],
    'stay warm on cold winter nights at home' => ['BB-1011', 'HF-1027', 'BB-1012', 'BB-1004'],
    'CP-1001' => ['CP-1001'],
    'Kestrel K-200' => ['CP-1002'],
    'NC700' => ['EL-1001'],
    'Halden Roasters' => ['CT-1015', 'CT-1016', 'CT-1017', 'CT-1018', 'CT-1038'],
    'Wellington boots' => ['OC-1040'],
];
const MODES = ['keyword', 'semantic', 'hybrid'];
const K = 10;

// Boot Trongate as if serving the shop's home page, so the app's own search runs unchanged
$verbose = in_array('-v', $argv, true);
chdir(__DIR__ . '/../public');
require '../config/config.php';
$_SERVER += [
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => parse_url(BASE_URL, PHP_URL_HOST) . (parse_url(BASE_URL, PHP_URL_PORT) ? ':' . parse_url(BASE_URL, PHP_URL_PORT) : ''),
    'REQUEST_URI' => parse_url(BASE_URL, PHP_URL_PATH) ?: '/',
];
require '../engine/ignition.php';
while (ob_get_level() > 0) {
    ob_end_flush();
}

$search = new Search('search');
$totals = array_fill_keys(MODES, 0.0);

printf("%-44s %8s %8s %8s\n", 'query', ...MODES);
foreach (CASES as $query => $expected) {
    $recalls = [];
    foreach (MODES as $mode) {
        $run = $search->_run($query, [], $mode, K);
        if ($run['mode'] !== $mode) {
            fwrite(STDERR, "{$mode} fell back to {$run['mode']}: {$run['notice']}\n");
        }
        $found = array_column($run['results'], 'sku');
        $recalls[$mode] = count(array_intersect($expected, $found)) / count($expected);
        $totals[$mode] += $recalls[$mode];
        $top[$mode] = $found;
    }

    printf("%-44s %8.2f %8.2f %8.2f\n", mb_strimwidth($query, 0, 44, '…'), ...array_values($recalls));
    if ($verbose) {
        foreach (MODES as $mode) {
            printf("    %-8s %s\n", $mode, implode(' ', array_map(fn($sku) => in_array($sku, $expected, true) ? "[{$sku}]" : $sku, $top[$mode])));
        }
    }
}

printf("%s\n%-44s %8.3f %8.3f %8.3f\n", str_repeat('-', 71), 'mean recall@' . K, ...array_map(fn($total) => $total / count(CASES), array_values($totals)));
