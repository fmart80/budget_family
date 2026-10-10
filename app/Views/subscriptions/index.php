<?php
use App\Subscriptions;
$annual = $monthly * 12;
$palette = ['#e4606d', '#0d6efd', '#198754', '#fd7e14', '#6f42c1', '#20c997', '#ffc107', '#0dcaf0', '#d63384', '#6c757d'];
$pie = ['type' => 'doughnut', 'data' => ['labels' => array_column($active, 'name'),
    'datasets' => [['data' => array_column($active, 'monthly'), 'backgroundColor' => array_map(fn ($i) => $palette[$i % 10], array_keys($active))]]],
    'options' => ['plugins' => ['legend' => ['position' => 'right']]]];
$bar = ['type' => 'bar', 'data' => ['labels' => array_keys($byAccount),
    'datasets' => [['label' => 'CHF / mois', 'data' => array_map(fn ($v) => round($v, 2), array_values($byAccount)), 'backgroundColor' => '#0d6efd']]],
    'options' => ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]]];
$daysUntil = fn (string $d) => (int) round((strtotime($d) - strtotime(date('Y-m-d'))) / 86400);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="h3 mb-0">Abonnements</h1>
    <a href="<?= url('subscriptions/edit') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvel abonnement</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="stat-label">Coût par mois</div><div class="stat-value"><?= money($monthly) ?></div>
        <small class="text-muted"><?= count($active) ?> abonnement(s) actif(s)</small></div></div>
    <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="stat-label">Coût par an</div><div class="stat-value amount-expense"><?= money($annual) ?></div>
        <?php if ($income > 0): ?><small class="text-muted"><?= number_format($monthly / $income * 100, 1, '.', "'") ?> % des revenus prévus</small><?php endif; ?></div></div>
    <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="stat-label">Sur la carte de crédit</div><div class="stat-value"><?= money($onCard) ?></div>
        <small class="text-muted">par mois<?= $hasCardAccount ? '' : ' — aucun compte de type « Carte de crédit »' ?></small></div></div>
    <div class="col-6 col-lg-3"><div class="card card-body h-100"><div class="stat-label">Économisé grâce aux résiliations</div><div class="stat-value amount-income"><?= money($savings) ?></div>
        <small class="text-muted"><?= count($cancelled) ?> abonnement(s) résilié(s)</small></div></div>
</div>

<?php if ($onCard > 0): ?>
    <div class="alert alert-warning"><i class="bi bi-credit-card"></i> <strong><?= money($onCard) ?> par mois</strong> (<?= money($onCard * 12) ?> par an) sont prélevés sur la carte de crédit.
        Si le relevé n'est pas payé en entier, ces abonnements <strong>s'ajoutent à la dette et coûtent des intérêts</strong>. Pendant le remboursement de la carte, payez-les plutôt par carte de débit ou TWINT, ou résiliez ceux dont vous pouvez vous passer.</div>
<?php endif; ?>

<?php foreach ($alerts as $a): $s = $a['sub']; ?>
    <?php if ($a['type'] === 'price'): $diff = $a['charged'] - $s['amount']; ?>
        <div class="alert alert-<?= $diff > 0 ? 'danger' : 'info' ?> d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="bi bi-graph-up-arrow"></i> <strong><?= e($s['name']) ?></strong> : <?= money($a['charged']) ?> prélevé le <?= date_ch($a['date']) ?> au lieu de <?= money($s['amount']) ?>
                (<?= $diff > 0 ? '+' : '' ?><?= money($diff, false) ?>, soit <?= $diff > 0 ? '+' : '' ?><?= money($diff * 12 / Subscriptions::CYCLES[$s['cycle']], false) ?> par an).</span>
            <form method="post" action="<?= url('subscriptions/update_amount') ?>"><?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="amount" value="<?= e($a['charged']) ?>">
                <button class="btn btn-sm btn-outline-dark">Mettre à jour le montant</button></form>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary"><i class="bi bi-question-circle"></i> <strong><?= e($s['name']) ?></strong> : aucun prélèvement trouvé depuis le <?= date_ch($a['date']) ?>. Résilié, changé de moyen de paiement, ou relevé pas encore importé ?</div>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ($active): ?>
<div class="row g-4 mb-4">
    <div class="col-lg-7"><div class="card h-100"><div class="card-body">
        <h2 class="h6">Répartition par abonnement (CHF / mois)</h2>
        <div class="chart-box"><canvas data-chart="<?= e(json_encode($pie)) ?>"></canvas></div>
    </div></div></div>
    <div class="col-lg-5"><div class="card h-100"><div class="card-body">
        <h2 class="h6">Prochains prélèvements (30 jours)</h2>
        <?php if (!$upcoming): ?><p class="text-muted small">Aucun.</p><?php endif; ?>
        <ul class="list-group list-group-flush">
        <?php foreach ($upcoming as $s): $d = $daysUntil($s['next_date']); ?>
            <li class="list-group-item px-0 d-flex justify-content-between">
                <span><?= e($s['name']) ?><br><small class="text-muted"><?= date_ch($s['next_date']) ?> · <?= $d === 0 ? "aujourd'hui" : "dans $d jour(s)" ?><?= $s['account_name'] ? ' · ' . e($s['account_name']) : '' ?></small></span>
                <span class="amount"><?= money($s['amount']) ?></span>
            </li>
        <?php endforeach; ?>
        </ul>
        <h2 class="h6 mt-3">Par moyen de paiement</h2>
        <div style="height: <?= 40 + 30 * count($byAccount) ?>px; position: relative"><canvas data-chart="<?= e(json_encode($bar)) ?>"></canvas></div>
    </div></div></div>
</div>
<?php endif; ?>

<?php foreach ($renewals as $s): ?>
    <div class="alert alert-info py-2"><i class="bi bi-calendar-event"></i> Renouvellement <?= e(mb_strtolower(Subscriptions::CYCLE_LABELS[$s['cycle']])) ?> de <strong><?= e($s['name']) ?></strong> le <?= date_ch($s['next_date']) ?> (<?= money($s['amount']) ?>) : c'est le moment de vérifier si vous le gardez.</div>
<?php endforeach; ?>

<div class="card mb-4">
    <div class="card-header bg-white"><strong>Abonnements actifs</strong></div>
    <?php if (!$active): ?>
        <div class="card-body text-muted">Aucun abonnement. Ajoutez Netflix, Spotify, téléphone mobile, iCloud, fitness…<?= $hasTransactions ? ' ou utilisez les suggestions ci-dessous.' : '' ?></div>
    <?php else: ?>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Abonnement</th><th>Fréquence</th><th>Prochain</th><th>Payé avec</th><th class="text-end">Montant</th><th class="text-end">Par mois</th><th class="text-end">Par an</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($active as $s): ?>
            <tr>
                <td><strong><?= e($s['name']) ?></strong><?php if ($s['url']): ?> <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener" title="Gérer / résilier"><i class="bi bi-box-arrow-up-right small"></i></a><?php endif; ?>
                    <?php if ($s['owner']): ?><br><small class="text-muted"><?= e($s['owner']) ?></small><?php endif; ?>
                    <?php if ($s['previous_amount']): ?><br><small class="<?= $s['amount'] > $s['previous_amount'] ? 'text-danger' : 'text-success' ?>">était <?= money($s['previous_amount'], false) ?> jusqu'au <?= date_ch($s['amount_changed_at']) ?></small><?php endif; ?></td>
                <td><?= Subscriptions::CYCLE_LABELS[$s['cycle']] ?></td>
                <td class="text-nowrap"><?= date_ch($s['next_date']) ?></td>
                <td><?= $s['on_card'] ? '<i class="bi bi-credit-card text-warning"></i> ' : '' ?><?= e($s['account_name'] ?? '—') ?></td>
                <td class="text-end amount"><?= money($s['amount'], false) ?></td>
                <td class="text-end amount"><?= money($s['monthly'], false) ?></td>
                <td class="text-end amount fw-semibold"><?= money($s['annual'], false) ?></td>
                <td class="text-end text-nowrap">
                    <a href="<?= url('subscriptions/edit', ['id' => $s['id']]) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-danger dropdown-toggle" data-bs-toggle="dropdown">Résilier</button>
                        <div class="dropdown-menu dropdown-menu-end p-2">
                            <form method="post" action="<?= url('subscriptions/status') ?>" class="mb-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button name="status" value="cancelled" class="dropdown-item text-danger">Marquer comme résilié</button>
                                <button name="status" value="paused" class="dropdown-item">Suspendre</button></form>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="fw-semibold"><tr><td colspan="5">Total</td><td class="text-end amount"><?= money($monthly, false) ?></td><td class="text-end amount"><?= money($annual, false) ?></td><td></td></tr></tfoot>
    </table></div>
    <?php endif; ?>
</div>

<?php if ($suggestions): ?>
<div class="card mb-4 border-start border-4 border-primary">
    <div class="card-header bg-white"><strong><i class="bi bi-magic"></i> Abonnements détectés dans vos relevés</strong></div>
    <div class="card-body pb-0"><p class="small text-muted">Ces dépenses reviennent chaque mois avec un montant stable. Ajoutez celles qui sont des abonnements.</p></div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Nom proposé</th><th>Exemple de libellé</th><th class="text-end">Mois vus</th><th>Dernier</th><th class="text-end">Montant</th><th></th></tr></thead>
        <?php foreach ($suggestions as $g): ?>
            <tr><td><?= e($g['name']) ?></td><td class="small text-muted"><?= e(mb_strimwidth($g['example'], 0, 60, '…')) ?></td>
                <td class="text-end"><?= $g['months'] ?></td><td><?= date_ch($g['last_date']) ?></td><td class="text-end amount"><?= money($g['amount'], false) ?></td>
                <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('subscriptions/edit', ['name' => $g['name'], 'amount' => $g['amount'], 'keyword' => $g['keyword'], 'date' => $g['last_date'], 'account_id' => $g['account_id']]) ?>">Ajouter</a>
                    <form method="post" action="<?= url('subscriptions/ignore') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="keyword" value="<?= e($g['keyword']) ?>">
                        <button class="btn btn-sm btn-link text-muted" title="Ce n'est pas un abonnement">Ignorer</button></form></td></tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php elseif (!$hasTransactions): ?>
    <div class="alert alert-light border small"><i class="bi bi-magic"></i> Après l'<a href="<?= url('import') ?>">import</a> de quelques mois de relevés (compte et carte de crédit), l'application détecte automatiquement vos abonnements et signale les hausses de prix.</div>
<?php endif; ?>

<?php if ($paused || $cancelled): ?>
<div class="card"><div class="card-header bg-white"><strong>Suspendus et résiliés</strong></div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <?php foreach (array_merge($paused, $cancelled) as $s): ?>
            <tr class="text-muted"><td><?= e($s['name']) ?></td>
                <td><?= $s['status'] === 'paused' ? 'suspendu' : 'résilié le ' . date_ch($s['cancelled_at']) ?></td>
                <td class="text-end amount"><?= money($s['monthly'], false) ?> / mois</td>
                <td class="text-end amount-income"><?= isset($s['saved']) && $s['saved'] > 0 ? 'économisé ' . money($s['saved']) : '' ?></td>
                <td class="text-end"><form method="post" action="<?= url('subscriptions/status') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <button name="status" value="active" class="btn btn-sm btn-link p-0">réactiver</button></form>
                    <form method="post" action="<?= url('subscriptions/delete') ?>" class="d-inline ms-2" data-confirm="Supprimer définitivement ?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <button class="btn btn-sm btn-link p-0 text-danger"><i class="bi bi-trash"></i></button></form></td></tr>
        <?php endforeach; ?>
    </table></div>
</div>
<?php endif; ?>
