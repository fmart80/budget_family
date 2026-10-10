<?php
$newCount = count(array_filter($rows, fn ($r) => !$r['duplicate']));
$isCsv = $import['format'] === 'csv';
$colSelect = function (string $name, ?int $value, bool $optional = true) use ($import): string {
    $html = '<select name="' . $name . '" class="form-select form-select-sm">' . ($optional ? '<option value="">—</option>' : '');
    foreach ($import['header'] as $i => $h) {
        $html .= '<option value="' . $i . '"' . selected($i, $value ?? '') . '>' . e($h !== '' ? $h : 'Colonne ' . ($i + 1)) . '</option>';
    }
    return $html . '</select>';
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h1 class="h3 mb-0">Aperçu de l'import</h1>
        <span class="text-muted"><?= e($import['filename']) ?> → <?= e($account['name'] ?? '?') ?> · <?= $isCsv ? 'CSV' : 'ISO 20022 camt' ?></span></div>
    <a href="<?= url('import') ?>" class="btn btn-outline-secondary">Recommencer</a>
</div>
<?php if ($ibanWarning): ?><div class="alert alert-warning"><?= e($ibanWarning) ?></div><?php endif; ?>

<?php if ($isCsv): ?>
<form method="post" action="<?= url('import/preview') ?>" class="card card-body mb-3">
    <?= csrf_field() ?>
    <h2 class="h6">Correspondance des colonnes</h2>
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label small mb-1">Date</label><?= $colSelect('map_date', $import['mapping']['date'], false) ?></div>
        <div class="col-md-2"><label class="form-label small mb-1">Montant (+/−)</label><?= $colSelect('map_amount', $import['mapping']['amount']) ?></div>
        <div class="col-md-2"><label class="form-label small mb-1">ou Débit</label><?= $colSelect('map_debit', $import['mapping']['debit']) ?></div>
        <div class="col-md-2"><label class="form-label small mb-1">et Crédit</label><?= $colSelect('map_credit', $import['mapping']['credit']) ?></div>
        <div class="col-md-3"><label class="form-label small mb-1">Libellé (Ctrl+clic pour plusieurs)</label>
            <select name="map_description[]" class="form-select form-select-sm" multiple size="3">
                <?php foreach ($import['header'] as $i => $h): ?><option value="<?= $i ?>"<?= in_array($i, $import['mapping']['description'], true) ? ' selected' : '' ?>><?= e($h !== '' ? $h : 'Colonne ' . ($i + 1)) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100">Appliquer</button></div>
    </div>
    <div class="form-text">Renseignez soit une colonne « Montant » signée, soit les colonnes « Débit » et « Crédit ».</div>
</form>
<?php endif; ?>

<?php if (!$rows): ?>
    <div class="alert alert-warning">Aucune opération reconnue dans ce fichier.<?= $isCsv ? ' Vérifiez la correspondance des colonnes ci-dessus.' : '' ?></div>
<?php else: ?>
<form method="post" action="<?= url('import/confirm') ?>">
    <?= csrf_field() ?>
    <div class="card">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><strong><?= $newCount ?></strong> nouvelle(s) opération(s) sur <?= count($rows) ?><?= $newCount < count($rows) ? ' · ' . (count($rows) - $newCount) . ' déjà importée(s)' : '' ?></span>
            <button class="btn btn-success" <?= $newCount ? '' : 'disabled' ?>><i class="bi bi-check2-all"></i> Importer la sélection</button>
        </div>
        <div class="table-responsive">
        <table class="table table-sm table-import mb-0">
            <thead><tr><th><input type="checkbox" class="form-check-input" data-check-all=".imp-check" checked></th><th>Date</th><th>Libellé</th><th class="text-end">Montant</th><th style="width: 220px">Catégorie</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $r): ?>
                <tr class="<?= $r['duplicate'] ? 'text-muted' : '' ?>">
                    <td><?php if ($r['duplicate']): ?><span class="badge text-bg-secondary" title="Déjà importée">doublon</span>
                        <?php else: ?><input type="checkbox" class="form-check-input imp-check" name="include[]" value="<?= $i ?>" checked><?php endif; ?></td>
                    <td class="text-nowrap"><?= date_ch($r['date']) ?></td>
                    <td><?= e($r['description']) ?></td>
                    <td class="text-end amount amount-<?= $r['type'] ?>"><?= money($r['amount'], false) ?></td>
                    <td><?php if (!$r['duplicate']): ?>
                        <select name="category[<?= $i ?>]" class="form-select form-select-sm">
                            <option value="">— à classer —</option>
                            <?php foreach ($categories as $c): if ($c['type'] !== $r['type']) { continue; } ?>
                                <option value="<?= $c['id'] ?>"<?= selected($c['id'], $r['category_id'] ?? '') ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</form>
<?php endif; ?>
