<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/trongate.css">
    <?= $additional_includes_top ?? '' ?>
    <title><?= out(($page_title ?? '') !== '' ? $page_title . ' · Semantic Shop' : 'Semantic Shop') ?></title>
</head>
<body class="shop">
    <header class="site-header">
        <div class="site-header-inner">
            <a class="logo" href="<?= BASE_URL ?>">Semantic<span>Shop</span></a>
            <form class="site-search" action="<?= BASE_URL ?>" method="get" role="search">
                <input type="search" name="q" value="<?= out($query ?? '') ?>" placeholder="Describe what you need, e.g. warm jacket for hiking in the rain" aria-label="Search products">
                <button type="submit">Search</button>
            </form>
            <nav class="site-nav">
                <a href="<?= BASE_URL ?>">Shop</a>
                <a href="<?= BASE_URL ?>admin">Admin</a>
            </nav>
        </div>
    </header>
    <main class="site-main">
        <?= display($data) ?>
    </main>
    <footer class="site-footer">
        A demo of semantic product search with EmbeddingGemma 2 and MariaDB, built on Trongate.
    </footer>
<?= $additional_includes_btm ?? '' ?>
</body>
</html>
