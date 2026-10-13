<div class="panel narrow">
    <h1>Sign in</h1>
    <?= form_open('admin/login') ?>
        <?= form_label('Admin password', ['for' => 'password']) ?>
        <?= form_password('password', null, ['id' => 'password', 'autofocus' => 'autofocus', 'autocomplete' => 'current-password']) ?>
        <?= form_submit('submit', 'Sign in') ?>
    <?= form_close() ?>
</div>
