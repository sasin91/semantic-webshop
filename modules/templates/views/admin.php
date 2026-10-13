<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/trongate.css">
    <?= $additional_includes_top ?? '' ?>
    <title><?= out(($page_title ?? 'Admin') . ' · Semantic Shop admin') ?></title>
</head>
<body class="shop admin">
    <header class="site-header">
        <div class="site-header-inner">
            <a class="logo" href="<?= BASE_URL ?>admin">Semantic<span>Shop</span> <em>admin</em></a>
            <nav class="site-nav">
                <a href="<?= BASE_URL ?>">View shop</a>
                <?php if (!empty($_SESSION['admin_signed_in'])): ?>
                    <a href="<?= BASE_URL ?>admin">Dashboard</a>
                    <a href="<?= BASE_URL ?>admin/logout">Sign out</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="site-main">
        <?= flashdata('<div class="flash">', '</div>') ?>
        <?= display($data) ?>
    </main>
<?= $additional_includes_btm ?? '' ?>
</body>
</html>
