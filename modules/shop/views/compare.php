<div class="compare-head">
    <h1>Keyword vs semantic vs hybrid</h1>
    <p class="muted">The same query, three ways. Keyword search matches words (MariaDB FULLTEXT), semantic search
    matches meaning (EmbeddingGemma 2 vectors), and hybrid merges both rankings with Reciprocal Rank Fusion.</p>
    <form class="compare-search" action="<?= BASE_URL ?>compare" method="get">
        <input type="search" name="q" value="<?= out($query) ?>" aria-label="Query to compare">
        <button type="submit">Compare</button>
    </form>
    <div class="examples">
        <?php foreach ($examples as $example => $why): ?>
            <a class="chip <?= $example === $query ? 'active' : '' ?>" href="<?= BASE_URL ?>compare?q=<?= urlencode($example) ?>">
                <?= out($example) ?> <small><?= out($why) ?></small>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="compare-columns">
    <?php foreach ($columns as $mode => $column): ?>
        <section class="compare-column">
            <h2><?= out($column['title']) ?></h2>
            <p class="muted small"><?= count($column['results']) ?> results in <?= (int) $column['took_ms'] ?> ms</p>
            <?php if ($column['mode'] !== $mode): ?>
                <div class="notice"><?= out((string) $column['notice']) ?></div>
            <?php endif; ?>
            <?php if ($column['results'] === []): ?>
                <p class="empty">No results.</p>
            <?php endif; ?>
            <ol class="compare-list">
                <?php foreach ($column['results'] as $product): ?>
                    <li>
                        <a href="<?= BASE_URL ?>product/<?= (int) $product['id'] ?>">
                            <span class="compare-icon" data-category="<?= out($product['category_slug']) ?>"><?= out($product['category_icon']) ?></span>
                            <span class="compare-name"><?= out($product['name']) ?><small><?= out($product['brand']) ?> · <?= out($product['sku']) ?></small></span>
                        </a>
                        <span class="match" title="<?= out($product['score_detail'] ?? '') ?>">
                            <span class="badge badge-<?= $product['matched_by'] ?>"><?= ucfirst($product['matched_by']) ?></span>
                            <span class="score"><?= out($product['score_label']) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endforeach; ?>
</div>
