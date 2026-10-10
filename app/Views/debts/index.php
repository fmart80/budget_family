<?php
use App\Finance;
$totalRemaining = 0; $totalMonthly = 0;
foreach ($debts as $d) { if (!$d['is_closed']) { $totalRemaining += $remaining[$d['id']] ?? 0; $totalMonthly += $d['monthly_payment']; } }
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Dettes</h1>
    <a href="<?= url('debts/edit') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle dette</a>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card card-body"><div class="stat-label">Total restant dû</div><div class="stat-value amount-expense"><?= money($totalRemaining) ?></div></div></div>
    <div class="col-md-6"><div class="card card-body"><div class="stat-label">Mensualités totales</div><div class="stat-value"><?= money($totalMonthly) ?></div></div></div>
</div>

<?php if (!$debts): ?>
    <div class="alert alert-info">Aucune dette enregistrée (crédit privé, leasing, prêt familial, carte de crédit…).</div>
<?php else: ?>
<div class="row g-3 mb-4">
<?php foreach ($debts as $d):
    $rest = $remaining[$d['id']] ?? 0;
    $pct = $d['initial_amount'] > 0 ? (1 - $rest / $d['initial_amount']) * 100 : 100;
    $months = Finance::monthsToPayOff($rest, (float) $d['interest_rate'], (float) $d['monthly_payment']);
?>
    <div class="col-md-6 col-xl-4">
        <a href="<?= url('debts/show', ['id' => $d['id']]) ?>" class="card h-100 text-reset text-decoration-none <?= $d['is_closed'] ? 'opacity-50' : '' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <h2 class="h6 mb-1"><?= e($d['name']) ?></h2>
                    <?php if ($d['is_closed']): ?><span class="badge text-bg-success">remboursée</span><?php else: ?><span class="badge text-bg-light"><?= e($d['interest_rate']) ?> %</span><?php endif; ?>
                </div>
                <div class="small text-muted mb-2"><?= e($d['creditor']) ?></div>
                <div class="d-flex justify-content-between small"><span>Restant</span><strong class="amount"><?= money($rest) ?></strong></div>
                <div class="progress my-2"><div class="progress-bar bg-success" style="width: <?= round(max(0, min(100, $pct))) ?>%"></div></div>
                <div class="d-flex justify-content-between small text-muted">
                    <span><?= round(max(0, $pct)) ?> % remboursé</span>
                    <span><?php if (!$d['is_closed']): ?><?= $months === null ? '<span class="text-danger">mensualité insuffisante</span>' : 'fin estimée : ' . e(month_label(date('Y-m', strtotime("+$months months")))) ?><?php endif; ?></span>
                </div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>

<?php if (count($avalanche) > 1): ?>
<div class="card"><div class="card-body">
    <h2 class="h6">Stratégie de remboursement</h2>
    <p class="small text-muted">Payez le minimum sur toutes les dettes et mettez tout montant supplémentaire sur la première de la liste. Une fois remboursée, reportez sa mensualité sur la suivante.</p>
    <div class="row">
        <div class="col-md-6"><strong class="small">Avalanche (taux le plus élevé d'abord, économise le plus d'intérêts)</strong>
            <ol class="small"><?php foreach ($avalanche as $d): ?><li><?= e($d['name']) ?> — <?= e($d['interest_rate']) ?> %</li><?php endforeach; ?></ol></div>
        <div class="col-md-6"><strong class="small">Boule de neige (plus petit solde d'abord, motivation rapide)</strong>
            <ol class="small"><?php foreach ($snowball as $d): ?><li><?= e($d['name']) ?> — <?= money($remaining[$d['id']] ?? 0) ?></li><?php endforeach; ?></ol></div>
    </div>
</div></div>
<?php endif; ?>
<?php endif; ?>
