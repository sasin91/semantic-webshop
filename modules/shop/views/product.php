<?php $attributes = json_decode($product['attributes'], true) ?: []; ?>
<nav class="crumbs">
    <a href="<?= BASE_URL ?>">Shop</a> ›
    <a href="<?= BASE_URL ?>?category=<?= out($product['category_slug']) ?>"><?= out($product['category']) ?></a>
</nav>
<div class="product">
    <div class="product-art card-art" data-category="<?= out($product['category_slug']) ?>"><span><?= out($product['category_icon']) ?></span></div>
    <div class="product-info">
        <div class="card-meta"><?= out($product['brand']) ?> · SKU <?= out($product['sku']) ?></div>
        <h1><?= out($product['name']) ?></h1>
        <p class="product-price"><?= nice_price((float) $product['price'], '€') ?>
            <?php if ((int) $product['stock'] > 0): ?>
                <span class="stock in"><?= (int) $product['stock'] ?> in stock</span>
            <?php else: ?>
                <span class="stock out">Sold out</span>
            <?php endif; ?>
        </p>
        <p class="product-description"><?= nl2br(out($product['description'])) ?></p>
        <?php if ($attributes !== []): ?>
            <table class="attributes">
                <?php foreach ($attributes as $name => $value): ?>
                    <tr><th><?= out((string) $name) ?></th><td><?= out((string) $value) ?></td></tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($similar !== []): ?>
    <section class="similar">
        <h2>More like this</h2>
        <p class="muted">The nearest products to this one's own vector: similar in meaning, from any category.</p>
        <div class="grid">
            <?php foreach ($similar as $product): ?>
                <?php $product += ['matched_by' => 'semantic', 'score_label' => 'similarity ' . number_format($product['similarity'], 2)]; ?>
                <?php require __DIR__ . '/_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
