<?php require __DIR__ . '/head.php';
$current = (string) ($_GET['p'] ?? 'dashboard');
$active = fn (string $prefix): string => (str_starts_with($current, $prefix) ? ' active' : '');
$user = current_user();
?>
<body>
<nav class="navbar navbar-expand-xl navbar-dark bg-primary shadow-sm">
    <div class="container-xl">
        <a class="navbar-brand fw-semibold" href="<?= url('dashboard') ?>"><i class="bi bi-piggy-bank"></i> <?= e(config('app_name', 'Budget Famille')) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link<?= $active('dashboard') ?>" href="<?= url('dashboard') ?>"><i class="bi bi-speedometer2"></i> Accueil</a></li>
                <li class="nav-item"><a class="nav-link<?= $active('transactions') ?>" href="<?= url('transactions') ?>"><i class="bi bi-arrow-left-right"></i> Transactions</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle<?= $active('budgets') . $active('planned') . $active('forecast') ?>" href="#" data-bs-toggle="dropdown"><i class="bi bi-clipboard-data"></i> Budget</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= url('budgets') ?>"><i class="bi bi-clipboard-data"></i> Budget du mois</a></li>
                        <li><a class="dropdown-item" href="<?= url('planned') ?>"><i class="bi bi-list-check"></i> Postes prévus (plan annuel)</a></li>
                        <li><a class="dropdown-item" href="<?= url('forecast') ?>"><i class="bi bi-graph-up-arrow"></i> Prévisions 12 mois</a></li>
                        <li><a class="dropdown-item" href="<?= url('forecast/smoothing') ?>"><i class="bi bi-distribute-vertical"></i> Lissage des dépenses</a></li>
                        <li><a class="dropdown-item" href="<?= url('forecast/analysis') ?>"><i class="bi bi-bar-chart"></i> Analyse 3 / 6 / 12 mois</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link<?= $active('bills') ?>" href="<?= url('bills') ?>"><i class="bi bi-receipt"></i> Factures</a></li>
                <li class="nav-item"><a class="nav-link<?= $active('subscriptions') ?>" href="<?= url('subscriptions') ?>"><i class="bi bi-collection-play"></i> Abonnements</a></li>
                <li class="nav-item"><a class="nav-link<?= $active('debts') ?>" href="<?= url('debts') ?>"><i class="bi bi-credit-card-2-back"></i> Dettes</a></li>
                <li class="nav-item"><a class="nav-link<?= $active('savings') ?>" href="<?= url('savings') ?>"><i class="bi bi-safe"></i> Épargne</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle<?= $active('accounts') . $active('categories') . $active('import') . $active('users') . $active('guide') ?>" href="#" data-bs-toggle="dropdown">Plus</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= url('import') ?>"><i class="bi bi-upload"></i> Import bancaire</a></li>
                        <li><a class="dropdown-item" href="<?= url('accounts') ?>"><i class="bi bi-bank"></i> Comptes</a></li>
                        <li><a class="dropdown-item" href="<?= url('categories') ?>"><i class="bi bi-tags"></i> Catégories & règles</a></li>
                        <li><a class="dropdown-item" href="<?= url('guide') ?>"><i class="bi bi-question-circle"></i> Guide & exemples</a></li>
                        <?php if (is_admin()): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= url('users') ?>"><i class="bi bi-people"></i> Utilisateurs</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="bi bi-person-circle"></i> <?= e($user['name'] ?? '') ?></a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= url('profile') ?>"><i class="bi bi-key"></i> Mon profil</a></li>
                        <li>
                            <form method="post" action="<?= url('logout') ?>">
                                <?= csrf_field() ?>
                                <button class="dropdown-item"><i class="bi bi-box-arrow-right"></i> Déconnexion</button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container-xl py-4">
    <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
