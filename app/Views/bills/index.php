<?php $today = date('Y-m-d'); $soon = date('Y-m-d', strtotime('+7 days')); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Factures</h1>
    <a href="<?= url('bills/edit') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle facture</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">En retard</div>
        <div class="stat-value <?= $summary['overdue_count'] ? 'amount-expense' : '' ?>"><?= money($summary['overdue']) ?></div>
        <small class="text-muted"><?= (int) $summary['overdue_count'] ?> facture(s)</small></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">À payer dans les 30 jours</div><div class="stat-value"><?= money($summary['next30']) ?></div></div></div>
    <div class="col-md-4"><div class="card card-body"><div class="stat-label">Total à payer</div><div class="stat-value"><?= money($summary['total']) ?></div></div></div>
</div>

<ul class="nav nav-tabs mb-0">
    <li class="nav-item"><a class="nav-link<?= $tab === 'due' ? ' active' : '' ?>" href="<?= url('bills') ?>">À payer</a></li>
    <li class="nav-item"><a class="nav-link<?= $tab === 'paid' ? ' active' : '' ?>" href="<?= url('bills', ['tab' => 'paid']) ?>">Payées</a></li>
</ul>
<div class="card rounded-top-0">
<?php if (!$bills): ?>
    <div class="card-body text-muted">Aucune facture.</div>
<?php else: ?>
<div class="table-responsive">
<table class="table table-hover mb-0">
    <thead><tr><th><?= $tab === 'paid' ? 'Payée le' : 'Échéance' ?></th><th>Facture</th><th>Catégorie</th><th>Récurrence</th><th class="text-end">Montant</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($bills as $b):
        $badge = '';
        if ($b['status'] === 'due' && $b['due_date'] < $today) { $badge = '<span class="badge text-bg-danger">en retard</span>'; }
        elseif ($b['status'] === 'due' && $b['due_date'] <= $soon) { $badge = '<span class="badge text-bg-warning">bientôt</span>'; }
    ?>
        <tr>
            <td class="text-nowrap"><?= date_ch($tab === 'paid' ? $b['paid_at'] : $b['due_date']) ?> <?= $badge ?></td>
            <td><strong><?= e($b['name']) ?></strong>
                <?php if ($b['attachment']): ?><a href="<?= url('bills/file', ['id' => $b['id']]) ?>" target="_blank" title="<?= e($b['attachment_name']) ?>"><i class="bi bi-paperclip"></i></a><?php endif; ?>
                <br><small class="text-muted"><?= e($b['payee']) ?><?= $b['reference'] ? ' · Réf. ' . e($b['reference']) : '' ?></small></td>
            <td><?php if ($b['category_name']): ?><span class="cat-dot" style="background: <?= e($b['category_color']) ?>"></span><?= e($b['category_name']) ?><?php endif; ?></td>
            <td><?= e(recurrence_labels()[$b['recurrence']]) ?></td>
            <td class="text-end amount"><?= money($b['amount']) ?></td>
            <td class="text-end text-nowrap">
                <?php if ($b['status'] === 'due'): ?>
                    <a href="<?= url('bills/pay', ['id' => $b['id']]) ?>" class="btn btn-sm btn-success"><i class="bi bi-check2"></i> Payer</a>
                <?php else: ?>
                    <form method="post" action="<?= url('bills/unpay') ?>" class="d-inline" data-confirm="Annuler ce paiement ? La transaction associée sera supprimée.">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <button class="btn btn-sm btn-outline-secondary" title="Annuler le paiement"><i class="bi bi-arrow-counterclockwise"></i></button>
                    </form>
                <?php endif; ?>
                <a href="<?= url('bills/edit', ['id' => $b['id']]) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                <form method="post" action="<?= url('bills/delete') ?>" class="d-inline" data-confirm="Supprimer cette facture ?">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $b['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
</div>
