<?php
use App\Forecast;
$totalInterest = $current['interest'] ?? array_sum(array_column($schedule, 'interest'));
$isSimulated = $sim['payment'] != (float) $debt['monthly_payment'] || $sim['extra'] > 0;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h3 mb-0"><?= e($debt['name']) ?></h1><span class="text-muted"><?= e($debt['creditor']) ?></span></div>
    <div class="d-flex gap-2">
        <a href="<?= url('debts/edit', ['id' => $debt['id']]) ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Modifier</a>
        <form method="post" action="<?= url('debts/delete') ?>" data-confirm="Supprimer cette dette et tout son historique ?">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $debt['id'] ?>">
            <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card card-body"><div class="stat-label">Emprunté</div><div class="stat-value"><?= money($debt['initial_amount']) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body"><div class="stat-label">Restant</div><div class="stat-value amount-expense"><?= money($remaining) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card card-body"><div class="stat-label">Mensualité · taux</div><div class="stat-value"><?= money($debt['monthly_payment']) ?></div><small class="text-muted"><?= e($debt['interest_rate']) ?> % / an</small></div></div>
    <div class="col-6 col-md-3"><div class="card card-body"><div class="stat-label">Intérêts restants estimés</div><div class="stat-value"><?= money($totalInterest) ?></div><small class="text-muted"><?= $current ? $current['months'] . ' mensualité(s) au rythme actuel' : count($schedule) . ' mensualité(s)' ?></small></div></div>
</div>

<?php if ($remaining > 0): ?>
<div class="card mb-4 border-start border-4 border-primary"><div class="card-body">
    <h2 class="h6"><i class="bi bi-calculator"></i> Simulateur de remboursement</h2>
    <form method="get" class="row g-2 align-items-end mb-3">
        <input type="hidden" name="p" value="debts/show"><input type="hidden" name="id" value="<?= $debt['id'] ?>">
        <div class="col-md-2"><label class="form-label small mb-1">Mensualité</label>
            <input name="sim_payment" class="form-control form-control-sm" inputmode="decimal" value="<?= e(number_format($sim['payment'], 2, '.', '')) ?>"></div>
        <div class="col-md-2"><label class="form-label small mb-1">+ Versement extra</label>
            <input name="sim_extra" class="form-control form-control-sm" inputmode="decimal" value="<?= $sim['extra'] ? e(number_format($sim['extra'], 2, '.', '')) : '' ?>" placeholder="ex. 1000"></div>
        <div class="col-md-5"><label class="form-label small mb-1">… chaque année en (ex. mois du 13ᵉ salaire)</label>
            <div class="d-flex flex-wrap gap-2 small">
                <?php foreach (Forecast::monthNames(true) as $n => $name): ?>
                    <label><input type="checkbox" class="form-check-input" name="sim_months[]" value="<?= $n ?>"<?= checked(in_array($n, $sim['months'], true)) ?>> <?= $name ?></label>
                <?php endforeach; ?>
            </div></div>
        <div class="col-md-2"><label class="form-label small mb-1">Ou : finir en (mois)</label>
            <input type="number" name="sim_target" min="1" max="240" class="form-control form-control-sm" value="<?= $sim['target'] ?: '' ?>" placeholder="ex. 12"></div>
        <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Calculer</button></div>
    </form>
    <?php if ($targetPayment): ?>
        <div class="alert alert-info py-2">Pour rembourser <?= money($remaining) ?> en <strong><?= $sim['target'] ?> mois</strong>, il faut payer <strong><?= money($targetPayment) ?> par mois</strong>.
            <a href="<?= url('debts/show', ['id' => $debt['id'], 'sim_payment' => $targetPayment]) ?>">Simuler</a></div>
    <?php endif; ?>
    <div class="row g-3">
        <div class="col-md-6"><div class="p-3 bg-light rounded h-100">
            <div class="stat-label">Situation actuelle (<?= money($debt['monthly_payment']) ?>/mois)</div>
            <?php if ($current): ?>
                <div>Fin : <strong><?= e(month_label($current['end'])) ?></strong> (<?= $current['months'] ?> mois)</div>
                <div>Intérêts payés : <strong class="amount-expense"><?= money($current['interest']) ?></strong></div>
            <?php else: ?><div class="text-danger">La mensualité ne couvre pas les intérêts : la dette ne diminue pas.</div><?php endif; ?>
        </div></div>
        <div class="col-md-6"><div class="p-3 rounded h-100 <?= $isSimulated ? 'bg-primary-subtle' : 'bg-light' ?>">
            <div class="stat-label">Simulation (<?= money($sim['payment']) ?>/mois<?= $sim['extra'] && $sim['months'] ? ' + ' . money($sim['extra']) . ' en ' . e(Forecast::describeMonths($sim['months'])) : '' ?>)</div>
            <?php if ($simResult): ?>
                <div>Fin : <strong><?= e(month_label($simResult['end'])) ?></strong> (<?= $simResult['months'] ?> mois)</div>
                <div>Intérêts payés : <strong><?= money($simResult['interest']) ?></strong>
                    <?php if ($current && $current['interest'] - $simResult['interest'] > 0.5): ?>
                        <span class="badge text-bg-success">économie <?= money($current['interest'] - $simResult['interest']) ?></span>
                        <span class="badge text-bg-success"><?= $current['months'] - $simResult['months'] ?> mois plus tôt</span>
                    <?php endif; ?></div>
            <?php else: ?><div class="text-danger">Avec ces montants, la dette n'est jamais remboursée.</div><?php endif; ?>
        </div></div>
    </div>
    <?php if ($scenarios): ?>
    <div class="table-responsive mt-3"><table class="table table-sm small mb-0">
        <thead><tr><th>Mensualité</th><th class="text-end">Durée</th><th>Fin</th><th class="text-end">Intérêts totaux</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($scenarios as $p => $sc): if (!$sc) { continue; } ?>
            <tr class="<?= (float) $p === (float) $debt['monthly_payment'] ? 'table-primary' : '' ?>">
                <td class="amount"><?= money($p) ?></td><td class="text-end"><?= $sc['months'] ?> mois</td><td><?= e(month_label($sc['end'])) ?></td>
                <td class="text-end amount"><?= money($sc['interest']) ?></td>
                <td class="text-end"><a href="<?= url('debts/show', ['id' => $debt['id'], 'sim_payment' => $p]) ?>">simuler</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
    <?php if ($isSimulated): ?>
        <p class="small text-muted mt-2 mb-0">L'échéancier ci-dessous montre la simulation. Pour l'adopter, <a href="<?= url('debts/edit', ['id' => $debt['id']]) ?>">modifiez la mensualité</a> de la dette (elle sera reprise dans les prévisions).</p>
    <?php endif; ?>
</div></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card mb-4"><div class="card-body">
            <h2 class="h6">Enregistrer un remboursement</h2>
            <form method="post" action="<?= url('debts/payment') ?>">
                <?= csrf_field() ?><input type="hidden" name="debt_id" value="<?= $debt['id'] ?>">
                <div class="row g-2">
                    <div class="col-6"><input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-6"><input name="amount" class="form-control" value="<?= e($debt['monthly_payment']) ?>" inputmode="decimal" placeholder="Montant"></div>
                    <div class="col-12"><select name="account_id" class="form-select">
                        <option value="">Ne pas créer de transaction</option>
                        <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="col-12"><input name="notes" class="form-control" placeholder="Note (facultatif)"></div>
                </div>
                <div class="form-text mb-2">Montant négatif = intérêts ou frais ajoutés au solde.</div>
                <button class="btn btn-success">Enregistrer</button>
            </form>
        </div></div>
        <div class="card"><div class="card-body">
            <h2 class="h6">Historique</h2>
            <?php if (!$payments): ?><p class="text-muted small mb-0">Aucun remboursement.</p><?php endif; ?>
            <ul class="list-group list-group-flush">
            <?php foreach ($payments as $p): ?>
                <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                    <span><?= date_ch($p['date']) ?> <small class="text-muted"><?= e($p['notes']) ?></small></span>
                    <span class="d-flex align-items-center gap-2">
                        <span class="amount <?= $p['amount'] < 0 ? 'amount-expense' : 'amount-income' ?>"><?= money($p['amount']) ?></span>
                        <form method="post" action="<?= url('debts/payment_delete') ?>" data-confirm="Supprimer ce remboursement ?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <?php if ($p['transaction_id']): ?><input type="hidden" name="delete_transaction" value="1"><?php endif; ?>
                            <button class="btn btn-sm btn-link p-0 text-danger"><i class="bi bi-x-lg"></i></button>
                        </form>
                    </span>
                </li>
            <?php endforeach; ?>
            </ul>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <h2 class="h6">Échéancier prévisionnel<?= $isSimulated ? ' <span class="badge text-bg-primary">simulation</span>' : '' ?></h2>
            <?php if (!$schedule): ?>
                <p class="text-muted small mb-0"><?= $remaining > 0 ? 'Indiquez une mensualité supérieure aux intérêts mensuels pour calculer l\'échéancier.' : 'Dette remboursée.' ?></p>
            <?php else: ?>
            <div class="table-responsive" style="max-height: 480px">
            <table class="table table-sm small mb-0">
                <thead class="sticky-top bg-white"><tr><th>Mois</th><th class="text-end">Mensualité</th><th class="text-end">Intérêts</th><th class="text-end">Capital</th><th class="text-end">Solde</th></tr></thead>
                <tbody>
                <?php foreach ($schedule as $s): ?>
                    <tr class="<?= $s['extra'] > 0 ? 'table-success' : '' ?>"><td><?= e(month_label($s['month'])) ?></td><td class="text-end amount"><?= money($s['payment'], false) ?></td>
                        <td class="text-end amount"><?= money($s['interest'], false) ?></td><td class="text-end amount"><?= money($s['principal'], false) ?></td>
                        <td class="text-end amount"><?= money($s['balance'], false) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div></div>
    </div>
</div>
