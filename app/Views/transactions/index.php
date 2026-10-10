<?php
$prev = $filters['month'] !== '' ? date('Y-m', strtotime($filters['month'] . '-01 -1 month')) : null;
$next = $filters['month'] !== '' ? date('Y-m', strtotime($filters['month'] . '-01 +1 month')) : null;
$qs = array_filter(['account' => $filters['account'], 'category' => $filters['category'], 'type' => $filters['type'], 'q' => $filters['q']]);
$back = url('transactions', ['month' => $filters['month']] + $qs);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Transactions</h1>
    <div class="d-flex gap-2">
        <a href="<?= url('transactions/export', ['month' => $filters['month']] + $qs) ?>" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Export CSV</a>
        <a href="<?= url('import') ?>" class="btn btn-outline-primary"><i class="bi bi-upload"></i> Importer</a>
        <a href="<?= url('transactions/edit') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle</a>
    </div>
</div>

<form class="card card-body mb-3 no-print" method="get">
    <input type="hidden" name="p" value="transactions">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">Mois</label>
            <div class="input-group">
                <?php if ($prev): ?><a class="btn btn-outline-secondary" href="<?= url('transactions', ['month' => $prev] + $qs) ?>"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
                <input type="month" name="month" class="form-control" value="<?= e($filters['month']) ?>">
                <?php if ($next): ?><a class="btn btn-outline-secondary" href="<?= url('transactions', ['month' => $next] + $qs) ?>"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
            </div>
        </div>
        <div class="col-md-2"><label class="form-label small mb-1">Compte</label>
            <select name="account" class="form-select"><option value="">Tous</option>
                <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"<?= selected($a['id'], $filters['account']) ?>><?= e($a['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Catégorie</label>
            <select name="category" class="form-select"><option value="">Toutes</option>
                <option value="none"<?= selected('none', $filters['category']) ?>>— Sans catégorie —</option>
                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"<?= selected($c['id'], $filters['category']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Type</label>
            <select name="type" class="form-select"><option value="">Tous</option>
                <option value="expense"<?= selected('expense', $filters['type']) ?>>Dépenses</option>
                <option value="income"<?= selected('income', $filters['type']) ?>>Revenus</option>
                <option value="transfer"<?= selected('transfer', $filters['type']) ?>>Virements</option>
            </select></div>
        <div class="col-md-2"><label class="form-label small mb-1">Recherche</label>
            <input name="q" class="form-control" value="<?= e($filters['q']) ?>" placeholder="Libellé…"></div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-primary w-100"><i class="bi bi-funnel"></i></button>
        </div>
    </div>
    <div class="small mt-2"><a href="<?= url('transactions', ['month' => ''] + $qs) ?>">Tous les mois</a></div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Revenus</div><div class="stat-value amount-income"><?= money($totals['income']) ?></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Dépenses</div><div class="stat-value amount-expense"><?= money($totals['expense']) ?></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Solde de la période</div><div class="stat-value"><?= money($totals['income'] - $totals['expense']) ?></div></div></div>
</div>

<?php if (!$transactions): ?>
    <div class="alert alert-info">Aucune transaction pour ces critères.</div>
<?php else: ?>
<form method="post" action="<?= url('transactions/bulk') ?>">
    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="card">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center no-print">
            <span class="small text-muted">Sélection :</span>
            <select name="category_id" class="form-select form-select-sm w-auto">
                <option value="">Choisir une catégorie…</option>
                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= $c['type'] === 'income' ? '[+] ' : '' ?><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
            <button name="action" value="categorize" class="btn btn-sm btn-outline-primary">Appliquer</button>
            <button name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer les transactions sélectionnées ?')">Supprimer</button>
            <span class="ms-auto small text-muted"><?= (int) $totals['n'] ?> transaction(s)<?= $totals['n'] > $limit ? " — affichage limité à $limit" : '' ?></span>
        </div>
        <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
            <thead><tr>
                <th style="width: 2rem"><input type="checkbox" class="form-check-input" data-check-all=".tx-check"></th>
                <th>Date</th><th>Libellé</th><th>Catégorie</th><th>Compte</th><th class="text-end">Montant</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($transactions as $t): ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input tx-check" name="ids[]" value="<?= $t['id'] ?>"></td>
                    <td class="text-nowrap"><?= date_ch($t['date']) ?></td>
                    <td><?= e($t['description']) ?><?php if ($t['notes']): ?> <i class="bi bi-chat-left-text text-muted" title="<?= e($t['notes']) ?>"></i><?php endif; ?>
                        <?php if ($t['user_name']): ?><br><small class="text-muted"><?= e($t['user_name']) ?></small><?php endif; ?></td>
                    <td><?php if ($t['type'] === 'transfer'): ?><span class="text-muted"><i class="bi bi-arrow-right"></i> <?= e($t['to_account_name']) ?></span>
                        <?php elseif ($t['category_name']): ?><span class="cat-dot" style="background: <?= e($t['category_color']) ?>"></span><?= e($t['category_name']) ?>
                        <?php else: ?><span class="badge text-bg-warning">à classer</span><?php endif; ?></td>
                    <td class="small"><?= e($t['account_name']) ?></td>
                    <td class="text-end"><?php require __DIR__ . '/_amount.php'; ?></td>
                    <td class="text-end"><a href="<?= url('transactions/edit', ['id' => $t['id']]) ?>" class="btn btn-sm btn-link p-0"><i class="bi bi-pencil"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</form>
<?php endif; ?>
