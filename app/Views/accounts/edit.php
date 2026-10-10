<h1 class="h3 mb-4"><?= $account['id'] ? 'Modifier le compte' : 'Nouveau compte' ?></h1>
<div class="card" style="max-width: 640px"><div class="card-body">
<form method="post" action="<?= url('accounts/save') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($account['id']) ?>">
    <div class="mb-3"><label class="form-label">Nom</label>
        <input name="name" class="form-control" value="<?= e($account['name']) ?>" required placeholder="UBS – Compte privé"></div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Type</label>
            <select name="type" class="form-select">
                <?php foreach (account_types() as $k => $label): ?><option value="<?= $k ?>"<?= selected($k, $account['type']) ?>><?= e($label) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-6 mb-3"><label class="form-label">Banque</label>
            <input name="bank" class="form-control" value="<?= e($account['bank']) ?>" list="banks">
            <datalist id="banks"><option value="UBS"><option value="BCF"><option value="PostFinance"></datalist></div>
    </div>
    <div class="mb-3"><label class="form-label">IBAN (facultatif)</label>
        <input name="iban" class="form-control" value="<?= e($account['iban']) ?>" placeholder="CH.."></div>
    <div class="mb-3"><label class="form-label">Solde initial (CHF)</label>
        <input name="opening_balance" class="form-control" value="<?= e($account['opening_balance']) ?>" inputmode="decimal">
        <div class="form-text">Solde du compte à la date de la première transaction saisie ou importée.</div></div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" id="active"<?= checked($account['is_active']) ?>>
        <label class="form-check-label" for="active">Compte actif</label></div>
    <button class="btn btn-primary">Enregistrer</button>
    <a href="<?= url('accounts') ?>" class="btn btn-link">Annuler</a>
</form>
</div></div>
