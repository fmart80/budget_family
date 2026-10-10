<?php use App\Subscriptions; ?>
<h1 class="h3 mb-4"><?= $sub['id'] ? "Modifier l'abonnement" : 'Nouvel abonnement' ?></h1>
<div class="card" style="max-width: 760px"><div class="card-body">
<form method="post" action="<?= url('subscriptions/save') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($sub['id']) ?>">
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Nom</label>
            <input name="name" class="form-control" value="<?= e($sub['name']) ?>" required list="known" placeholder="Netflix, Spotify, Swisscom…">
            <datalist id="known"><?php foreach (['Netflix', 'Spotify', 'Disney+', 'Apple iCloud', 'Apple Music', 'YouTube Premium', 'Amazon Prime', 'Blue TV', 'Swisscom', 'Sunrise', 'Salt', 'Microsoft 365', 'Google One', 'ChatGPT', 'PlayStation Plus', 'Xbox Game Pass', 'Nintendo Switch Online', 'Audible', 'Deezer', 'DAZN', 'Fitness'] as $k): ?><option value="<?= $k ?>"><?php endforeach; ?></datalist></div>
        <div class="col-md-3 mb-3"><label class="form-label">Montant (CHF)</label>
            <input name="amount" class="form-control" value="<?= e($sub['amount']) ?>" inputmode="decimal" required></div>
        <div class="col-md-3 mb-3"><label class="form-label">Fréquence</label>
            <select name="cycle" class="form-select">
                <?php foreach (Subscriptions::CYCLE_LABELS as $k => $label): ?><option value="<?= $k ?>"<?= selected($k, $sub['cycle']) ?>><?= $label ?></option><?php endforeach; ?>
            </select></div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3"><label class="form-label">Date d'un prélèvement</label>
            <input type="date" name="anchor_date" class="form-control" value="<?= e($sub['anchor_date']) ?>" required>
            <div class="form-text">Le dernier ou le prochain : les suivants sont calculés.</div></div>
        <div class="col-md-4 mb-3"><label class="form-label">Payé avec</label>
            <select name="account_id" class="form-select"><option value="">—</option>
                <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"<?= selected($a['id'], $sub['account_id']) ?>><?= e($a['name']) ?><?= $a['type'] === 'carte' ? ' (carte de crédit)' : '' ?></option><?php endforeach; ?>
            </select>
            <div class="form-text">Pas de carte dans la liste ? Créez un <a href="<?= url('accounts/edit') ?>">compte</a> de type « Carte de crédit ».</div></div>
        <div class="col-md-4 mb-3"><label class="form-label">Catégorie</label>
            <select name="category_id" class="form-select"><option value="">—</option>
                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"<?= selected($c['id'], $sub['category_id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">Mot-clé sur le relevé</label>
            <input name="keyword" class="form-control" value="<?= e($sub['keyword']) ?>" placeholder="ex. netflix">
            <div class="form-text">Texte présent dans le libellé bancaire : sert à repérer les prélèvements et les hausses de prix. Par défaut : le nom.</div></div>
        <div class="col-md-6 mb-3"><label class="form-label">Pour qui (facultatif)</label>
            <input name="owner" class="form-control" value="<?= e($sub['owner']) ?>" placeholder="Famille, enfants, Fredy…"></div>
    </div>
    <div class="mb-3"><label class="form-label">Lien pour gérer / résilier (facultatif)</label>
        <input type="url" name="url" class="form-control" value="<?= e($sub['url']) ?>" placeholder="https://www.netflix.com/account"></div>
    <div class="mb-3"><label class="form-label">Notes</label>
        <input name="notes" class="form-control" value="<?= e($sub['notes']) ?>" maxlength="255" placeholder="Délai de résiliation, partage familial…"></div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="create_rule" id="rule"<?= checked(!$sub['id']) ?>>
        <label class="form-check-label" for="rule">Classer automatiquement les transactions importées contenant le mot-clé dans cette catégorie</label></div>
    <button class="btn btn-primary">Enregistrer</button>
    <a href="<?= url('subscriptions') ?>" class="btn btn-link">Annuler</a>
</form>
</div></div>
