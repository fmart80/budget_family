<div class="card"><div class="card-body">
    <h2 class="h5">Installation — étape 1/2</h2>
    <p>La connexion à la base fonctionne. Cliquez ci-dessous pour créer les tables et les catégories par défaut.</p>
    <?php require __DIR__ . '/_errors.php'; ?>
    <form method="post" action="<?= url('setup') ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Clé d'installation (<code>setup_key</code>)</label>
            <input type="password" name="setup_key" class="form-control" required>
        </div>
        <button class="btn btn-primary">Installer la base de données</button>
    </form>
</div></div>
