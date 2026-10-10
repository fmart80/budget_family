<?php
use App\Forecast;
$d = $data;
$hasIrregular = $d['irregular']['expense'] || $d['irregular']['income'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Lissage des dépenses</h1>
    <a href="<?= url('planned') ?>" class="btn btn-primary"><i class="bi bi-list-check"></i> Postes prévus</a>
</div>
<p class="text-muted">Principe : chaque mois, vous mettez de côté 1/12ᵉ de toutes les dépenses qui ne tombent pas chaque mois (assurance ménage, impôts, Serafe, vacances…).
    Quand la facture arrive, l'argent est déjà là : <strong>plus de mauvaise surprise en fin de mois</strong>.</p>

<?php if (!$hasIrregular): ?>
    <div class="alert alert-info">Aucun poste irrégulier pour l'instant. Ajoutez par exemple « Assurance ménage — 2000.- en décembre » dans les <a href="<?= url('planned') ?>">postes prévus</a>.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card card-body h-100 border-start border-4 border-primary">
        <div class="stat-label">À mettre de côté chaque mois</div>
        <div class="stat-value"><?= money($d['provision']) ?></div>
        <small class="text-muted">pour les dépenses irrégulières (<?= money($d['irregular_annual']['expense']) ?> / an)</small></div></div>
    <div class="col-md-4"><div class="card card-body h-100">
        <div class="stat-label">Revenus irréguliers lissés</div>
        <div class="stat-value amount-income"><?= money($d['income_smoothed']) ?></div>
        <small class="text-muted">par mois (13ᵉ salaire, primes… : <?= money($d['irregular_annual']['income']) ?> / an)</small></div></div>
    <div class="col-md-4"><div class="card card-body h-100 <?= $d['margin'] < 0 ? 'border-start border-4 border-danger' : 'border-start border-4 border-success' ?>">
        <div class="stat-label">Marge mensuelle réelle</div>
        <div class="stat-value <?= $d['margin'] < 0 ? 'amount-expense' : 'amount-income' ?>"><?= money($d['margin']) ?></div>
        <small class="text-muted"><?= $d['margin'] < 0 ? 'Déficit structurel : il faut réduire des dépenses ou augmenter les revenus.' : 'Disponible chaque mois pour épargner ou rembourser plus vite.' ?></small></div></div>
</div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h6">Votre mois « lissé »</h2>
    <table class="table table-sm mb-0" style="max-width: 560px">
        <tr><td>Revenus réguliers</td><td class="text-end amount amount-income">+ <?= money($d['regular']['income']) ?></td></tr>
        <tr><td>Revenus irréguliers ÷ 12</td><td class="text-end amount amount-income">+ <?= money($d['income_smoothed']) ?></td></tr>
        <tr><td>Dépenses régulières</td><td class="text-end amount amount-expense">− <?= money($d['regular']['expense']) ?></td></tr>
        <tr><td>Provision dépenses irrégulières</td><td class="text-end amount amount-expense">− <?= money($d['provision']) ?></td></tr>
        <tr><td>Mensualités de dettes</td><td class="text-end amount amount-expense">− <?= money($d['debt_monthly']) ?></td></tr>
        <tr class="fw-semibold"><td>Marge</td><td class="text-end amount"><?= money($d['margin']) ?></td></tr>
    </table>
</div></div>

<div class="row g-4 mb-4">
<?php foreach (['expense' => 'Dépenses irrégulières', 'income' => 'Revenus irréguliers'] as $type => $label): ?>
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <h2 class="h6"><?= $label ?></h2>
        <?php if (!$d['irregular'][$type]): ?><p class="text-muted small mb-0">Aucun.</p><?php else: ?>
        <table class="table table-sm mb-0">
            <thead><tr><th>Poste</th><th>Quand</th><th class="text-end">Par an</th><th class="text-end">÷ 12</th></tr></thead>
            <?php foreach ($d['irregular'][$type] as $i): ?>
                <tr><td><a href="<?= url('planned/edit', ['id' => $i['id']]) ?>" class="text-reset"><?= e($i['name']) ?></a></td><td class="small"><?= e(Forecast::describeMonths($i['month_list'])) ?></td>
                    <td class="text-end amount"><?= money($i['annual'], false) ?></td><td class="text-end amount fw-semibold"><?= money($i['monthly_equivalent'], false) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div></div></div>
<?php endforeach; ?>
</div>

<?php if ($hasIrregular): ?>
<div class="card"><div class="card-body">
    <h2 class="h6"><i class="bi bi-safe"></i> Mise en place d'un compte « réserve factures »</h2>
    <ol class="small">
        <li>Ouvrez un compte épargne séparé (UBS ou BCF) et créez-le dans <a href="<?= url('accounts') ?>">Comptes</a>.</li>
        <?php if ($d['transfer'] >= 0): ?>
            <li>Mettez en place un <strong>ordre permanent de <?= money($d['transfer']) ?> par mois</strong> du compte salaire vers ce compte (provision <?= money($d['provision']) ?> − 13ᵉ salaire lissé <?= money($d['income_smoothed']) ?>).</li>
        <?php else: ?>
            <li>Vos revenus irréguliers couvrent les dépenses irrégulières : vous pouvez reprendre <strong><?= money(-$d['transfer']) ?> par mois</strong> du compte réserve vers le compte salaire.</li>
        <?php endif; ?>
        <?php if ($d['irregular']['income']): ?><li>Versez les <strong>revenus irréguliers</strong> (13ᵉ salaire…) sur ce compte réserve dès réception.</li><?php endif; ?>
        <li>Payez les <strong>dépenses irrégulières</strong> depuis ce compte.</li>
        <?php if ($d['reserve_initial'] > 0): ?><li>Pour démarrer sans trou, il faut <strong><?= money($d['reserve_initial']) ?></strong> sur le compte réserve dès maintenant (une grosse facture arrive avant que la réserve soit constituée).</li><?php endif; ?>
    </ol>
    <div class="table-responsive"><table class="table table-sm small mb-0">
        <thead><tr><th>Mois</th><th class="text-end">Virement</th><th class="text-end">Revenus irréguliers</th><th class="text-end">Factures payées</th><th class="text-end">Solde réserve</th></tr></thead>
        <tbody>
        <?php foreach ($d['reserve'] as $r): ?>
            <tr><td><?= e(month_label($r['month'])) ?></td><td class="text-end amount"><?= money($r['transfer'], false) ?></td>
                <td class="text-end amount amount-income"><?= $r['in'] ? money($r['in'], false) : '' ?></td>
                <td class="text-end amount amount-expense"><?= $r['out'] ? money($r['out'], false) : '' ?></td>
                <td class="text-end amount fw-semibold"><?= money($r['balance'], false) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div></div>
<?php endif; ?>
