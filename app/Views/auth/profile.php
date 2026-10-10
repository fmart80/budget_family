<h1 class="h3 mb-4">Mon profil</h1>
<div class="card" style="max-width: 520px"><div class="card-body">
    <p><strong><?= e($user['name']) ?></strong><br><span class="text-muted"><?= e($user['email']) ?></span></p>
    <?php require APP_PATH . '/Views/setup/_errors.php'; ?>
    <form method="post" action="<?= url('profile') ?>">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Mot de passe actuel</label>
            <input type="password" name="current_password" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Nouveau mot de passe</label>
            <input type="password" name="new_password" class="form-control" minlength="10" required></div>
        <div class="mb-3"><label class="form-label">Confirmer</label>
            <input type="password" name="new_password_confirm" class="form-control" minlength="10" required></div>
        <button class="btn btn-primary">Changer le mot de passe</button>
    </form>
</div></div>
