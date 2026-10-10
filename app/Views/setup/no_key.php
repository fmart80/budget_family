<div class="card"><div class="card-body">
    <h2 class="h5">Clé d'installation manquante</h2>
    <p>Ajoutez une clé secrète dans <code>config/config.php</code>, par exemple :</p>
    <pre class="bg-light p-2 rounded">'setup_key' => '<?= e(bin2hex(random_bytes(12))) ?>',</pre>
    <p class="mb-0">Elle empêche une personne qui découvrirait votre site d'en prendre le contrôle avant vous.</p>
</div></div>
