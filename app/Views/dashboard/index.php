<?php
$labels = []; $values = []; $colors = [];
foreach ($spending as $cid => $total) {
    $labels[] = $categories[$cid]['name'] ?? 'Sans catégorie';
    $values[] = round($total, 2);
    $colors[] = $categories[$cid]['color'] ?? '#ced4da';
}
$pie = ['type' => 'doughnut', 'data' => ['labels' => $labels, 'datasets' => [['data' => $values, 'backgroundColor' => $colors]]],
    'options' => ['plugins' => ['legend' => ['position' => 'right']]]];
$bar = ['type' => 'bar', 'data' => [
    'labels' => array_map(fn ($h) => mb_substr(month_label($h['month']), 0, 3) . ' ' . substr($h['month'], 2, 2), $history),
    'datasets' => [
        ['label' => 'Revenus', 'data' => array_column($history, 'income'), 'backgroundColor' => '#198754'],
        ['label' => 'Dépenses', 'data' => array_column($history, 'expense'), 'backgroundColor' => '#dc3545'],
    ]]];
$today = date('Y-m-d');
$budgetUsed = $budgetTotal > 0 ? min(100, $totals['expense'] / $budgetTotal * 100) : 0;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h3 mb-0">Bonjour <?= e(current_user()['name']) ?> 👋</h1>
    <div class="d-flex gap-2">
        <a href="<?= url('transactions/edit', ['type' => 'expense']) ?>" class="btn btn-danger"><i class="bi bi-dash-lg"></i> Dépense</a>
        <a href="<?= url('transactions/edit', ['type' => 'income']) ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Revenu</a>
        <a href="<?= url('import') ?>" class="btn btn-outline-primary"><i class="bi bi-upload"></i> Importer</a>
    </div>
</div>

<?php if (!$accounts): ?>
    <div class="alert alert-info">Pour démarrer : <a href="<?= url('accounts/edit') ?>">créez vos comptes</a> (UBS, BCF, espèces…), puis importez vos relevés ou saisissez vos transactions.</div>
<?php endif; ?>
<?php if ($uncategorized): ?>
    <div class="alert alert-warning py-2"><a href="<?= url('transactions', ['month' => '', 'category' => 'none']) ?>"><?= $uncategorized ?> transaction(s)</a> à classer dans une catégorie.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="stat-label">Avoirs (tous comptes)</div><div class="stat-value"><?= money($netWorth) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="stat-label">Revenus <?= e(month_label($month)) ?></div><div class="stat-value amount-income"><?= money($totals['income']) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="stat-label">Dépenses <?= e(month_label($month)) ?></div><div class="stat-value amount-expense"><?= money($totals['expense']) ?></div>
        <?php if ($budgetTotal > 0): ?><div class="progress mt-2"><div class="progress-bar <?= $budgetUsed >= 100 ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= round($budgetUsed) ?>%"></div></div>
        <small class="text-muted"><?= round($budgetUsed) ?> % du budget (<?= money($budgetTotal) ?>)</small><?php endif; ?></div></div>
    <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="stat-label">Dettes restantes</div><div class="stat-value"><?= money($debtRemaining) ?></div></div></div>
</div>

<?php [$scoreLabel, $scoreColor] = \App\Score::label($score['score']); ?>
<div class="row g-3 mb-4">
    <div class="col-md-6"><a href="<?= url('score') ?>" class="card card-body h-100 text-reset text-decoration-none">
        <div class="d-flex align-items-center gap-3">
            <div class="display-6 fw-bold text-<?= $scoreColor ?>"><?= $score['score'] ?? '—' ?><small class="fs-6 text-muted">/100</small></div>
            <div class="flex-grow-1"><div class="stat-label">Score financier</div><span class="badge text-bg-<?= $scoreColor ?>"><?= e($scoreLabel) ?></span>
                <?php if ($score['score'] !== null): ?><div class="progress mt-2"><div class="progress-bar bg-<?= $scoreColor ?>" style="width: <?= $score['score'] ?>%"></div></div><?php endif; ?></div>
            <i class="bi bi-chevron-right text-muted"></i>
        </div></a></div>
    <div class="col-md-6"><div class="card card-body h-100">
        <div class="stat-label">Taux d'épargne</div>
        <div class="d-flex gap-4 align-items-baseline">
            <div><span class="stat-value <?= ($monthRate ?? 0) < 0 ? 'amount-expense' : 'amount-income' ?>"><?= $monthRate === null ? '—' : number_format($monthRate, 1, '.', "'") . ' %' ?></span><div class="small text-muted"><?= e(month_label($month)) ?> (en cours)</div></div>
            <div><span class="stat-value fs-5"><?= $score['savings']['rate'] === null ? '—' : number_format($score['savings']['rate'], 1, '.', "'") . ' %' ?></span><div class="small text-muted">moyenne 3 derniers mois</div></div>
        </div>
    </div></div>
</div>

<?php if ($forecast): ?>
<div class="card mb-4"><div class="card-body">
    <div class="d-flex justify-content-between"><h2 class="h6">Prochains mois</h2><a href="<?= url('forecast') ?>" class="small">Prévisions complètes</a></div>
    <div class="row g-2 text-center">
    <?php foreach ($forecast['months'] as $i => $r): ?>
        <div class="col-6 col-md-3"><div class="p-2 rounded <?= $r['balance'] < 0 ? 'bg-danger-subtle' : ($r['net'] < 0 ? 'bg-warning-subtle' : 'bg-light') ?>">
            <div class="small text-muted"><?= e(month_label($r['month'])) ?><?= $i === 0 ? ' (reste)' : '' ?></div>
            <div class="fw-semibold amount <?= $r['net'] < 0 ? 'amount-expense' : 'amount-income' ?>"><?= ($r['net'] >= 0 ? '+' : '') . money($r['net'], false) ?></div>
            <div class="small amount">solde prévu <?= money($r['balance'], false) ?></div>
        </div></div>
    <?php endforeach; ?>
    </div>
    <?php if ($provision > 0): ?><p class="small text-muted mb-0 mt-2"><i class="bi bi-safe"></i> À mettre de côté chaque mois pour les grosses factures : <strong><?= money($provision) ?></strong> — <a href="<?= url('forecast/smoothing') ?>">lissage</a>.</p><?php endif; ?>
</div></div>
<?php else: ?>
    <div class="alert alert-light border"><i class="bi bi-lightbulb"></i> Pour ne plus être pris de court en fin de mois, saisissez votre <a href="<?= url('planned') ?>">plan annuel</a> (salaires, 13ᵉ, assurances, impôts…) : l'application calculera vos prévisions et le montant à mettre de côté chaque mois. <a href="<?= url('guide') ?>">Guide</a></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-lg-7"><div class="card h-100"><div class="card-body">
        <h2 class="h6">Revenus et dépenses — 12 derniers mois</h2>
        <div class="chart-box"><canvas data-chart="<?= e(json_encode($bar)) ?>"></canvas></div>
    </div></div></div>
    <div class="col-lg-5"><div class="card h-100"><div class="card-body">
        <h2 class="h6">Dépenses du mois par catégorie</h2>
        <?php if ($values): ?><div class="chart-box"><canvas data-chart="<?= e(json_encode($pie)) ?>"></canvas></div>
        <?php else: ?><p class="text-muted small">Aucune dépense ce mois-ci.</p><?php endif; ?>
    </div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-4"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between"><h2 class="h6">Factures à venir</h2><a href="<?= url('bills') ?>" class="small">Toutes</a></div>
        <?php if (!$bills): ?><p class="text-muted small mb-0">Rien à payer dans les 30 prochains jours.</p><?php endif; ?>
        <ul class="list-group list-group-flush">
        <?php foreach ($bills as $b): ?>
            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                <span><?= e($b['name']) ?><br><small class="<?= $b['due_date'] < $today ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $b['due_date'] < $today ? 'En retard · ' : '' ?><?= date_ch($b['due_date']) ?></small></span>
                <span class="text-end"><span class="amount"><?= money($b['amount']) ?></span><br>
                    <a href="<?= url('bills/pay', ['id' => $b['id']]) ?>" class="small">payer</a></span>
            </li>
        <?php endforeach; ?>
        </ul>
    </div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between"><h2 class="h6">Comptes</h2><a href="<?= url('accounts') ?>" class="small">Gérer</a></div>
        <ul class="list-group list-group-flush">
        <?php foreach ($accounts as $a): $bal = $balances[$a['id']] ?? 0; ?>
            <li class="list-group-item px-0 d-flex justify-content-between">
                <a href="<?= url('transactions', ['account' => $a['id'], 'month' => '']) ?>" class="text-reset"><?= e($a['name']) ?></a>
                <span class="amount <?= $bal < 0 ? 'amount-expense' : '' ?>"><?= money($bal) ?></span>
            </li>
        <?php endforeach; ?>
        </ul>
    </div></div></div>
    <div class="col-lg-4"><div class="card h-100"><div class="card-body">
        <div class="d-flex justify-content-between"><h2 class="h6">Objectifs d'épargne</h2><a href="<?= url('savings') ?>" class="small">Tous</a></div>
        <?php if (!$goals): ?><p class="text-muted small mb-0"><a href="<?= url('savings/edit') ?>">Créer un objectif</a> (fonds d'urgence, vacances…)</p><?php endif; ?>
        <?php foreach ($goals as $g): $s = $saved[$g['id']] ?? 0; $pct = $g['target_amount'] > 0 ? min(100, $s / $g['target_amount'] * 100) : 0; ?>
            <div class="mb-3">
                <div class="d-flex justify-content-between small"><a href="<?= url('savings/show', ['id' => $g['id']]) ?>" class="text-reset"><?= e($g['name']) ?></a><span class="amount"><?= money($s, false) ?> / <?= money($g['target_amount'], false) ?></span></div>
                <div class="progress"><div class="progress-bar" style="width: <?= round($pct) ?>%; background: <?= e($g['color']) ?>"></div></div>
            </div>
        <?php endforeach; ?>
    </div></div></div>
</div>

<?php if ($subs): $subMonthly = array_sum(array_column($subs, 'monthly')); $subCard = array_sum(array_map(fn ($x) => $x['on_card'] ? $x['monthly'] : 0, $subs)); ?>
<div class="card mt-4"><div class="card-body">
    <div class="d-flex justify-content-between"><h2 class="h6"><i class="bi bi-collection-play"></i> Abonnements</h2><a href="<?= url('subscriptions') ?>" class="small">Tableau de bord des abonnements</a></div>
    <div class="row g-3 align-items-center">
        <div class="col-md-4">
            <div class="stat-value"><?= money($subMonthly) ?> <small class="fs-6 text-muted">/ mois</small></div>
            <div class="small text-muted"><?= count($subs) ?> actif(s) · <?= money($subMonthly * 12) ?> par an<?= $subCard > 0 ? ' · <span class="text-warning-emphasis">' . money($subCard) . '/mois sur la carte de crédit</span>' : '' ?></div>
        </div>
        <div class="col-md-8"><div class="d-flex flex-wrap gap-2">
            <?php foreach (array_slice($subs, 0, 6) as $x): ?>
                <span class="badge text-bg-light border fw-normal p-2"><?= e($x['name']) ?> · <?= money($x['amount'], false) ?> · <?= date('d.m', strtotime($x['next_date'])) ?></span>
            <?php endforeach; ?>
        </div></div>
    </div>
</div></div>
<?php endif; ?>
