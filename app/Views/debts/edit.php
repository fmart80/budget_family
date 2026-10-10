<h1 class="h3 mb-4"><?= $debt['id'] ? 'Modifier la dette' : 'Nouvelle dette' ?></h1>
<div class="card" style="max-width: 680px"><div class="card-body">
<form method="post" action="<?= url('debts/save') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($debt['id']) ?>">
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Nom</label>
            <input name="name" class="form-control" value="<?= e($debt['name']) ?>" required placeholder="Leasing voiture"></div>
        <div class="col-md-6 mb-3"><label class="form-label">Créancier</label>
            <input name="creditor" class="form-control" value="<?= e($debt['creditor']) ?>"></div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3"><label class="form-label">Montant emprunté</label>
            <input name="initial_amount" class="form-control" value="<?= e($debt['initial_amount']) ?>" inputmode="decimal" required></div>
        <div class="col-md-4 mb-3"><label class="form-label">Taux annuel (%)</label>
            <input name="interest_rate" class="form-control" value="<?= e($debt['interest_rate']) ?>" inputmode="decimal"></div>
        <div class="col-md-4 mb-3"><label class="form-label">Mensualité</label>
            <input name="monthly_payment" class="form-control" value="<?= e($debt['monthly_payment']) ?>" inputmode="decimal"></div>
    </div>
    <div class="mb-3"><label class="form-label">Date de début</label>
        <input type="date" name="start_date" class="form-control" value="<?= e($debt['start_date']) ?>"></div>
    <div class="form-text mb-3">Astuce : pour une dette déjà en cours, saisissez comme « montant emprunté » le solde restant actuel.</div>
    <div class="mb-3"><label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= e($debt['notes']) ?></textarea></div>
    <?php if ($debt['id']): ?><div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="is_closed" id="closed"<?= checked($debt['is_closed']) ?>>
        <label for="closed" class="form-check-label">Dette clôturée</label></div><?php endif; ?>
    <button class="btn btn-primary">Enregistrer</button>
    <a href="<?= url($debt['id'] ? 'debts/show' : 'debts', $debt['id'] ? ['id' => $debt['id']] : []) ?>" class="btn btn-link">Annuler</a>
</form>
</div></div>
