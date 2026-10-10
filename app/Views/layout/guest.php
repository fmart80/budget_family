<?php require __DIR__ . '/head.php'; ?>
<body class="bg-body-tertiary">
<main class="container py-5" style="max-width: 640px">
    <h1 class="h3 text-center mb-4 text-primary"><i class="bi bi-piggy-bank"></i> <?= e(config('app_name', 'Budget Famille')) ?></h1>
    <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
</body>
</html>
