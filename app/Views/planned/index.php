<?php use App\Forecast; ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Postes prévus</h1>
    <div class="d-flex gap-2">
        <a href="<?= url('planned/edit', ['type' => 'income']) ?>" class="btn btn-success"><i class="bi bi-plus-lg"></i> Revenu prévu</a>
        <a href="<?= url('planned/edit', ['type' => 'expense']) ?>" class="btn btn-danger"><i class="bi bi-plus-lg"></i> Dépense prévue</a>
    </div>
</div>
<p class="text-muted">Listez ici <strong>tout ce qui revient</strong> dans l'année : salaires, 13ᵉ salaire, loyer, LAMal, impôts, assurance ménage, Serafe, vacances, cadeaux de Noël…
    Ces postes alimentent les <a href="<?= url('forecast') ?>">prévisions sur 12 mois</a> et le <a href="<?= url('forecast/smoothing') ?>">lissage</a>.
    Les mensualités des <a href="<?= url('debts') ?>">dettes</a> et les <a href="<?= url('subscriptions') ?>">abonnements</a> sont ajoutés automatiquement : ne les saisissez pas ici. <a href="<?= url('guide') ?>">Voir le guide et des exemples</a>.</p>

<?php if ($recurringBills): ?>
<div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><?= count($recurringBills) ?> facture(s) récurrente(s) ne sont pas encore dans le plan : <?= e(implode(', ', array_column($recurringBills, 'name'))) ?>.</span>
    <form method="post" action="<?= url('planned/import_bills') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-primary">Les ajouter au plan</button></form>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Revenus prévus / an</div><div class="stat-value amount-income"><?= money($totals['income']) ?></div><small class="text-muted"><?= money($totals['income'] / 12) ?> / mois en moyenne</small></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Dépenses prévues / an</div><div class="stat-value amount-expense"><?= money($totals['expense']) ?></div><small class="text-muted"><?= money($totals['expense'] / 12) ?> / mois en moyenne</small></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Solde annuel prévu (hors dettes)</div><div class="stat-value <?= $totals['income'] < $totals['expense'] ? 'amount-expense' : '' ?>"><?= money($totals['income'] - $totals['expense']) ?></div><small class="text-muted"><?= money(($totals['income'] - $totals['expense']) / 12) ?> / mois</small></div></div>
</div>

<?php foreach (['income' => 'Revenus prévus', 'expense' => 'Dépenses prévues'] as $type => $label):
    $list = array_filter($items, fn ($i) => $i['type'] === $type); ?>
<div class="card mb-4">
    <div class="card-header bg-white"><strong><?= $label ?></strong></div>
    <?php if (!$list): ?><div class="card-body text-muted small">Aucun poste.</div><?php else: ?>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Poste</th><th>Catégorie</th><th>Quand</th><th class="text-end">Montant</th><th class="text-end">Par an</th><th class="text-end">Équivalent / mois</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $i): ?>
            <tr class="<?= $i['is_active'] ? '' : 'text-muted text-decoration-line-through' ?>">
                <td><?= e($i['name']) ?><?php if ($i['notes']): ?><br><small class="text-muted"><?= e($i['notes']) ?></small><?php endif; ?></td>
                <td><?php if ($i['category_name']): ?><span class="cat-dot" style="background: <?= e($i['category_color']) ?>"></span><?= e($i['category_name']) ?><?php endif; ?></td>
                <td><?= e(Forecast::describeMonths($i['month_list'])) ?><?= !$i['is_regular'] ? ' <span class="badge text-bg-warning">irrégulier</span>' : '' ?>
                    <?php if ($i['start_month'] || $i['end_month']): ?><br><small class="text-muted"><?= $i['start_month'] ? 'dès ' . e(month_label($i['start_month'])) : '' ?> <?= $i['end_month'] ? 'jusqu\'à ' . e(month_label($i['end_month'])) : '' ?></small><?php endif; ?></td>
                <td class="text-end amount"><?= money($i['amount']) ?></td>
                <td class="text-end amount"><?= money($i['annual'], false) ?></td>
                <td class="text-end amount"><?= money($i['monthly_equivalent'], false) ?></td>
                <td class="text-end text-nowrap">
                    <a href="<?= url('planned/edit', ['id' => $i['id']]) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <form method="post" action="<?= url('planned/delete') ?>" class="d-inline" data-confirm="Supprimer ce poste ?">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $i['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
