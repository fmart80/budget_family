<div class="card"><div class="card-body">
    <h2 class="h5 text-danger">Connexion à la base de données impossible</h2>
    <p>Vérifiez l'hôte, le nom de la base, l'utilisateur et le mot de passe dans <code>config/config.php</code>.</p>
    <?php if ($error): ?><pre class="bg-light p-2 rounded small"><?= e($error) ?></pre><?php endif; ?>
</div></div>
