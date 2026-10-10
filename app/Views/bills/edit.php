<h1 class="h3 mb-4"><?= $bill['id'] ? 'Modifier la facture' : 'Nouvelle facture' ?></h1>
<div class="card" style="max-width: 760px"><div class="card-body">
<form method="post" action="<?= url('bills/save') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($bill['id']) ?>">
    <div class="row">
        <div class="col-md-7 mb-3"><label class="form-label">Nom</label>
            <input name="name" class="form-control" value="<?= e($bill['name']) ?>" required placeholder="Prime LAMal, Loyer, Serafe…"></div>
        <div class="col-md-5 mb-3"><label class="form-label">Bénéficiaire</label>
            <input name="payee" class="form-control" value="<?= e($bill['payee']) ?>"></div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3"><label class="form-label">Montant (CHF)</label>
            <input name="amount" class="form-control" value="<?= e($bill['amount']) ?>" inputmode="decimal" required></div>
        <div class="col-md-4 mb-3"><label class="form-label">Échéance</label>
            <input type="date" name="due_date" class="form-control" value="<?= e($bill['due_date']) ?>" required></div>
        <div class="col-md-4 mb-3"><label class="form-label">Récurrence</label>
            <select name="recurrence" class="form-select">
                <?php foreach (recurrence_labels() as $k => $label): ?><option value="<?= $k ?>"<?= selected($k, $bill['recurrence']) ?>><?= $label ?></option><?php endforeach; ?>
            </select></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Catégorie</label>
            <select name="category_id" class="form-select"><option value="">—</option>
                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"<?= selected($c['id'], $bill['category_id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-6 mb-3"><label class="form-label">Compte de paiement habituel</label>
            <select name="account_id" class="form-select"><option value="">—</option>
                <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"<?= selected($a['id'], $bill['account_id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?>
            </select></div>
    </div>
    <div class="mb-3"><label class="form-label">Référence (QR-facture / BVR)</label>
        <input name="reference" class="form-control" value="<?= e($bill['reference']) ?>"></div>
    <div class="mb-3"><label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= e($bill['notes']) ?></textarea></div>
    <div class="mb-3"><label class="form-label">Pièce jointe (PDF, JPG, PNG)</label>
        <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        <?php if ($bill['attachment']): ?>
            <div class="form-text"><a href="<?= url('bills/file', ['id' => $bill['id']]) ?>" target="_blank"><i class="bi bi-paperclip"></i> <?= e($bill['attachment_name']) ?></a>
                <label class="ms-3"><input type="checkbox" name="remove_attachment" class="form-check-input"> supprimer</label></div>
        <?php endif; ?>
    </div>
    <button class="btn btn-primary">Enregistrer</button>
    <a href="<?= url('bills') ?>" class="btn btn-link">Annuler</a>
</form>
</div></div>
