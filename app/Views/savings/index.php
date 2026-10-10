<?php use App\Controllers\SavingsController; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Épargne</h1>
    <a href="<?= url('savings/edit') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvel objectif</a>
</div>
<?php if (!$goals): ?>
    <div class="alert alert-info">Créez vos objectifs : fonds d'urgence (3 à 6 mois de charges), vacances, 3ᵉ pilier, voiture…</div>
<?php else: ?>
<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card card-body"><div class="stat-label">Total épargné</div><div class="stat-value amount-income"><?= money(array_sum($saved)) ?></div></div></div>
    <div class="col-md-6"><div class="card card-body"><div class="stat-label">Total des objectifs</div><div class="stat-value"><?= money(array_sum(array_column($goals, 'target_amount'))) ?></div></div></div>
</div>
<div class="row g-3">
<?php foreach ($goals as $g):
    $s = $saved[$g['id']] ?? 0;
    $pct = $g['target_amount'] > 0 ? min(100, $s / $g['target_amount'] * 100) : 0;
    $monthly = SavingsController::monthlyNeeded($g, $s);
?>
    <div class="col-md-6 col-xl-4">
        <a href="<?= url('savings/show', ['id' => $g['id']]) ?>" class="card h-100 text-reset text-decoration-none" style="border-top: 4px solid <?= e($g['color']) ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between"><h2 class="h6"><?= e($g['name']) ?></h2>
                    <?php if ($pct >= 100): ?><span class="badge text-bg-success">atteint</span><?php endif; ?></div>
                <div class="d-flex justify-content-between small"><span class="amount"><?= money($s) ?></span><span class="text-muted amount">/ <?= money($g['target_amount']) ?></span></div>
                <div class="progress my-2"><div class="progress-bar" style="width: <?= round($pct) ?>%; background: <?= e($g['color']) ?>"></div></div>
                <div class="d-flex justify-content-between small text-muted">
                    <span><?= round($pct) ?> %</span>
                    <span><?php if ($g['target_date']): ?>pour le <?= date_ch($g['target_date']) ?><?php if ($monthly > 0): ?> · <?= money($monthly) ?>/mois<?php endif; ?><?php endif; ?></span>
                </div>
            </div>
        </a>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
