<h1 class="h3 mb-4"><?= $goal['id'] ? "Modifier l'objectif" : 'Nouvel objectif' ?></h1>
<div class="card" style="max-width: 640px"><div class="card-body">
<form method="post" action="<?= url('savings/save') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($goal['id']) ?>">
    <div class="row">
        <div class="col-md-9 mb-3"><label class="form-label">Nom</label>
            <input name="name" class="form-control" value="<?= e($goal['name']) ?>" required placeholder="Fonds d'urgence"></div>
        <div class="col-md-3 mb-3"><label class="form-label">Couleur</label>
            <input type="color" name="color" class="form-control form-control-color w-100" value="<?= e($goal['color']) ?>"></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Montant visé (CHF)</label>
            <input name="target_amount" class="form-control" value="<?= e($goal['target_amount']) ?>" inputmode="decimal" required></div>
        <div class="col-md-6 mb-3"><label class="form-label">Date visée (facultatif)</label>
            <input type="date" name="target_date" class="form-control" value="<?= e($goal['target_date']) ?>"></div>
    </div>
    <?php if (!$goal['id']): ?>
    <div class="mb-3"><label class="form-label">Déjà épargné (facultatif)</label>
        <input name="initial_amount" class="form-control" inputmode="decimal" placeholder="0.00"></div>
    <?php endif; ?>
    <div class="mb-3"><label class="form-label">Compte épargne associé (facultatif)</label>
        <select name="account_id" class="form-select"><option value="">—</option>
            <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"<?= selected($a['id'], $goal['account_id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?>
        </select>
        <div class="form-text">Permet de créer automatiquement un virement vers ce compte lors d'un versement.</div></div>
    <div class="mb-3"><label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= e($goal['notes']) ?></textarea></div>
    <button class="btn btn-primary">Enregistrer</button>
    <a href="<?= url('savings') ?>" class="btn btn-link">Annuler</a>
</form>
</div></div>
