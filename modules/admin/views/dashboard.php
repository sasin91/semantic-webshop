<h1>Admin</h1>

<div class="admin-columns">
    <section class="panel">
        <h2>Catalogue</h2>
        <table class="facts">
            <tr><th>Database</th><td><?= out($status['database_version']) ?></td></tr>
            <tr><th>Tables</th><td><?= $status['installed'] ? 'Installed' : 'Not installed' ?></td></tr>
            <tr><th>Categories</th><td><?= (int) $status['categories'] ?></td></tr>
            <tr><th>Products</th><td><?= (int) $status['products'] ?></td></tr>
        </table>
        <?= form_open('admin/install') ?>
            <p>Creates any missing tables and adds the demo products that are not there yet. Products you edited are kept.</p>
            <?= form_submit('submit', 'Install and seed') ?>
        <?= form_close() ?>
    </section>

    <section class="panel">
        <h2>Embeddings service</h2>
        <table class="facts">
            <tr><th>Status</th><td>
                <?php if ($health['ok']): ?>
                    <span class="badge badge-both">Up</span> <?= (int) $health['latency_ms'] ?> ms for one query
                <?php else: ?>
                    <span class="badge badge-down">Down</span> <?= out((string) $health['error']) ?>
                <?php endif; ?>
            </td></tr>
            <tr><th>Address</th><td><code><?= out($health['url']) ?></code></td></tr>
            <tr><th>Model</th><td><?= out($settings['model']) ?></td></tr>
            <tr><th>Version</th><td><?= out($settings['version']) ?></td></tr>
            <tr><th>Dimensions</th><td><?= (int) $settings['dimensions'] ?></td></tr>
            <tr><th>Vector store</th><td><?= $backend === 'native' ? 'MariaDB VECTOR column + vector index' : 'Float BLOBs, cosine in PHP' ?></td></tr>
        </table>
    </section>
</div>

<?php if ($embeddings !== null): ?>
    <section class="panel" id="embed-panel">
        <h2>Product vectors</h2>
        <table class="facts">
            <tr><th>Current</th><td><span id="embed-current"><?= $embeddings['current'] ?></span> of <?= $embeddings['total'] ?></td></tr>
            <tr><th>Missing</th><td><?= $embeddings['missing'] ?></td></tr>
            <tr><th>Outdated</th><td><?= $embeddings['stale'] ?> <small>(text changed or another model)</small></td></tr>
            <tr><th>Cached queries</th><td><?= $embeddings['cached_queries'] ?></td></tr>
        </table>
        <progress id="embed-progress" max="<?= $embeddings['total'] ?>" value="<?= $embeddings['current'] ?>"></progress>
        <p id="embed-message" class="muted"></p>
        <?= form_open('admin/embed', ['id' => 'embed-form', 'data-total' => $embeddings['total']]) ?>
            <button type="submit" name="all" value="0">Embed missing and outdated</button>
            <button type="submit" name="all" value="1" class="button-secondary">Re-embed all</button>
        <?= form_close() ?>
    </section>
<?php endif; ?>
