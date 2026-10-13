<?php
/** One product tile. Expects $product; search results also carry matched_by and score_label. */
$badges = ['keyword' => 'Keyword', 'semantic' => 'Semantic', 'both' => 'Both'];
?>
<article class="card">
    <a class="card-link" href="<?= BASE_URL ?>product/<?= (int) $product['id'] ?>">
        <div class="card-art" data-category="<?= out($product['category_slug']) ?>"><span><?= out($product['category_icon']) ?></span></div>
        <div class="card-body">
            <div class="card-meta"><?= out($product['brand']) ?> · <?= out($product['category']) ?></div>
            <h3><?= out($product['name']) ?></h3>
            <div class="card-foot">
                <span class="price"><?= nice_price((float) $product['price'], '€') ?></span>
                <?php if ((int) $product['stock'] > 0): ?>
                    <span class="stock in">In stock</span>
                <?php else: ?>
                    <span class="stock out">Sold out</span>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <?php if (isset($product['matched_by'])): ?>
        <div class="match" title="<?= out($product['score_detail'] ?? '') ?>">
            <span class="badge badge-<?= $product['matched_by'] ?>"><?= $badges[$product['matched_by']] ?></span>
            <span class="score"><?= out($product['score_label']) ?></span>
        </div>
    <?php endif; ?>
</article>
