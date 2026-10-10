<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Auth;
use App\Database;

$route = (string) ($_GET['p'] ?? 'dashboard');
if (!preg_match('#^[a-z_]+(/[a-z_]+)?$#', $route)) {
    $route = 'dashboard';
}

// 1) Configuration absente : afficher les instructions d'installation.
if ($GLOBALS['config'] === null) {
    view('setup/no_config', [], 'layout/guest');
    exit;
}

// 2) Base de données inaccessible.
try {
    Database::get();
} catch (PDOException $ex) {
    http_response_code(500);
    view('setup/db_error', ['error' => config('debug') ? $ex->getMessage() : null], 'layout/guest');
    exit;
}

// 3) Installation (tables + premier administrateur).
$setup = new App\Controllers\SetupController();
if (!Database::isInstalled() || !$setup->hasUsers()) {
    $setup->run();
    exit;
}
Database::migrate();

$routes = [
    'login'                => ['AuthController', 'login'],
    'logout'               => ['AuthController', 'logout'],
    'profile'              => ['AuthController', 'profile'],

    'dashboard'            => ['DashboardController', 'index'],

    'accounts'             => ['AccountsController', 'index'],
    'accounts/edit'        => ['AccountsController', 'edit'],
    'accounts/save'        => ['AccountsController', 'save'],
    'accounts/delete'      => ['AccountsController', 'delete'],

    'categories'           => ['CategoriesController', 'index'],
    'categories/edit'      => ['CategoriesController', 'edit'],
    'categories/save'      => ['CategoriesController', 'save'],
    'categories/delete'    => ['CategoriesController', 'delete'],
    'rules/save'           => ['CategoriesController', 'saveRule'],
    'rules/delete'         => ['CategoriesController', 'deleteRule'],
    'rules/apply'          => ['CategoriesController', 'applyRules'],

    'transactions'         => ['TransactionsController', 'index'],
    'transactions/edit'    => ['TransactionsController', 'edit'],
    'transactions/save'    => ['TransactionsController', 'save'],
    'transactions/delete'  => ['TransactionsController', 'delete'],
    'transactions/bulk'    => ['TransactionsController', 'bulk'],
    'transactions/export'  => ['TransactionsController', 'export'],

    'import'               => ['ImportController', 'index'],
    'import/upload'        => ['ImportController', 'upload'],
    'import/preview'       => ['ImportController', 'preview'],
    'import/confirm'       => ['ImportController', 'confirm'],

    'budgets'              => ['BudgetsController', 'index'],
    'budgets/save'         => ['BudgetsController', 'save'],
    'budgets/copy'         => ['BudgetsController', 'copy'],

    'planned'              => ['PlannedController', 'index'],
    'planned/edit'         => ['PlannedController', 'edit'],
    'planned/save'         => ['PlannedController', 'save'],
    'planned/delete'       => ['PlannedController', 'delete'],
    'planned/import_bills' => ['PlannedController', 'importBills'],

    'forecast'             => ['ForecastController', 'index'],
    'forecast/smoothing'   => ['ForecastController', 'smoothing'],
    'forecast/analysis'    => ['ForecastController', 'analysis'],
    'forecast/budget_from_average' => ['ForecastController', 'budgetFromAverage'],
    'forecast/plan_from_average'   => ['ForecastController', 'planFromAverage'],

    'guide'                => ['GuideController', 'index'],

    'subscriptions'        => ['SubscriptionsController', 'index'],
    'subscriptions/edit'   => ['SubscriptionsController', 'edit'],
    'subscriptions/save'   => ['SubscriptionsController', 'save'],
    'subscriptions/delete' => ['SubscriptionsController', 'delete'],
    'subscriptions/status' => ['SubscriptionsController', 'status'],
    'subscriptions/update_amount' => ['SubscriptionsController', 'updateAmount'],
    'subscriptions/ignore' => ['SubscriptionsController', 'ignore'],

    'bills'                => ['BillsController', 'index'],
    'bills/edit'           => ['BillsController', 'edit'],
    'bills/save'           => ['BillsController', 'save'],
    'bills/delete'         => ['BillsController', 'delete'],
    'bills/pay'            => ['BillsController', 'pay'],
    'bills/unpay'          => ['BillsController', 'unpay'],
    'bills/file'           => ['BillsController', 'file'],

    'debts'                => ['DebtsController', 'index'],
    'debts/show'           => ['DebtsController', 'show'],
    'debts/edit'           => ['DebtsController', 'edit'],
    'debts/save'           => ['DebtsController', 'save'],
    'debts/delete'         => ['DebtsController', 'delete'],
    'debts/payment'        => ['DebtsController', 'payment'],
    'debts/payment_delete' => ['DebtsController', 'deletePayment'],

    'savings'              => ['SavingsController', 'index'],
    'savings/show'         => ['SavingsController', 'show'],
    'savings/edit'         => ['SavingsController', 'edit'],
    'savings/save'         => ['SavingsController', 'save'],
    'savings/delete'       => ['SavingsController', 'delete'],
    'savings/contribute'   => ['SavingsController', 'contribute'],
    'savings/contribution_delete' => ['SavingsController', 'deleteContribution'],

    'users'                => ['UsersController', 'index'],
    'users/edit'           => ['UsersController', 'edit'],
    'users/save'           => ['UsersController', 'save'],
    'users/toggle'         => ['UsersController', 'toggle'],
];

if (!isset($routes[$route])) {
    abort(404);
}

if ($route !== 'login' && Auth::user() === null) {
    redirect('login');
}
if (str_starts_with($route, 'users') && !is_admin()) {
    abort(403, 'Accès réservé aux administrateurs.');
}
if (is_post()) {
    verify_csrf();
}

[$controller, $method] = $routes[$route];
$class = 'App\\Controllers\\' . $controller;
(new $class())->$method();
