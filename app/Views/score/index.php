<?php
use App\Score;
[$label, $color] = Score::label($result['score']);
$score = $result['score'];
$rate = $result['savings']['rate'];
$historyChart = ['type' => 'line', 'data' => [
    'labels' => array_map(fn ($h) => month_label($h['month']), $history),
    'datasets' => [['label' => 'Score', 'data' => array_map(fn ($h) => (int) $h['score'], $history),
        'borderColor' => '#0d6efd', 'backgroundColor' => '#0d6efd', 'tension' => 0.2]]],
    'options' => ['scales' => ['y' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['display' => false]]]];
$rateChart = ['type' => 'bar', 'data' => [
    'labels' => array_map(fn ($r) => month_label($r['month']), $rates),
    'datasets' => [['label' => 'Taux d\'épargne (%)', 'data' => array_map(fn ($r) => $r['rate'] === null ? null : round($r['rate'], 1), $rates),
        'backgroundColor' => array_map(fn ($r) => ($r['rate'] ?? 0) < 0 ? '#dc3545' : '#198754', $rates)]]],
    'options' => ['plugins' => ['legend' => ['display' => false]]]];
$sorted = $result['criteria'];
usort($sorted, fn ($a, $b) => (($a['score'] ?? -1) / $a['max']) <=> (($b['score'] ?? -1) / $b['max']));
$priorities = array_slice(array_filter($sorted, fn ($c) => $c['score'] === null || $c['score'] < $c['max']), 0, 3);
?>
<h1 class="h3 mb-3">Score financier</h1>
<p class="text-muted">Une note sur 100 calculée à partir de vos vraies données : épargne, réserve, dettes, logement, budget, factures, prévisions, prévoyance et suivi. Elle se met à jour toute seule et son historique montre votre progression.</p>

<div class="row g-4 mb-4">
    <div class="col-lg-4"><div class="card h-100"><div class="card-body text-center">
        <div class="display-3 fw-bold text-<?= $color ?>"><?= $score ?? '—' ?><small class="fs-4 text-muted">/100</small></div>
        <span class="badge text-bg-<?= $color ?> fs-6"><?= e($label) ?></span>
        <?php if ($score !== null): ?><div class="progress mt-3" style="height: .8rem"><div class="progress-bar bg-<?= $color ?>" style="width: <?= $score ?>%"></div></div><?php endif; ?>
        <p class="small text-muted mt-2 mb-0"><?= $score === null
            ? 'Il faut au moins 3 critères évalués : importez vos relevés, saisissez votre plan annuel ou votre budget.'
            : 'Calculé sur ' . $result['evaluated'] . ' critère(s) sur 9' . ($result['evaluated'] < 9 ? ' : les autres attendent des données.' : '.') ?></p>
    </div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-body text-center">
        <div class="stat-label">Taux d'épargne (moyenne 3 mois)</div>
        <div class="display-5 fw-semibold <?= ($rate ?? 0) < 0 ? 'amount-expense' : 'amount-income' ?>"><?= $rate === null ? '—' : number_format($rate, 1, '.', "'") . ' %' ?></div>
        <?php if ($rate !== null): ?><p class="small text-muted mb-0"><?= money($result['savings']['income'] - $result['savings']['expense']) ?> mis de côté par mois en moyenne<br>sur <?= money($result['savings']['income']) ?> de revenus</p><?php endif; ?>
        <div class="chart-box mt-2" style="height: 140px"><canvas data-chart="<?= e(json_encode($rateChart)) ?>"></canvas></div>
    </div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-body">
        <h2 class="h6">Vos 3 priorités</h2>
        <?php if (!$priorities): ?><p class="small mb-0">Tout est au maximum, bravo ! 🎉</p><?php endif; ?>
        <ol class="small mb-0">
            <?php foreach ($priorities as $p): ?><li class="mb-2"><strong><?= e($p['label']) ?></strong> : <?= e($p['tip']) ?></li><?php endforeach; ?>
        </ol>
    </div></div></div>
</div>

<div class="card mb-4"><div class="table-responsive">
<table class="table mb-0">
    <thead><tr><th>Critère</th><th>Situation</th><th style="width: 25%">Points</th><th>Conseil</th></tr></thead>
    <tbody>
    <?php foreach ($result['criteria'] as $c): $pct = $c['score'] === null ? 0 : $c['score'] / $c['max'] * 100; ?>
        <tr class="<?= $c['score'] === null ? 'text-muted' : '' ?>">
            <td class="fw-semibold"><?= e($c['label']) ?></td>
            <td class="small"><?= e($c['value']) ?></td>
            <td><?php if ($c['score'] === null): ?><span class="small">non évalué</span><?php else: ?>
                <div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1"><div class="progress-bar <?= $pct >= 80 ? 'bg-success' : ($pct >= 40 ? 'bg-warning' : 'bg-danger') ?>" style="width: <?= round($pct) ?>%"></div></div>
                <span class="small text-nowrap"><?= $c['score'] ?> / <?= $c['max'] ?></span></div><?php endif; ?></td>
            <td class="small text-muted"><?= e($c['tip']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div></div>

<?php if (count($history) > 1): ?>
<div class="card"><div class="card-body">
    <h2 class="h6">Progression</h2>
    <div class="chart-box" style="height: 220px"><canvas data-chart="<?= e(json_encode($historyChart)) ?>"></canvas></div>
</div></div>
<?php else: ?>
    <p class="small text-muted">L'historique de votre score se construit mois après mois : revenez le mois prochain pour voir votre progression.</p>
<?php endif; ?>
