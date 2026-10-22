<?php use App\Controllers\TaxesController; ?>
<h1 class="h3 mb-2">Impôts : estimation et déductions</h1>
<p class="text-muted">Estimation simple et fiable à partir de votre <strong>dernier avis de taxation</strong> : on calcule votre taux d'imposition réel, puis on l'applique à vos revenus de cette année. Pour un calcul officiel détaillé, utilisez le <a href="https://swisstaxcalculator.estv.admin.ch" target="_blank" rel="noopener">calculateur d'impôts de la Confédération</a>.</p>

<div class="row g-4 mb-4">
    <div class="col-lg-5"><div class="card h-100"><div class="card-body">
        <h2 class="h6">Votre dernier avis de taxation</h2>
        <form method="post" action="<?= url('taxes/save') ?>">
            <?= csrf_field() ?>
            <div class="row g-2">
                <div class="col-4"><label class="form-label small mb-1">Année</label>
                    <input name="tax_ref_year" class="form-control" value="<?= e($refYear) ?>" inputmode="numeric"></div>
                <div class="col-8"><label class="form-label small mb-1">Revenus nets de cette année-là</label>
                    <input name="tax_ref_income" class="form-control" value="<?= $refIncome ? e(number_format($refIncome, 2, '.', '')) : '' ?>" inputmode="decimal" placeholder="salaires nets + 13ᵉ + allocations">
                    <div class="form-text">Total des salaires nets versés (certificats de salaire), allocations comprises.</div></div>
                <div class="col-12"><label class="form-label small mb-1">Total des impôts de cette année-là</label>
                    <input name="tax_ref_paid" class="form-control" value="<?= $refTax ? e(number_format($refTax, 2, '.', '')) : '' ?>" inputmode="decimal" placeholder="canton + commune + paroisse + impôt fédéral">
                    <div class="form-text">Additionnez tous les montants de l'avis de taxation (cantonal, communal, paroissial et fédéral direct).</div></div>
                <div class="col-6"><label class="form-label small mb-1">Taux marginal (facultatif, %)</label>
                    <input name="tax_marginal_rate" class="form-control" value="<?= $marginal ? e($marginal) : '' ?>" inputmode="decimal" placeholder="ex. 25">
                    <div class="form-text">Impôt sur le dernier franc gagné : donné par le calculateur officiel. Sert à chiffrer l'économie du 3a.</div></div>
                <div class="col-6"><label class="form-label small mb-1">Plafond 3a (CHF)</label>
                    <input name="pillar3a_max" class="form-control" value="<?= e(number_format($max3a, 0, '.', '')) ?>" inputmode="decimal">
                    <div class="form-text">Salarié avec caisse de pension : 7'258.- en 2026.</div></div>
            </div>
            <button class="btn btn-primary mt-3">Enregistrer</button>
        </form>
    </div></div></div>

    <div class="col-lg-7"><div class="card h-100"><div class="card-body">
        <h2 class="h6">Estimation <?= date('Y') ?></h2>
        <?php if (!$estimate): ?>
            <p class="text-muted">Renseignez votre dernier avis de taxation à gauche<?= $income['used'] > 0 ? '' : ', et vos revenus (postes prévus ou relevés importés)' ?> pour obtenir l'estimation.</p>
        <?php else: ?>
            <table class="table table-sm">
                <tr><td>Taux d'imposition réel (<?= e($refYear) ?>)</td><td class="text-end fw-semibold"><?= number_format($estimate['rate'], 2, '.', "'") ?> %</td></tr>
                <tr><td>Revenus attendus <?= e($income['source']) ?></td><td class="text-end amount"><?= money($income['used']) ?></td></tr>
                <tr class="fw-semibold"><td>Impôts estimés pour <?= date('Y') ?></td><td class="text-end amount"><?= money($estimate['estimate']) ?></td></tr>
                <tr><td>Déjà prévus dans votre plan (postes « Impôts »)</td><td class="text-end amount"><?= money($planned) ?></td></tr>
                <tr class="<?= $estimate['gap'] > 50 ? 'table-danger' : 'table-success' ?>"><td><?= $estimate['gap'] > 50 ? 'Manque à prévoir' : 'Marge' ?></td>
                    <td class="text-end amount fw-semibold"><?= money(abs($estimate['gap'])) ?></td></tr>
            </table>
            <?php if ($estimate['gap'] > 50): ?>
                <div class="alert alert-warning small mb-2">Prévoyez <strong><?= money($estimate['monthly_gap']) ?> de plus par mois</strong> pour les impôts, par exemple en augmentant le poste « Impôts » dans vos <a href="<?= url('planned') ?>">postes prévus</a> (il sera repris dans le lissage).</div>
            <?php elseif ($planned <= 0): ?>
                <div class="alert alert-info small mb-2">Ajoutez un poste « Impôts » dans vos <a href="<?= url('planned') ?>">postes prévus</a> pour que le lissage en tienne compte.</div>
            <?php endif; ?>
            <p class="small text-muted mb-0">Estimation indicative : si vos revenus augmentent nettement, l'impôt augmente un peu plus vite (barème progressif). Un changement de situation (mariage, enfant, déménagement de commune) modifie aussi le taux.</p>
        <?php endif; ?>
    </div></div></div>
</div>

<div class="card mb-4 border-start border-4 border-success"><div class="card-body">
    <h2 class="h6"><i class="bi bi-piggy-bank"></i> Économie d'impôt avec le 3ᵉ pilier A</h2>
    <div class="row g-3">
        <div class="col-md-4"><div class="stat-label">Versé par an (détecté)</div><div class="stat-value"><?= money($pillar) ?></div><small class="text-muted">sur <?= money($max3a) ?> possibles</small></div>
        <div class="col-md-4"><div class="stat-label">Encore déductible</div><div class="stat-value"><?= money(max(0, $max3a - $pillar)) ?></div></div>
        <div class="col-md-4"><div class="stat-label">Économie d'impôt possible</div><div class="stat-value amount-income"><?= $rateFor3a > 0 ? '≈ ' . money($saving3a) : '—' ?></div>
            <small class="text-muted"><?= $marginal > 0 ? "avec votre taux marginal de $marginal %" : ($rateFor3a > 0 ? 'au minimum (taux réel) : plus avec votre taux marginal' : 'renseignez votre avis de taxation') ?></small></div>
    </div>
    <p class="small text-muted mt-2 mb-0">Le 3a est reconnu automatiquement s'il porte « 3a » ou « pilier » dans son nom (poste prévu, objectif d'épargne ou libellé bancaire). Pensez à verser avant fin décembre : seuls les versements de l'année sont déductibles. Pendant le remboursement d'une carte de crédit à 12 %, rembourser la carte reste en général prioritaire.</p>
</div></div>

<div class="card"><div class="card-body">
    <h2 class="h6"><i class="bi bi-list-check"></i> Déductions à ne pas oublier dans la déclaration</h2>
    <p class="small text-muted">Cochez les justificatifs déjà rassemblés : la liste est mémorisée. Les montants et plafonds varient selon le canton : consultez le guide de la déclaration de votre canton.</p>
    <form method="post" action="<?= url('taxes/deductions') ?>">
        <?= csrf_field() ?>
        <div class="row">
        <?php foreach (TaxesController::DEDUCTIONS as $key => [$label, $help]): ?>
            <div class="col-md-6 mb-2"><div class="form-check">
                <input class="form-check-input" type="checkbox" name="deductions[]" value="<?= $key ?>" id="d-<?= $key ?>"<?= checked(in_array($key, $checked, true)) ?>>
                <label class="form-check-label" for="d-<?= $key ?>"><strong><?= e($label) ?></strong><br><small class="text-muted"><?= e($help) ?></small></label>
            </div></div>
        <?php endforeach; ?>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2">
            <small class="text-muted"><?= count($checked) ?> / <?= count(TaxesController::DEDUCTIONS) ?> rassemblé(s)</small>
            <button class="btn btn-outline-primary btn-sm">Enregistrer la liste</button>
        </div>
    </form>
</div></div>
