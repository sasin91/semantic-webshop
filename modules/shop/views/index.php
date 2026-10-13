<?php
$filter_query = fn(array $changes) => http_build_query(array_filter(array_merge([
    'q' => $query,
    'mode' => $mode,
    'category' => $filters['category'],
    'min_price' => $filters['min_price'],
    'max_price' => $filters['max_price'],
    'in_stock' => $filters['in_stock'] ? '1' : null,
], $changes), fn($value) => $value !== null && $value !== ''));
?>
<div class="shop-layout">
    <aside class="filters">
        <form action="<?= BASE_URL ?>" method="get">
            <input type="hidden" name="q" value="<?= out($query) ?>">
            <input type="hidden" name="mode" value="<?= out($mode) ?>">
            <input type="hidden" name="category" value="<?= out($filters['category']) ?>">
            <h2>Category</h2>
            <ul class="category-list">
                <li><a class="<?= $filters['category'] === '' ? 'active' : '' ?>" href="<?= BASE_URL ?>?<?= $filter_query(['category' => null]) ?>">All categories</a></li>
                <?php foreach ($categories as $category): ?>
                    <li>
                        <a class="<?= $filters['category'] === $category['slug'] ? 'active' : '' ?>" href="<?= BASE_URL ?>?<?= $filter_query(['category' => $category['slug']]) ?>">
                            <span><?= out($category['icon']) ?> <?= out($category['name']) ?></span> <small><?= (int) $category['num_products'] ?></small>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <h2>Price (€)</h2>
            <div class="price-range">
                <input type="number" name="min_price" min="0" step="1" placeholder="Min" value="<?= $filters['min_price'] ?? '' ?>" aria-label="Minimum price">
                <span>to</span>
                <input type="number" name="max_price" min="0" step="1" placeholder="Max" value="<?= $filters['max_price'] ?? '' ?>" aria-label="Maximum price">
            </div>
            <label class="checkbox"><input type="checkbox" name="in_stock" value="1" <?= $filters['in_stock'] ? 'checked' : '' ?>> In stock only</label>
            <button type="submit">Apply filters</button>
        </form>
    </aside>

    <section class="results">
        <div class="results-head">
            <h1><?= $query === '' ? 'All products' : 'Results for “' . out($query) . '”' ?></h1>
            <p class="results-count">
                <?= (int) $total ?> <?= $total === 1 ? 'product' : 'products' ?>
                <?php if (isset($search)): ?>
                    · <?= out($search['mode']) ?> search in <?= (int) $search['took_ms'] ?> ms<?= $search['cached'] ? ' (query vector from cache)' : '' ?>
                <?php endif; ?>
            </p>
            <?php if ($query !== ''): ?>
                <nav class="mode-tabs" aria-label="Search mode">
                    <?php foreach (Search::MODES as $tab): ?>
                        <a class="<?= $tab === $mode ? 'active' : '' ?>" href="<?= BASE_URL ?>?<?= $filter_query(['mode' => $tab]) ?>"><?= ucfirst($tab) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </div>

        <?php if (!empty($search['notice'])): ?>
            <div class="notice"><?= out($search['notice']) ?></div>
        <?php endif; ?>

        <?php if ($products === []): ?>
            <p class="empty">Nothing matched. Try other words or fewer filters.</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($products as $product): ?>
                    <?php require __DIR__ . '/_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($num_pages) && $num_pages > 1): ?>
            <nav class="pager">
                <?php if ($page > 1): ?>
                    <a href="<?= BASE_URL ?>?<?= $filter_query(['page' => $page - 1]) ?>">← Previous</a>
                <?php endif; ?>
                <span>Page <?= $page ?> of <?= $num_pages ?></span>
                <?php if ($page < $num_pages): ?>
                    <a href="<?= BASE_URL ?>?<?= $filter_query(['page' => $page + 1]) ?>">Next →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</div>
