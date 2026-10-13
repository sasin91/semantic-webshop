<?php
/**
 * Write db/seed/products-{n}.sql from db/seed/products.json, for loading the
 * catalogue in a SQL console instead of with Install and seed in the admin.
 * Each file stays under the 64 KB a trongate.cloud SQL console accepts; run
 * them in order. Like the installer, they skip rows that already exist.
 *
 *   php bin/seed-sql.php
 */
const MAX_BYTES = 60000;

$root = dirname(__DIR__);
$seed = json_decode(file_get_contents("$root/db/seed/products.json"), true, flags: JSON_THROW_ON_ERROR);

$quote = fn(string $value): string => "'" . str_replace(['\\', "'"], ['\\\\', "''"], $value) . "'";
$header = fn(int $part): string => "-- Seed catalogue part {$part}, generated from db/seed/products.json by bin/seed-sql.php.\n"
    . "-- Run after db/schema.sql and the parts before it. Safe to run again: existing rows are left alone.\n\n"
    . "SET NAMES utf8mb4;\n";

$rows = [];
foreach ($seed['categories'] as $category) {
    $rows[] = '(' . implode(', ', [$quote($category['name']), $quote($category['slug']), $quote($category['icon'])]) . ')';
}
$files = [];
$sql = $header(1) . "\nINSERT IGNORE INTO categories (name, slug, icon) VALUES\n" . implode(",\n", $rows) . ";\n";

foreach (array_chunk($seed['products'], 25) as $chunk) {
    $rows = [];
    foreach ($chunk as $product) {
        $rows[] = 'SELECT ' . implode(', ', [
            $quote($product['sku']),
            $quote($product['name']),
            $quote($product['brand']),
            "(SELECT id FROM categories WHERE slug = {$quote($product['category'])})",
            $quote($product['description']),
            $quote(json_encode($product['attributes'], JSON_UNESCAPED_UNICODE)),
            number_format((float) $product['price'], 2, '.', ''),
            (int) $product['stock'],
        ]);
    }

    $statement = "\nINSERT IGNORE INTO products (sku, name, brand, category_id, description, attributes, price, stock)\n"
        . implode("\nUNION ALL ", $rows) . ";\n";

    if (strlen($sql . $statement) > MAX_BYTES) {
        $files[] = $sql;
        $sql = $header(count($files) + 1);
    }
    $sql .= $statement;
}
$files[] = $sql;

foreach (glob("$root/db/seed/products*.sql") as $old) {
    unlink($old);
}
foreach ($files as $i => $contents) {
    file_put_contents("$root/db/seed/products-" . ($i + 1) . '.sql', $contents);
}
echo count($seed['categories']) . ' categories and ' . count($seed['products']) . ' products written to db/seed/products-1.sql to products-' . count($files) . ".sql\n";
