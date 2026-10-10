<?php
use App\Controllers\SavingsController;
$pct = $goal['target_amount'] > 0 ? min(100, $saved / $goal['target_amount'] * 100) : 0;
$monthly = SavingsController::monthlyNeeded($goal, $saved);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h3 mb-0"><?= e($goal['name']) ?></h1>
        <span class="text-muted"><?= $goal['account_name'] ? 'Compte : ' . e($goal['account_name']) : '' ?></span></div>
    <div class="d-flex gap-2">
        <a href="<?= url('savings/edit', ['id' => $goal['id']]) ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Modifier</a>
        <form method="post" action="<?= url('savings/delete') ?>" data-confirm="Supprimer cet objectif et son historique ?">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $goal['id'] ?>">
            <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</div>
<div class="card card-body mb-4">
    <div class="d-flex justify-content-between"><span class="stat-value amount-income"><?= money($saved) ?></span><span class="stat-value text-muted">/ <?= money($goal['target_amount']) ?></span></div>
    <div class="progress my-2" style="height: 1rem"><div class="progress-bar" style="width: <?= round($pct) ?>%; background: <?= e($goal['color']) ?>"><?= round($pct) ?> %</div></div>
    <div class="small text-muted">Reste <?= money(max(0, $goal['target_amount'] - $saved)) ?>
        <?php if ($goal['target_date']): ?> · échéance <?= date_ch($goal['target_date']) ?><?php if ($monthly > 0): ?> · à épargner : <strong><?= money($monthly) ?> par mois</strong><?php endif; ?><?php endif; ?></div>
    <?php if ($goal['notes']): ?><p class="small mt-2 mb-0"><?= nl2br(e($goal['notes'])) ?></p><?php endif; ?>
</div>
<div class="row g-4">
    <div class="col-lg-5"><div class="card"><div class="card-body">
        <h2 class="h6">Nouveau versement</h2>
        <form method="post" action="<?= url('savings/contribute') ?>">
            <?= csrf_field() ?><input type="hidden" name="goal_id" value="<?= $goal['id'] ?>">
            <div class="row g-2">
                <div class="col-6"><input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                <div class="col-6"><input name="amount" class="form-control" inputmode="decimal" placeholder="Montant" value="<?= $monthly > 0 ? e(number_format($monthly, 2, '.', '')) : '' ?>"></div>
                <?php if ($goal['account_id']): ?>
                <div class="col-12"><select name="from_account_id" class="form-select">
                    <option value="">Ne pas créer de virement</option>
                    <?php foreach ($accounts as $a): if ((int) $a['id'] === (int) $goal['account_id']) { continue; } ?>
                        <option value="<?= $a['id'] ?>">Virement depuis <?= e($a['name']) ?></option>
                    <?php endforeach; ?>
                </select></div>
                <?php endif; ?>
                <div class="col-12"><input name="notes" class="form-control" placeholder="Note (facultatif)"></div>
            </div>
            <div class="form-text mb-2">Montant négatif = retrait.</div>
            <button class="btn btn-success">Enregistrer</button>
        </form>
    </div></div></div>
    <div class="col-lg-7"><div class="card"><div class="card-body">
        <h2 class="h6">Historique</h2>
        <?php if (!$contributions): ?><p class="text-muted small mb-0">Aucun versement.</p><?php endif; ?>
        <ul class="list-group list-group-flush">
        <?php foreach ($contributions as $c): ?>
            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                <span><?= date_ch($c['date']) ?> <small class="text-muted"><?= e($c['notes']) ?></small></span>
                <span class="d-flex gap-2 align-items-center">
                    <span class="amount <?= $c['amount'] < 0 ? 'amount-expense' : 'amount-income' ?>"><?= money($c['amount']) ?></span>
                    <form method="post" action="<?= url('savings/contribution_delete') ?>" data-confirm="Supprimer ce versement ?">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button class="btn btn-sm btn-link p-0 text-danger"><i class="bi bi-x-lg"></i></button>
                    </form>
                </span>
            </li>
        <?php endforeach; ?>
        </ul>
    </div></div></div>
</div>
