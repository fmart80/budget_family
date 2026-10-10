<?php $totalInterest = array_sum(array_column($schedule, 'interest')); ?>
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
    <div class="col-6 col-md-3"><div class="card card-body"><div class="stat-label">Intérêts restants estimés</div><div class="stat-value"><?= money($totalInterest) ?></div><small class="text-muted"><?= count($schedule) ?> mensualité(s)</small></div></div>
</div>

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
            <h2 class="h6">Échéancier prévisionnel</h2>
            <?php if (!$schedule): ?>
                <p class="text-muted small mb-0"><?= $remaining > 0 ? 'Indiquez une mensualité supérieure aux intérêts mensuels pour calculer l\'échéancier.' : 'Dette remboursée.' ?></p>
            <?php else: ?>
            <div class="table-responsive" style="max-height: 480px">
            <table class="table table-sm small mb-0">
                <thead class="sticky-top bg-white"><tr><th>Mois</th><th class="text-end">Mensualité</th><th class="text-end">Intérêts</th><th class="text-end">Capital</th><th class="text-end">Solde</th></tr></thead>
                <tbody>
                <?php foreach ($schedule as $s): ?>
                    <tr><td><?= e(month_label($s['month'])) ?></td><td class="text-end amount"><?= money($s['payment'], false) ?></td>
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
