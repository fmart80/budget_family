<?php
use App\Forecast;
$selected = Forecast::parseMonths((string) $item['months']);
$presets = ['Tous les mois' => range(1, 12), 'Trimestriel' => [3, 6, 9, 12], 'Juin + décembre' => [6, 12], 'Décembre' => [12], 'Aucun' => []];
?>
<h1 class="h3 mb-4"><?= $item['id'] ? 'Modifier le poste' : ($item['type'] === 'income' ? 'Nouveau revenu prévu' : 'Nouvelle dépense prévue') ?></h1>
<div class="card" style="max-width: 760px"><div class="card-body">
<form method="post" action="<?= url('planned/save') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($item['id']) ?>">
    <div class="row">
        <div class="col-md-4 mb-3"><label class="form-label">Type</label>
            <select name="type" id="tx-type" class="form-select">
                <option value="expense"<?= selected('expense', $item['type']) ?>>Dépense</option>
                <option value="income"<?= selected('income', $item['type']) ?>>Revenu</option>
            </select></div>
        <div class="col-md-8 mb-3"><label class="form-label">Nom</label>
            <input name="name" class="form-control" value="<?= e($item['name']) ?>" required placeholder="Assurance ménage, 13e salaire, Loyer…"></div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3"><label class="form-label">Montant à chaque fois (CHF)</label>
            <input name="amount" class="form-control" value="<?= e($item['amount']) ?>" inputmode="decimal" required></div>
        <div class="col-md-4 mb-3"><label class="form-label">Jour du mois (approx.)</label>
            <input type="number" name="day" min="1" max="31" class="form-control" value="<?= e($item['day']) ?>">
            <div class="form-text">Sert à savoir si c'est déjà passé ce mois-ci.</div></div>
        <div class="col-md-4 mb-3"><label class="form-label">Catégorie</label>
            <select name="category_id" id="tx-category" class="form-select"><option value="">—</option>
                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" data-type="<?= $c['type'] ?>"<?= selected($c['id'], $item['category_id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select></div>
    </div>
    <div class="mb-3">
        <label class="form-label">Mois concernés</label>
        <div class="d-flex flex-wrap gap-1 mb-2">
            <?php foreach ($presets as $label => $set): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-months="<?= implode(',', $set) ?>"><?= $label ?></button>
            <?php endforeach; ?>
        </div>
        <div class="d-flex flex-wrap gap-3">
            <?php foreach (Forecast::monthNames() as $n => $name): ?>
                <div class="form-check"><input class="form-check-input month-check" type="checkbox" name="months[]" value="<?= $n ?>" id="m<?= $n ?>"<?= checked(in_array($n, $selected, true)) ?>>
                    <label class="form-check-label" for="m<?= $n ?>"><?= ucfirst($name) ?></label></div>
            <?php endforeach; ?>
        </div>
        <div class="form-text">Ex. 13ᵉ salaire versé à 50 % en juin et 50 % en décembre : montant = la moitié, mois = juin + décembre.</div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label">À partir de (facultatif)</label>
            <input type="month" name="start_month" class="form-control" value="<?= e($item['start_month']) ?>"></div>
        <div class="col-md-6 mb-3"><label class="form-label">Jusqu'à (facultatif)</label>
            <input type="month" name="end_month" class="form-control" value="<?= e($item['end_month']) ?>">
            <div class="form-text">Ex. fin d'un leasing, crèche jusqu'à l'école…</div></div>
    </div>
    <div class="mb-3"><label class="form-label">Note</label>
        <input name="notes" class="form-control" value="<?= e($item['notes']) ?>" maxlength="255"></div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" id="active"<?= checked($item['is_active']) ?>>
        <label class="form-check-label" for="active">Actif (pris en compte dans les prévisions)</label></div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary">Enregistrer</button>
        <?php if (!$item['id']): ?><button name="save_new" value="1" class="btn btn-outline-primary">Enregistrer et nouveau</button><?php endif; ?>
        <a href="<?= url('planned') ?>" class="btn btn-link">Annuler</a>
    </div>
</form>
</div></div>
