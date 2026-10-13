<?php
/**
 * Write db/seed/products.sql from db/seed/products.json, for loading the
 * catalogue in a SQL console instead of with Install and seed in the admin.
 * Like the installer, it skips categories and products that already exist.
 *
 *   php bin/seed-sql.php
 */
$root = dirname(__DIR__);
$seed = json_decode(file_get_contents("$root/db/seed/products.json"), true, flags: JSON_THROW_ON_ERROR);

$quote = fn(string $value): string => "'" . str_replace(['\\', "'"], ['\\\\', "''"], $value) . "'";

$sql = "-- Seed catalogue, generated from db/seed/products.json by bin/seed-sql.php.\n"
    . "-- Run after db/schema.sql. Safe to run again: existing rows are left alone.\n\n"
    . "SET NAMES utf8mb4;\n\n"
    . "INSERT IGNORE INTO categories (name, slug, icon) VALUES\n";

$rows = [];
foreach ($seed['categories'] as $category) {
    $rows[] = '(' . implode(', ', [$quote($category['name']), $quote($category['slug']), $quote($category['icon'])]) . ')';
}
$sql .= implode(",\n", $rows) . ";\n";

foreach (array_chunk($seed['products'], 50) as $chunk) {
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

    $sql .= "\nINSERT IGNORE INTO products (sku, name, brand, category_id, description, attributes, price, stock)\n"
        . implode("\nUNION ALL ", $rows) . ";\n";
}

file_put_contents("$root/db/seed/products.sql", $sql);
echo count($seed['categories']) . ' categories and ' . count($seed['products']) . " products written to db/seed/products.sql\n";
