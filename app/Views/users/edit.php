<h1 class="h3 mb-4"><?= $user['id'] ? "Modifier l'utilisateur" : 'Nouvel utilisateur' ?></h1>
<div class="card" style="max-width: 560px"><div class="card-body">
<form method="post" action="<?= url('users/save') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($user['id']) ?>">
    <div class="mb-3"><label class="form-label">Nom</label><input name="name" class="form-control" value="<?= e($user['name']) ?>" required></div>
    <div class="mb-3"><label class="form-label">E-mail</label><input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Rôle</label>
        <select name="role" class="form-select">
            <option value="member"<?= selected('member', $user['role']) ?>>Membre</option>
            <option value="admin"<?= selected('admin', $user['role']) ?>>Administrateur</option>
        </select></div>
    <div class="mb-3"><label class="form-label">Mot de passe<?= $user['id'] ? ' (laisser vide pour ne pas changer)' : '' ?></label>
        <input type="password" name="password" class="form-control" minlength="10" <?= $user['id'] ? '' : 'required' ?>></div>
    <button class="btn btn-primary">Enregistrer</button>
    <a href="<?= url('users') ?>" class="btn btn-link">Annuler</a>
</form>
</div></div>
