<h1 class="h3 mb-4">Import bancaire</h1>
<div class="row g-4">
    <div class="col-lg-6"><div class="card"><div class="card-body">
        <form method="post" action="<?= url('import/upload') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Compte concerné</label>
                <select name="account_id" class="form-select" required>
                    <?php foreach ($accounts as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['name']) ?><?= $a['bank'] ? ' (' . e($a['bank']) . ')' : '' ?></option><?php endforeach; ?>
                </select></div>
            <div class="mb-3"><label class="form-label">Fichier du relevé</label>
                <input type="file" name="file" class="form-control" accept=".csv,.txt,.xml,.zip" required>
                <div class="form-text">Formats acceptés : CSV, XML ISO 20022 (camt.053 / camt.054), ZIP contenant des fichiers camt.</div></div>
            <button class="btn btn-primary"><i class="bi bi-eye"></i> Analyser le fichier</button>
        </form>
        <p class="small text-muted mt-3 mb-0">Rien n'est enregistré avant la validation de l'aperçu. Les opérations déjà importées sont détectées et ignorées : vous pouvez réimporter un relevé qui chevauche le précédent.</p>
    </div></div></div>
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6"><i class="bi bi-bank"></i> UBS (e-banking)</h2>
            <p class="small mb-0">Ouvrez le compte, affichez les transactions de la période voulue, puis utilisez la fonction d'export (icône de téléchargement) au format <strong>CSV</strong>. Les colonnes Date de comptabilisation, Débit, Crédit et Description sont reconnues automatiquement, en français comme en allemand. L'export <strong>camt.053</strong> fonctionne aussi.</p>
        </div></div>
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6"><i class="bi bi-bank"></i> BCF (e-banking)</h2>
            <p class="small mb-0">Dans le détail du compte, exportez les mouvements au format <strong>CSV</strong> ou téléchargez le relevé électronique <strong>camt.053</strong> (XML ISO 20022), le format le plus fiable. Pour un CSV, vérifiez à l'étape suivante que les colonnes date, montant et libellé sont bien reconnues et corrigez-les si besoin.</p>
        </div></div>
        <div class="card"><div class="card-body">
            <h2 class="h6"><i class="bi bi-magic"></i> Catégorisation automatique</h2>
            <p class="small mb-0">Définissez des <a href="<?= url('categories') ?>">règles par mot-clé</a> (« Coop » → Alimentation, « Swisscom » → Télécom…) : elles sont appliquées à chaque import.</p>
        </div></div>
    </div>
</div>
