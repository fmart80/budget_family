<?php
$prev = date('Y-m', strtotime($month . '-01 -1 month'));
$next = date('Y-m', strtotime($month . '-01 +1 month'));
$first = strtotime($month . '-01');
$days = (int) date('t', $first);
$offset = (int) date('N', $first) - 1;
$today = date('Y-m-d');
$kinds = ['bill' => ['Facture', 'bi-receipt'], 'subscription' => ['Abonnement', 'bi-collection-play'], 'planned' => ['Prévu', 'bi-list-check'], 'debt' => ['Dette', 'bi-credit-card-2-back']];
$weekdays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$short = fn (float $v): string => number_format(abs($v), 0, '.', "'");

// Totaux par jour et solde prévu en fin de journée (mois en cours : à partir d'aujourd'hui).
$daily = [];
$balance = $startBalance;
$maxOut = 0.0;
for ($d = 1; $d <= $days; $d++) {
    $date = sprintf('%s-%02d', $month, $d);
    $in = $out = 0.0;
    foreach ($byDay[$date] ?? [] as $e) {
        $e['type'] === 'income' ? $in += $e['amount'] : $out += $e['amount'];
    }
    $counts = $balance !== null && ($month !== date('Y-m') || $date >= $today);
    if ($counts) {
        $balance += $in - $out;
    }
    $daily[$date] = ['in' => $in, 'out' => $out, 'balance' => $counts ? $balance : null];
    $maxOut = max($maxOut, $out);
}
$heat = function (float $out) use ($maxOut): int {
    if ($out <= 0 || $maxOut <= 0) {
        return 0;
    }
    return max(1, (int) ceil($out / $maxOut * 4));
};

$chartDays = array_filter($daily, fn ($v) => $v['balance'] !== null);
$lowest = $lowestDate = null;
foreach ($chartDays as $date => $v) {
    if ($lowest === null || $v['balance'] < $lowest) {
        [$lowest, $lowestDate] = [$v['balance'], $date];
    }
}
$chart = ['type' => 'line', 'data' => [
    'labels' => array_map(fn ($d) => (int) substr($d, 8, 2) . '.' , array_keys($chartDays)),
    'datasets' => [[
        'label' => 'Solde prévu', 'data' => array_map(fn ($v) => round($v['balance'], 2), array_values($chartDays)),
        'stepped' => true, 'fill' => true, 'borderColor' => '#0d6efd', 'backgroundColor' => 'rgba(13,110,253,.08)',
        'pointRadius' => array_map(fn ($v) => $v['in'] > 0 || $v['out'] > 0 ? 3 : 0, array_values($chartDays)),
        'pointBackgroundColor' => array_map(fn ($v) => $v['in'] >= $v['out'] ? '#198754' : '#dc3545', array_values($chartDays)),
    ]]],
    'options' => ['plugins' => ['legend' => ['display' => false]], 'scales' => ['x' => ['ticks' => ['maxTicksLimit' => 16]]]]];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Calendrier — <?= e(month_label($month)) ?></h1>
    <div class="btn-group">
        <a class="btn btn-outline-secondary" href="<?= url('calendar', ['month' => $prev]) ?>" aria-label="Mois précédent"><i class="bi bi-chevron-left"></i></a>
        <a class="btn btn-outline-secondary" href="<?= url('calendar') ?>">Aujourd'hui</a>
        <a class="btn btn-outline-secondary" href="<?= url('calendar', ['month' => $next]) ?>" aria-label="Mois suivant"><i class="bi bi-chevron-right"></i></a>
    </div>
</div>

<div class="row g-2 g-md-3 mb-3">
    <div class="col-4"><div class="card card-body p-2 p-md-3"><div class="stat-label">Rentrées</div><div class="fw-semibold fs-5 amount-income amount"><?= money($income, false) ?></div></div></div>
    <div class="col-4"><div class="card card-body p-2 p-md-3"><div class="stat-label">Sorties</div><div class="fw-semibold fs-5 amount-expense amount"><?= money($expense, false) ?></div></div></div>
    <div class="col-4"><div class="card card-body p-2 p-md-3"><div class="stat-label">Solde du mois</div><div class="fw-semibold fs-5 amount <?= $income - $expense < 0 ? 'amount-expense' : 'amount-income' ?>"><?= ($income - $expense >= 0 ? '+' : '−') . $short($income - $expense) ?></div></div></div>
</div>

<div class="card mb-3"><div class="card-body p-2 p-md-3">
    <div class="cal-grid">
        <?php foreach ($weekdays as $w): ?><div class="cal-head"><?= $w ?></div><?php endforeach; ?>
        <?php for ($i = 0; $i < $offset; $i++): ?><div class="cal-cell cal-empty"></div><?php endfor; ?>
        <?php for ($d = 1; $d <= $days; $d++):
            $date = sprintf('%s-%02d', $month, $d);
            $list = $byDay[$date] ?? [];
            $day = $daily[$date];
            $classes = 'cal-cell' . ($date === $today ? ' cal-today' : '') . ($date < $today ? ' cal-past' : '')
                . ($day['in'] > 0 ? ' cal-payday' : ' cal-heat-' . $heat($day['out'])) . ($list ? ' cal-has' : '');
        ?>
            <button type="button" class="<?= $classes ?>" data-day="<?= $date ?>" <?= $list ? '' : 'disabled' ?>
                    aria-label="<?= e(date_ch($date)) ?> : <?= count($list) ?> opération(s)">
                <span class="cal-day"><?= $d ?></span>
                <span class="cal-dots d-md-none">
                    <?php foreach (array_slice($list, 0, 4) as $e): ?><i class="cal-dot cal-dot-<?= $e['type'] ?>"></i><?php endforeach; ?>
                </span>
                <span class="cal-chips d-none d-md-block">
                    <?php foreach (array_slice($list, 0, 3) as $e): ?>
                        <span class="cal-event cal-<?= $e['type'] ?><?= ($e['status'] ?? '') === 'paid' ? ' cal-paid' : '' ?>"><i class="bi <?= $kinds[$e['kind']][1] ?>"></i> <?= e(mb_strimwidth($e['name'], 0, 14, '…')) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($list) > 3): ?><span class="cal-more">+<?= count($list) - 3 ?></span><?php endif; ?>
                </span>
                <?php if ($day['in'] > 0 || $day['out'] > 0): $net = $day['in'] - $day['out']; ?>
                    <span class="cal-total <?= $net >= 0 ? 'amount-income' : 'amount-expense' ?>"><?= ($net >= 0 ? '+' : '−') . $short($net) ?></span>
                <?php endif; ?>
            </button>
        <?php endfor; ?>
    </div>
    <div class="d-flex flex-wrap gap-3 small text-muted mt-2 px-1">
        <span><i class="cal-dot cal-dot-income"></i> rentrée</span>
        <span><i class="cal-dot cal-dot-expense"></i> sortie</span>
        <span><span class="cal-legend cal-heat-2"></span><span class="cal-legend cal-heat-4"></span> plus la case est rouge, plus il sort d'argent ce jour-là</span>
        <span class="d-md-none">· touchez un jour pour le détail</span>
    </div>
</div></div>

<div class="card mb-3 d-none" id="cal-detail"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0" id="cal-detail-title"></h2>
        <button type="button" class="btn-close" id="cal-detail-close" aria-label="Fermer"></button>
    </div>
    <div id="cal-detail-body"></div>
</div></div>

<?php foreach ($byDay as $date => $list): ?>
<template id="cal-day-<?= $date ?>" data-title="<?= e(day_label($date)) ?>">
    <ul class="list-group list-group-flush">
    <?php foreach ($list as $e): ?>
        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
            <span><i class="bi <?= $kinds[$e['kind']][1] ?> text-muted"></i> <a href="<?= e($e['url'] ?? '#') ?>" class="text-reset"><?= e($e['name']) ?></a>
                <small class="text-muted"><?= $kinds[$e['kind']][0] ?><?= ($e['status'] ?? '') === 'paid' ? ' · payée' : '' ?></small></span>
            <span class="amount amount-<?= $e['type'] ?> text-nowrap"><?= $e['type'] === 'income' ? '+' : '−' ?> <?= money($e['amount'], false) ?></span>
        </li>
    <?php endforeach; ?>
    </ul>
    <?php if ($daily[$date]['balance'] !== null): ?><div class="small text-muted mt-2">Solde prévu en fin de journée : <strong class="<?= $daily[$date]['balance'] < 0 ? 'text-danger' : '' ?>"><?= money($daily[$date]['balance']) ?></strong></div><?php endif; ?>
</template>
<?php endforeach; ?>

<?php if ($chartDays): ?>
<div class="card mb-3"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
        <h2 class="h6 mb-0">Solde prévu jour par jour</h2>
        <?php if ($lowest !== null): ?><span class="small">Point le plus bas : <strong class="<?= $lowest < 0 ? 'text-danger' : '' ?>"><?= money($lowest) ?></strong> le <?= date_ch($lowestDate) ?></span><?php endif; ?>
    </div>
    <div class="chart-box" style="height: 220px"><canvas data-chart="<?= e(json_encode($chart)) ?>"></canvas></div>
    <p class="small text-muted mb-0">Comptes courants, <?= $month === date('Y-m') ? "à partir d'aujourd'hui" : 'à partir du solde prévu en début de mois' ?>. Indicatif : basé sur vos postes prévus, factures, abonnements et dettes.</p>
</div></div>
<?php endif; ?>

<?php if (!$events): ?>
    <div class="alert alert-info">Rien de prévu ce mois-ci. Ajoutez vos <a href="<?= url('planned') ?>">postes prévus</a>, <a href="<?= url('bills') ?>">factures</a> et <a href="<?= url('subscriptions') ?>">abonnements</a>.</div>
<?php else: ?>
<details class="card"><summary class="card-header bg-white fw-semibold">Liste détaillée (<?= count($events) ?> opérations)</summary>
<div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Date</th><th>Quoi</th><th class="d-none d-sm-table-cell">Type</th><th class="text-end">Montant</th></tr></thead>
    <tbody>
    <?php foreach ($events as $e): ?>
        <tr class="<?= $e['date'] < $today ? 'text-muted' : '' ?><?= $e['date'] === $today ? ' table-primary' : '' ?>">
            <td class="text-nowrap"><?= date('d.m', strtotime($e['date'])) ?></td>
            <td><a href="<?= e($e['url'] ?? '#') ?>" class="text-reset"><?= e($e['name']) ?></a><?= ($e['status'] ?? '') === 'paid' ? ' <span class="badge text-bg-success">payée</span>' : '' ?></td>
            <td class="small d-none d-sm-table-cell"><i class="bi <?= $kinds[$e['kind']][1] ?>"></i> <?= $kinds[$e['kind']][0] ?></td>
            <td class="text-end amount amount-<?= $e['type'] ?>"><?= $e['type'] === 'income' ? '+' : '−' ?> <?= money($e['amount'], false) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
</details>
<?php endif; ?>
