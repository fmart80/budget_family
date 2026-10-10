<h1 class="h3 mb-4"><?= $tx['id'] ? 'Modifier la transaction' : 'Nouvelle transaction' ?></h1>
<div class="card" style="max-width: 720px"><div class="card-body">
<form method="post" action="<?= url('transactions/save') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($tx['id']) ?>">
    <div class="row">
        <div class="col-md-4 mb-3"><label class="form-label">Type</label>
            <select name="type" id="tx-type" class="form-select">
                <option value="expense"<?= selected('expense', $tx['type']) ?>>Dépense</option>
                <option value="income"<?= selected('income', $tx['type']) ?>>Revenu</option>
                <option value="transfer"<?= selected('transfer', $tx['type']) ?>>Virement entre comptes</option>
            </select></div>
        <div class="col-md-4 mb-3"><label class="form-label">Date</label>
            <input type="date" name="date" class="form-control" value="<?= e($tx['date']) ?>" required></div>
        <div class="col-md-4 mb-3"><label class="form-label">Montant (CHF)</label>
            <input name="amount" class="form-control" value="<?= e($tx['amount']) ?>" inputmode="decimal" required autofocus></div>
    </div>
    <div class="mb-3"><label class="form-label">Libellé</label>
        <input name="description" class="form-control" value="<?= e($tx['description']) ?>" required maxlength="255"></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label"><span class="not-transfer">Compte</span><span class="only-transfer d-none">Depuis le compte</span></label>
            <select name="account_id" class="form-select" required>
                <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"<?= selected($a['id'], $tx['account_id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-6 mb-3 not-transfer"><label class="form-label">Catégorie</label>
            <select name="category_id" id="tx-category" class="form-select">
                <option value="">— Sans catégorie —</option>
                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" data-type="<?= $c['type'] ?>"<?= selected($c['id'], $tx['category_id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-6 mb-3 only-transfer d-none"><label class="form-label">Vers le compte</label>
            <select name="to_account_id" class="form-select">
                <option value="">—</option>
                <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"<?= selected($a['id'], $tx['to_account_id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?>
            </select></div>
    </div>
    <div class="mb-3"><label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= e($tx['notes']) ?></textarea></div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary">Enregistrer</button>
        <?php if (!$tx['id']): ?><button name="save_new" value="1" class="btn btn-outline-primary">Enregistrer et nouvelle</button><?php endif; ?>
        <a href="<?= e($back) ?>" class="btn btn-link">Annuler</a>
    </div>
</form>
<?php if ($tx['id']): ?>
    <hr>
    <form method="post" action="<?= url('transactions/delete') ?>" data-confirm="Supprimer cette transaction ?">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $tx['id'] ?>"><input type="hidden" name="month" value="<?= e(substr($tx['date'], 0, 7)) ?>">
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Supprimer</button>
    </form>
<?php endif; ?>
</div></div>
