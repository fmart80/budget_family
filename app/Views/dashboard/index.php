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
    'labels' => array_map(fn ($h) => substr(month_label($h['month']), 0, 3) . ' ' . substr($h['month'], 2, 2), $history),
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
