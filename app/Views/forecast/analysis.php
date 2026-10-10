<?php
$chart = ['type' => 'bar', 'data' => [
    'labels' => array_map(fn ($h) => month_label($h['month']), $history),
    'datasets' => [
        ['label' => 'Revenus', 'data' => array_column($history, 'income'), 'backgroundColor' => '#198754'],
        ['label' => 'Dépenses', 'data' => array_column($history, 'expense'), 'backgroundColor' => '#dc3545'],
    ]]];
$tot = fn (string $k) => array_sum(array_column($rows, $k));
$diffBadge = function (float $avg, float $ref): string {
    if ($ref <= 0 || $avg <= 0) { return ''; }
    $pct = ($avg - $ref) / $ref * 100;
    if (abs($pct) < 10) { return ''; }
    return $pct > 0 ? '<span class="badge text-bg-danger">+' . round($pct) . ' %</span>' : '<span class="badge text-bg-success">' . round($pct) . ' %</span>';
};
?>
<h1 class="h3 mb-3">Analyse des dépenses</h1>
<p class="text-muted">Moyennes mensuelles calculées sur les derniers mois <strong>complets</strong> (le mois en cours est affiché à part). Elles montrent vos dépenses réelles, mois chers et mois calmes confondus.</p>
<?php if ($monthsOfHistory < 3): ?>
    <div class="alert alert-info">Vous avez <?= $monthsOfHistory ?> mois complet(s) d'historique. Importez vos relevés UBS / BCF des 6 à 12 derniers mois pour des moyennes fiables.</div>
<?php endif; ?>

<div class="card mb-4"><div class="card-body"><div class="chart-box"><canvas data-chart="<?= e(json_encode($chart)) ?>"></canvas></div></div></div>

<div class="card mb-3"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead><tr><th>Catégorie</th><th class="text-end">Moy. 3 mois</th><th class="text-end">Moy. 6 mois</th><th class="text-end">Moy. 12 mois</th><th class="text-end">Prévu / mois</th><th class="text-end">Mois en cours</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><span class="cat-dot" style="background: <?= e($r['category']['color']) ?>"></span><?= e($r['category']['name']) ?></td>
            <td class="text-end amount"><?= money($r['a3'], false) ?> <?= $diffBadge($r['a3'], $r['a12']) ?></td>
            <td class="text-end amount fw-semibold"><?= money($r['a6'], false) ?></td>
            <td class="text-end amount"><?= money($r['a12'], false) ?></td>
            <td class="text-end amount text-muted"><?= $r['plan'] ? money($r['plan'], false) : '—' ?></td>
            <td class="text-end amount"><?= money($r['current'], false) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot class="fw-semibold">
        <tr><td>Total dépenses</td><td class="text-end amount"><?= money($tot('a3'), false) ?></td><td class="text-end amount"><?= money($tot('a6'), false) ?></td><td class="text-end amount"><?= money($tot('a12'), false) ?></td><td class="text-end amount"><?= money($tot('plan'), false) ?></td><td class="text-end amount"><?= money($tot('current'), false) ?></td></tr>
        <tr class="amount-income"><td>Revenus moyens</td><td class="text-end amount"><?= money($income[3], false) ?></td><td class="text-end amount"><?= money($income[6], false) ?></td><td class="text-end amount"><?= money($income[12], false) ?></td><td></td><td></td></tr>
        <tr><td>Reste moyen par mois</td><td class="text-end amount"><?= money($income[3] - $tot('a3'), false) ?></td><td class="text-end amount"><?= money($income[6] - $tot('a6'), false) ?></td><td class="text-end amount"><?= money($income[12] - $tot('a12'), false) ?></td><td></td><td></td></tr>
    </tfoot>
</table>
</div></div>
<p class="small text-muted">Badge : la moyenne sur 3 mois s'écarte de plus de 10 % de la moyenne sur 12 mois (dépenses en hausse en rouge, en baisse en vert).</p>

<div class="card"><div class="card-body">
    <h2 class="h6">Utiliser ces moyennes comme budget</h2>
    <form method="post" action="<?= url('forecast/budget_from_average') ?>" class="d-flex flex-wrap gap-2 align-items-center">
        <?= csrf_field() ?>
        <span>Remplir le budget de</span>
        <input type="month" name="month" class="form-control form-control-sm w-auto" value="<?= date('Y-m') ?>">
        <span>avec la moyenne sur</span>
        <select name="period" class="form-select form-select-sm w-auto"><option value="3">3 mois</option><option value="6" selected>6 mois</option><option value="12">12 mois</option></select>
        <button class="btn btn-sm btn-primary">Remplir</button>
    </form>
    <hr>
    <h2 class="h6">Compléter le plan annuel avec ces moyennes</h2>
    <p class="small text-muted">Crée un poste prévu mensuel pour chaque catégorie qui n'en a pas encore (alimentation, loisirs, habillement…), afin que les prévisions tiennent compte de vos dépenses courantes réelles.</p>
    <form method="post" action="<?= url('forecast/plan_from_average') ?>" class="d-flex flex-wrap gap-2 align-items-center">
        <?= csrf_field() ?>
        <span>Moyenne sur</span>
        <select name="period" class="form-select form-select-sm w-auto"><option value="3">3 mois</option><option value="6" selected>6 mois</option><option value="12">12 mois</option></select>
        <button class="btn btn-sm btn-outline-primary">Ajouter au plan</button>
    </form>
</div></div>
