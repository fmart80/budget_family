<?php
$prev = date('Y-m', strtotime($month . '-01 -1 month'));
$next = date('Y-m', strtotime($month . '-01 +1 month'));
$first = strtotime($month . '-01');
$days = (int) date('t', $first);
$offset = (int) date('N', $first) - 1;
$today = date('Y-m-d');
$kinds = ['bill' => ['Facture', 'bi-receipt'], 'subscription' => ['Abonnement', 'bi-collection-play'], 'planned' => ['Prévu', 'bi-list-check'], 'debt' => ['Dette', 'bi-credit-card-2-back']];
$weekdays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
$balance = $startBalance;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Calendrier — <?= e(month_label($month)) ?></h1>
    <div class="btn-group">
        <a class="btn btn-outline-secondary" href="<?= url('calendar', ['month' => $prev]) ?>"><i class="bi bi-chevron-left"></i></a>
        <a class="btn btn-outline-secondary" href="<?= url('calendar') ?>">Mois actuel</a>
        <a class="btn btn-outline-secondary" href="<?= url('calendar', ['month' => $next]) ?>"><i class="bi bi-chevron-right"></i></a>
    </div>
</div>
<p class="text-muted small">Factures, abonnements, postes prévus (salaires, loyer…) et mensualités de dettes, jour par jour. Idéal pour vérifier qu'aucun gros prélèvement ne tombe avant le salaire.</p>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Rentrées prévues</div><div class="stat-value amount-income"><?= money($income) ?></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Prélèvements prévus</div><div class="stat-value amount-expense"><?= money($expense) ?></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Solde du mois</div><div class="stat-value <?= $income - $expense < 0 ? 'amount-expense' : '' ?>"><?= money($income - $expense) ?></div></div></div>
</div>

<div class="card mb-4 d-none d-md-block"><div class="card-body p-2">
    <div class="cal-grid">
        <?php foreach ($weekdays as $w): ?><div class="cal-head"><?= $w ?></div><?php endforeach; ?>
        <?php for ($i = 0; $i < $offset; $i++): ?><div class="cal-cell cal-empty"></div><?php endfor; ?>
        <?php for ($d = 1; $d <= $days; $d++): $date = sprintf('%s-%02d', $month, $d); $list = $byDay[$date] ?? []; ?>
            <div class="cal-cell<?= $date === $today ? ' cal-today' : '' ?><?= $date < $today ? ' cal-past' : '' ?>">
                <div class="cal-day"><?= $d ?></div>
                <?php foreach (array_slice($list, 0, 4) as $e): ?>
                    <a href="<?= e($e['url'] ?? '#') ?>" class="cal-event cal-<?= $e['type'] ?><?= ($e['status'] ?? '') === 'paid' ? ' cal-paid' : '' ?>" title="<?= e($kinds[$e['kind']][0] . ' : ' . $e['name'] . ' · ' . money($e['amount'])) ?>">
                        <i class="bi <?= $kinds[$e['kind']][1] ?>"></i> <?= e(mb_strimwidth($e['name'], 0, 16, '…')) ?> <span class="amount"><?= money($e['amount'], false) ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (count($list) > 4): ?><div class="small text-muted">+<?= count($list) - 4 ?> autre(s)</div><?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
</div></div>

<div class="card"><div class="card-header bg-white"><strong>Jour par jour</strong>
    <?php if ($balance !== null): ?><span class="small text-muted ms-2">solde indicatif des comptes courants<?= $month === date('Y-m') ? ' à partir d\'aujourd\'hui' : '' ?></span><?php endif; ?></div>
<?php if (!$events): ?>
    <div class="card-body text-muted">Rien de prévu ce mois-ci. Ajoutez vos <a href="<?= url('planned') ?>">postes prévus</a>, <a href="<?= url('bills') ?>">factures</a> et <a href="<?= url('subscriptions') ?>">abonnements</a>.</div>
<?php else: ?>
<div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Date</th><th>Quoi</th><th>Type</th><th class="text-end">Montant</th><?= $balance !== null ? '<th class="text-end">Solde prévu</th>' : '' ?></tr></thead>
    <tbody>
    <?php foreach ($events as $e):
        $counts = $balance !== null && ($month !== date('Y-m') || $e['date'] >= $today);
        if ($counts) { $balance += $e['type'] === 'income' ? $e['amount'] : -$e['amount']; } ?>
        <tr class="<?= $e['date'] < $today ? 'text-muted' : '' ?><?= $e['date'] === $today ? ' table-primary' : '' ?>">
            <td class="text-nowrap"><?= date_ch($e['date']) ?></td>
            <td><a href="<?= e($e['url'] ?? '#') ?>" class="text-reset"><?= e($e['name']) ?></a><?= ($e['status'] ?? '') === 'paid' ? ' <span class="badge text-bg-success">payée</span>' : '' ?></td>
            <td class="small"><i class="bi <?= $kinds[$e['kind']][1] ?>"></i> <?= $kinds[$e['kind']][0] ?></td>
            <td class="text-end amount amount-<?= $e['type'] ?>"><?= $e['type'] === 'income' ? '+' : '−' ?> <?= money($e['amount'], false) ?></td>
            <?php if ($balance !== null): ?><td class="text-end amount <?= $counts && $balance < 0 ? 'amount-expense fw-semibold' : '' ?>"><?= $counts ? money($balance, false) : '' ?></td><?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
</div>
