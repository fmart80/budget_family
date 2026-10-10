<h1 class="h3 mb-4"><?= $category['id'] ? 'Modifier la catégorie' : 'Nouvelle catégorie' ?></h1>
<div class="card" style="max-width: 520px"><div class="card-body">
<form method="post" action="<?= url('categories/save') ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($category['id']) ?>">
    <div class="mb-3"><label class="form-label">Nom</label>
        <input name="name" class="form-control" value="<?= e($category['name']) ?>" required></div>
    <div class="row">
        <div class="col-8 mb-3"><label class="form-label">Type</label>
            <select name="type" class="form-select">
                <option value="expense"<?= selected('expense', $category['type']) ?>>Dépense</option>
                <option value="income"<?= selected('income', $category['type']) ?>>Revenu</option>
            </select></div>
        <div class="col-4 mb-3"><label class="form-label">Couleur</label>
            <input type="color" name="color" class="form-control form-control-color w-100" value="<?= e($category['color']) ?>"></div>
    </div>
    <button class="btn btn-primary">Enregistrer</button>
    <a href="<?= url('categories') ?>" class="btn btn-link">Annuler</a>
</form>
</div></div>
