<?php
$attributes = json_decode($product['attributes'], true) ?: [];
$attribute_lines = implode("\n", array_map(fn($key, $value) => "{$key}: {$value}", array_keys($attributes), $attributes));
?>
<div class="admin-head">
    <h1><?= $id > 0 ? 'Edit product' : 'New product' ?></h1>
    <?php if ($id > 0): ?><a href="<?= BASE_URL ?>product/<?= $id ?>">View in shop →</a><?php endif; ?>
</div>

<section class="panel">
    <?= validation_errors('<p class="form-error">', '</p>') ?>
    <?= form_open($id > 0 ? "admin/edit/{$id}" : 'admin/edit', ['class' => 'product-form']) ?>
        <div class="form-row">
            <div>
                <?= form_label('SKU', ['for' => 'sku']) ?>
                <?= form_input('sku', $product['sku'], ['id' => 'sku', 'required' => 'required']) ?>
            </div>
            <div>
                <?= form_label('Brand', ['for' => 'brand']) ?>
                <?= form_input('brand', $product['brand'], ['id' => 'brand', 'required' => 'required']) ?>
            </div>
            <div>
                <?= form_label('Category', ['for' => 'category_id']) ?>
                <?= form_dropdown('category_id', $categories, $product['category_id'], ['id' => 'category_id']) ?>
            </div>
        </div>
        <?= form_label('Name', ['for' => 'name']) ?>
        <?= form_input('name', $product['name'], ['id' => 'name', 'required' => 'required']) ?>
        <?= form_label('Description', ['for' => 'description']) ?>
        <?= form_textarea('description', $product['description'], ['id' => 'description', 'rows' => 5, 'required' => 'required']) ?>
        <?= form_label('Key attributes, one "Name: value" per line', ['for' => 'attributes']) ?>
        <?= form_textarea('attributes', $attribute_lines, ['id' => 'attributes', 'rows' => 4]) ?>
        <div class="form-row">
            <div>
                <?= form_label('Price (€)', ['for' => 'price']) ?>
                <?= form_number('price', $product['price'], ['id' => 'price', 'step' => '0.01', 'min' => '0']) ?>
            </div>
            <div>
                <?= form_label('Stock', ['for' => 'stock']) ?>
                <?= form_number('stock', $product['stock'], ['id' => 'stock', 'step' => '1']) ?>
            </div>
        </div>
        <p class="muted small">Name, category, brand, description and attributes are embedded. Saving re-embeds the product when any of them changed.</p>
        <?= form_submit('submit', 'Save') ?>
    <?= form_close() ?>
</section>

<?php if ($id > 0): ?>
    <?= form_open("admin/delete/{$id}", ['onsubmit' => "return confirm('Delete this product?')"]) ?>
        <button type="submit" class="button-danger">Delete product</button>
    <?= form_close() ?>
<?php endif; ?>
