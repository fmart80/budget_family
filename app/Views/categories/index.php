<h1 class="h3 mb-4">Catégories & règles</h1>
<div class="row g-4">
<?php foreach (['expense' => 'Dépenses', 'income' => 'Revenus'] as $type => $label): ?>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <strong><?= $label ?></strong>
                <a href="<?= url('categories/edit', ['type' => $type]) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i></a>
            </div>
            <ul class="list-group list-group-flush">
            <?php foreach ($categories as $c): if ($c['type'] !== $type) { continue; } ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><span class="cat-dot" style="background: <?= e($c['color']) ?>"></span><?= e($c['name']) ?>
                        <small class="text-muted">(<?= $usage[$c['id']] ?? 0 ?>)</small></span>
                    <span class="text-nowrap">
                        <a href="<?= url('categories/edit', ['id' => $c['id']]) ?>" class="btn btn-sm btn-link p-0 me-2"><i class="bi bi-pencil"></i></a>
                        <form method="post" action="<?= url('categories/delete') ?>" class="d-inline" data-confirm="Supprimer cette catégorie ?">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button class="btn btn-sm btn-link p-0 text-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </span>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endforeach; ?>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white"><strong>Règles de catégorisation automatique</strong></div>
            <div class="card-body">
                <p class="small text-muted">Si le libellé d'une transaction importée contient le mot-clé, la catégorie est attribuée automatiquement (ex. « Migros » → Alimentation, « CSS » → Assurance maladie).</p>
                <form method="post" action="<?= url('rules/save') ?>" class="row g-2 mb-3">
                    <?= csrf_field() ?>
                    <div class="col-5"><input name="keyword" class="form-control form-control-sm" placeholder="Mot-clé" required></div>
                    <div class="col-5"><select name="category_id" class="form-select form-select-sm" required>
                        <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="col-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button></div>
                </form>
                <ul class="list-group list-group-flush mb-3">
                <?php foreach ($rules as $r): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span><code><?= e($r['keyword']) ?></code> → <span class="cat-dot" style="background: <?= e($r['color']) ?>"></span><?= e($r['category_name']) ?></span>
                        <form method="post" action="<?= url('rules/delete') ?>">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button class="btn btn-sm btn-link p-0 text-danger"><i class="bi bi-x-lg"></i></button>
                        </form>
                    </li>
                <?php endforeach; ?>
                </ul>
                <form method="post" action="<?= url('rules/apply') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-primary w-100" <?= $uncategorized ? '' : 'disabled' ?>>
                        Appliquer aux <?= $uncategorized ?> transaction(s) sans catégorie
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
