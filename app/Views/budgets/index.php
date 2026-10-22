<?php
$prev = date('Y-m', strtotime($month . '-01 -1 month'));
$next = date('Y-m', strtotime($month . '-01 +1 month'));
$totalBudget = array_sum(array_column($rows, 'budget'));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h3 mb-0">Budget — <?= e(month_label($month)) ?></h1>
    <div class="btn-group">
        <a class="btn btn-outline-secondary" href="<?= url('budgets', ['month' => $prev]) ?>"><i class="bi bi-chevron-left"></i></a>
        <a class="btn btn-outline-secondary" href="<?= url('budgets') ?>">Mois actuel</a>
        <a class="btn btn-outline-secondary" href="<?= url('budgets', ['month' => $next]) ?>"><i class="bi bi-chevron-right"></i></a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card card-body"><div class="stat-label">Revenus du mois</div><div class="stat-value amount-income"><?= money($totals['income']) ?></div>
        <?php if ($totals['income'] > 0): ?><small class="text-muted">Taux d'épargne : <strong><?= number_format($totals['net'] / $totals['income'] * 100, 1, '.', "'") ?> %</strong></small><?php endif; ?></div></div>
    <div class="col-md-3"><div class="card card-body"><div class="stat-label">Budget prévu</div><div class="stat-value"><?= money($totalBudget) ?></div></div></div>
    <div class="col-md-3"><div class="card card-body"><div class="stat-label">Dépensé</div><div class="stat-value amount-expense"><?= money($totals['expense']) ?></div></div></div>
    <div class="col-md-3"><div class="card card-body"><div class="stat-label">Reste à dépenser</div>
        <div class="stat-value <?= $totalBudget - $totals['expense'] < 0 ? 'amount-expense' : '' ?>"><?= money($totalBudget - $totals['expense']) ?></div></div></div>
</div>

<?php if (!$hasBudget): ?>
    <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Aucun budget défini pour ce mois. Saisissez les montants ci-dessous ou reprenez ceux du mois précédent.</span>
        <form method="post" action="<?= url('budgets/copy') ?>"><?= csrf_field() ?><input type="hidden" name="month" value="<?= e($month) ?>">
            <button class="btn btn-sm btn-primary">Copier <?= e(month_label($prev)) ?></button></form>
    </div>
<?php endif; ?>

<form method="post" action="<?= url('budgets/save') ?>">
    <?= csrf_field() ?><input type="hidden" name="month" value="<?= e($month) ?>">
    <div class="card"><div class="table-responsive">
    <table class="table mb-0">
        <thead><tr><th>Catégorie</th><th style="width: 160px">Budget (CHF)</th><th class="text-end">Dépensé</th><th class="text-end">Reste</th><th style="width: 30%">Progression</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $pct = $r['budget'] > 0 ? min(100, $r['spent'] / $r['budget'] * 100) : ($r['spent'] > 0 ? 100 : 0);
            $over = $r['spent'] > $r['budget'];
            $bar = $over ? 'bg-danger' : ($pct > 85 ? 'bg-warning' : 'bg-success');
        ?>
            <tr>
                <td><span class="cat-dot" style="background: <?= e($r['category']['color']) ?>"></span>
                    <a href="<?= url('transactions', ['month' => $month, 'category' => $r['category']['id']]) ?>" class="text-reset"><?= e($r['category']['name']) ?></a></td>
                <td><input name="amounts[<?= $r['category']['id'] ?>]" class="form-control form-control-sm text-end" inputmode="decimal"
                           value="<?= $r['budget'] > 0 ? e(number_format($r['budget'], 2, '.', '')) : '' ?>" placeholder="0.00"></td>
                <td class="text-end amount"><?= money($r['spent'], false) ?></td>
                <td class="text-end amount <?= $over ? 'amount-expense fw-semibold' : '' ?>"><?= $r['budget'] > 0 || $r['spent'] > 0 ? money($r['budget'] - $r['spent'], false) : '' ?></td>
                <td><?php if ($r['budget'] > 0 || $r['spent'] > 0): ?><div class="progress"><div class="progress-bar <?= $bar ?>" style="width: <?= round($pct) ?>%"></div></div><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($uncategorized > 0): ?>
            <tr class="table-warning"><td colspan="2"><a href="<?= url('transactions', ['month' => $month, 'category' => 'none']) ?>">Dépenses sans catégorie</a></td>
                <td class="text-end amount"><?= money($uncategorized, false) ?></td><td colspan="2"></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <div class="card-footer bg-white"><button class="btn btn-primary">Enregistrer le budget</button></div>
    </div>
</form>
