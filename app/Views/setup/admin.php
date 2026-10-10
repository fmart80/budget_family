<div class="card"><div class="card-body">
    <h2 class="h5">Installation — étape 2/2 : compte administrateur</h2>
    <?php require __DIR__ . '/_errors.php'; ?>
    <form method="post" action="<?= url('setup') ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Clé d'installation (<code>setup_key</code>)</label>
            <input type="password" name="setup_key" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Votre nom</label>
            <input name="name" class="form-control" value="<?= e($data['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">E-mail (identifiant de connexion)</label>
            <input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Mot de passe (10 caractères minimum)</label>
            <input type="password" name="password" class="form-control" minlength="10" required>
        </div>
        <button class="btn btn-primary">Créer le compte</button>
    </form>
</div></div>
