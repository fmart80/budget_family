<h1 class="h3 mb-4">Payer : <?= e($bill['name']) ?></h1>
<div class="card" style="max-width: 560px"><div class="card-body">
<form method="post" action="<?= url('bills/pay', ['id' => $bill['id']]) ?>">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-6 mb-3"><label class="form-label">Date de paiement</label>
            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-6 mb-3"><label class="form-label">Montant payé</label>
            <input name="amount" class="form-control" value="<?= e($bill['amount']) ?>" inputmode="decimal"></div>
    </div>
    <div class="mb-3"><label class="form-label">Débiter le compte</label>
        <select name="account_id" class="form-select">
            <option value="">Ne pas créer de transaction (déjà importée depuis la banque)</option>
            <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"<?= selected($a['id'], $bill['account_id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?>
        </select>
        <div class="form-text">Si vous importez vos relevés UBS / BCF, choisissez « Ne pas créer de transaction » pour éviter les doublons.</div></div>
    <?php if ($bill['recurrence'] !== 'none'): ?>
        <div class="alert alert-info small">Facture <?= e(mb_strtolower(recurrence_labels()[$bill['recurrence']])) ?> : la prochaine échéance sera créée automatiquement.</div>
    <?php endif; ?>
    <button class="btn btn-success">Confirmer le paiement</button>
    <a href="<?= url('bills') ?>" class="btn btn-link">Annuler</a>
</form>
</div></div>
