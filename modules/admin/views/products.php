<div class="admin-head">
    <h1>Products</h1>
    <a class="button" href="<?= BASE_URL ?>admin/edit">New product</a>
</div>

<form class="admin-filter" action="<?= BASE_URL ?>admin/products" method="get">
    <input type="search" name="q" value="<?= out($filter) ?>" placeholder="Name, brand or SKU" aria-label="Filter products">
    <button type="submit">Filter</button>
</form>

<table class="admin-table">
    <thead>
        <tr><th>SKU</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Vector</th></tr>
    </thead>
    <tbody>
        <?php foreach ($products as $product): ?>
            <?php $vector = $status[$product['id']]; ?>
            <tr>
                <td><code><?= out($product['sku']) ?></code></td>
                <td><a href="<?= BASE_URL ?>admin/edit/<?= (int) $product['id'] ?>"><?= out($product['name']) ?></a> <small class="muted"><?= out($product['brand']) ?></small></td>
                <td><?= out($product['category']) ?></td>
                <td><?= nice_price((float) $product['price'], '€') ?></td>
                <td><?= (int) $product['stock'] ?></td>
                <td>
                    <span class="badge <?= $vector['status'] === 'current' ? 'badge-both' : 'badge-keyword' ?>"><?= out($vector['status']) ?></span>
                    <?php if ($vector['embedded_at'] !== null): ?><small class="muted"><?= out($vector['embedded_at']) ?></small><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($num_pages > 1): ?>
    <nav class="pager">
        <?php if ($page > 1): ?><a href="<?= BASE_URL ?>admin/products?<?= http_build_query(['q' => $filter, 'page' => $page - 1]) ?>">← Previous</a><?php endif; ?>
        <span>Page <?= $page ?> of <?= $num_pages ?></span>
        <?php if ($page < $num_pages): ?><a href="<?= BASE_URL ?>admin/products?<?= http_build_query(['q' => $filter, 'page' => $page + 1]) ?>">Next →</a><?php endif; ?>
    </nav>
<?php endif; ?>
