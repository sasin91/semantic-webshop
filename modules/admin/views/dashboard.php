<h1>Admin</h1>

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
