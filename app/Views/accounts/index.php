<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Comptes</h1>
    <a href="<?= url('accounts/edit') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau compte</a>
</div>
<?php if (!$accounts): ?>
    <div class="alert alert-info">Aucun compte. Créez par exemple « UBS – Compte privé », « BCF – Épargne » et « Espèces ».</div>
<?php else: ?>
<div class="card"><div class="table-responsive">
<table class="table table-hover mb-0">
    <thead><tr><th>Compte</th><th>Type</th><th>Banque / IBAN</th><th class="text-end">Solde</th><th></th></tr></thead>
    <tbody>
    <?php $total = 0; foreach ($accounts as $a): $bal = $balances[$a['id']] ?? 0; if ($a['is_active']) { $total += $bal; } ?>
        <tr class="<?= $a['is_active'] ? '' : 'text-muted' ?>">
            <td><a href="<?= url('transactions', ['account' => $a['id'], 'month' => '']) ?>"><?= e($a['name']) ?></a>
                <?php if (!$a['is_active']): ?><span class="badge bg-secondary">inactif</span><?php endif; ?></td>
            <td><?= e(account_types()[$a['type']] ?? $a['type']) ?></td>
            <td><?= e($a['bank']) ?> <small class="text-muted"><?= e($a['iban']) ?></small></td>
            <td class="text-end amount <?= $bal < 0 ? 'amount-expense' : '' ?>"><?= money($bal) ?></td>
            <td class="text-end text-nowrap">
                <a href="<?= url('accounts/edit', ['id' => $a['id']]) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                <form method="post" action="<?= url('accounts/delete') ?>" class="d-inline" data-confirm="Supprimer ce compte ?">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $a['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr class="fw-semibold"><td colspan="3">Total (comptes actifs)</td><td class="text-end amount"><?= money($total) ?></td><td></td></tr></tfoot>
</table>
</div></div>
<?php endif; ?>
