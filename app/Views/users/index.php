<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Utilisateurs</h1>
    <a href="<?= url('users/edit') ?>" class="btn btn-primary"><i class="bi bi-person-plus"></i> Ajouter</a>
</div>
<p class="text-muted small">Tous les membres partagent les mêmes données familiales. Les administrateurs peuvent en plus gérer les utilisateurs.</p>
<div class="card"><div class="table-responsive">
<table class="table mb-0">
    <thead><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th>Statut</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr class="<?= $u['is_active'] ? '' : 'text-muted' ?>">
            <td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td>
            <td><?= $u['role'] === 'admin' ? '<span class="badge text-bg-primary">admin</span>' : 'membre' ?></td>
            <td><?= $u['is_active'] ? 'actif' : 'désactivé' ?></td>
            <td class="text-end text-nowrap">
                <a href="<?= url('users/edit', ['id' => $u['id']]) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                <?php if ((int) $u['id'] !== (int) current_user()['id']): ?>
                <form method="post" action="<?= url('users/toggle') ?>" class="d-inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>"><?= $u['is_active'] ? 'Désactiver' : 'Réactiver' ?></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div></div>
