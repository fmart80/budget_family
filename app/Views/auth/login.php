<div class="card mx-auto" style="max-width: 420px"><div class="card-body p-4">
    <h2 class="h5 mb-3">Connexion</h2>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= url('login') ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">E-mail</label>
            <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100">Se connecter</button>
    </form>
</div></div>
