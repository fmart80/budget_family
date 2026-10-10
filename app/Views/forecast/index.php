<?php
$m = $forecast['months'];
$chart = ['type' => 'bar', 'data' => [
    'labels' => array_map(fn ($r) => month_label($r['month']), $m),
    'datasets' => [
        ['type' => 'line', 'label' => 'Solde prévu', 'data' => array_map(fn ($r) => round($r['balance'], 2), $m),
            'borderColor' => '#0d6efd', 'backgroundColor' => '#0d6efd', 'tension' => 0.2, 'yAxisID' => 'y'],
        ['label' => 'Revenus', 'data' => array_column($m, 'income'), 'backgroundColor' => 'rgba(25,135,84,.6)'],
        ['label' => 'Dépenses + dettes', 'data' => array_map(fn ($r) => round($r['expense'] + $r['debt'], 2), $m), 'backgroundColor' => 'rgba(220,53,69,.6)'],
    ]]];
$tight = array_filter($m, fn ($r) => $r['net'] < 0);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Prévisions sur <?= count($m) ?> mois</h1>
    <div class="d-flex gap-2">
        <a href="<?= url('forecast/smoothing') ?>" class="btn btn-outline-primary"><i class="bi bi-distribute-vertical"></i> Lissage</a>
        <a href="<?= url('planned') ?>" class="btn btn-primary"><i class="bi bi-list-check"></i> Postes prévus</a>
    </div>
</div>

<?php if (!$hasItems): ?>
    <div class="alert alert-info">Commencez par saisir vos <a href="<?= url('planned') ?>">postes prévus</a> (salaires, 13ᵉ, loyer, assurances, impôts…). <a href="<?= url('guide') ?>">Voir le guide</a>.</div>
<?php endif; ?>

<form method="get" class="card card-body mb-3 no-print">
    <input type="hidden" name="p" value="forecast">
    <div class="small mb-1">Solde de départ = solde actuel des comptes :</div>
    <div class="d-flex flex-wrap gap-3 align-items-center">
        <?php foreach ($accounts as $a): ?>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="accounts[]" value="<?= $a['id'] ?>" id="acc<?= $a['id'] ?>"<?= checked(in_array((int) $a['id'], $forecast['account_ids'], true)) ?>>
                <label class="form-check-label" for="acc<?= $a['id'] ?>"><?= e($a['name']) ?></label></div>
        <?php endforeach; ?>
        <select name="months" class="form-select form-select-sm w-auto">
            <?php foreach ([6, 12, 18, 24] as $n): ?><option value="<?= $n ?>"<?= selected($n, count($m)) ?>><?= $n ?> mois</option><?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary">Recalculer</button>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Solde de départ</div><div class="stat-value"><?= money($forecast['start_balance']) ?></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Point le plus bas prévu</div>
        <div class="stat-value <?= $forecast['lowest'] < 0 ? 'amount-expense' : '' ?>"><?= money($forecast['lowest']) ?></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Solde dans <?= count($m) ?> mois</div>
        <div class="stat-value"><?= money(end($m)['balance'] ?? $forecast['start_balance']) ?></div></div></div>
</div>

<?php if ($forecast['lowest'] < 0): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> Le solde prévu passe <strong>sous zéro</strong>. Regardez le <a href="<?= url('forecast/smoothing') ?>">lissage</a> : mettre de côté chaque mois pour les grosses factures évite ces creux.</div>
<?php elseif ($tight): ?>
    <div class="alert alert-warning"><i class="bi bi-info-circle"></i> <?= count($tight) ?> mois avec plus de dépenses que de revenus : <?= e(implode(', ', array_map(fn ($r) => month_label($r['month']), $tight))) ?>. Le solde reste positif, mais anticipez-les avec le <a href="<?= url('forecast/smoothing') ?>">lissage</a>.</div>
<?php endif; ?>

<div class="card mb-3"><div class="card-body"><div class="chart-box"><canvas data-chart="<?= e(json_encode($chart)) ?>"></canvas></div></div></div>

<div class="card"><div class="table-responsive">
<table class="table mb-0">
    <thead><tr><th>Mois</th><th class="text-end">Revenus</th><th class="text-end">Dépenses</th><th class="text-end">Dettes</th><th class="text-end">Solde du mois</th><th class="text-end">Solde prévu</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($m as $i => $r): ?>
        <tr class="<?= $r['balance'] < 0 ? 'table-danger' : ($r['net'] < 0 ? 'table-warning' : '') ?>">
            <td><?= e(month_label($r['month'])) ?><?= $i === 0 ? ' <small class="text-muted">(reste du mois)</small>' : '' ?></td>
            <td class="text-end amount amount-income"><?= money($r['income'], false) ?></td>
            <td class="text-end amount amount-expense"><?= money($r['expense'], false) ?></td>
            <td class="text-end amount"><?= $r['debt'] ? money($r['debt'], false) : '' ?></td>
            <td class="text-end amount fw-semibold <?= $r['net'] < 0 ? 'amount-expense' : 'amount-income' ?>"><?= money($r['net'], false) ?></td>
            <td class="text-end amount fw-semibold"><?= money($r['balance'], false) ?></td>
            <td class="text-end"><button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="collapse" data-bs-target="#d<?= $i ?>"><i class="bi bi-chevron-down"></i></button></td>
        </tr>
        <tr class="collapse" id="d<?= $i ?>"><td colspan="7" class="bg-light small">
            <div class="row">
                <div class="col-md-4"><strong>Revenus</strong><ul class="mb-0"><?php foreach ($r['incomes'] as $it): ?><li><?= e($it['name']) ?> : <?= money($it['amount']) ?></li><?php endforeach; ?></ul></div>
                <div class="col-md-5"><strong>Dépenses</strong><ul class="mb-0"><?php foreach ($r['expenses'] as $it): ?><li class="<?= $it['is_regular'] ? '' : 'fw-semibold' ?>"><?= e($it['name']) ?> : <?= money($it['amount']) ?></li><?php endforeach; ?></ul></div>
                <div class="col-md-3"><strong>Dettes</strong><ul class="mb-0"><?php foreach ($r['debts'] as $it): ?><li><?= e($it['name']) ?> : <?= money($it['amount']) ?></li><?php endforeach; ?></ul></div>
            </div>
        </td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div></div>
<p class="small text-muted mt-2">En gras : dépenses irrégulières (qui ne tombent pas tous les mois). Les dépenses courantes (alimentation, loisirs…) ne sont comptées que si vous les avez saisies comme postes prévus.</p>
