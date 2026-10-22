<?php
declare(strict_types=1);

use App\Database;

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function db(): PDO
{
    return Database::get();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Montant au format suisse : CHF 1'234.50 */
function money(float|string|null $amount, bool $withCurrency = true): string
{
    $formatted = number_format((float) $amount, 2, '.', "'");
    return $withCurrency ? config('currency', 'CHF') . ' ' . $formatted : $formatted;
}

/** Date au format suisse : 31.12.2026 */
function date_ch(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : '';
}

function month_label(string $month): string
{
    static $names = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
        'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    [$y, $m] = array_map('intval', explode('-', $month));
    return ucfirst($names[$m] ?? '') . ' ' . $y;
}

function valid_month(?string $month): string
{
    return ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) ? $month : date('Y-m');
}

function valid_date(?string $date): ?string
{
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $date));
    return checkdate($m, $d, $y) ? $date : null;
}

/** Convertit une saisie utilisateur ("1'234.50", "1234,5") en nombre. */
function parse_amount(mixed $value): float
{
    $s = str_replace(["'", '’', ' ', "\u{00A0}"], '', trim((string) $value));
    if (str_contains($s, ',') && !str_contains($s, '.')) {
        $s = str_replace(',', '.', $s);
    } else {
        $s = str_replace(',', '', $s);
    }
    return is_numeric($s) ? round((float) $s, 2) : 0.0;
}

function url(string $route = 'dashboard', array $params = []): string
{
    $query = array_merge(['p' => $route], $params);
    return 'index.php?' . http_build_query($query);
}

function redirect(string $route, array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Session expirée ou requête invalide. Rechargez la page et réessayez.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, mixed $default = ''): mixed
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function int_input(string $key): ?int
{
    $v = input($key, '');
    return ($v === '' || $v === null || !ctype_digit((string) $v)) ? null : (int) $v;
}

function view(string $__template, array $__data = [], string $__layout = 'layout/main'): void
{
    extract($__data, EXTR_SKIP);
    ob_start();
    require APP_PATH . '/Views/' . $__template . '.php';
    $content = ob_get_clean();
    require APP_PATH . '/Views/' . $__layout . '.php';
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $value): string
{
    return $value ? ' checked' : '';
}

function current_user(): ?array
{
    return App\Auth::user();
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function abort(int $code, string $message = 'Page introuvable'): never
{
    http_response_code($code);
    view('layout/error', ['code' => $code, 'message' => $message]);
    exit;
}

/** Listes de référence réutilisées dans les formulaires. */
function account_types(): array
{
    return ['courant' => 'Compte courant', 'epargne' => 'Compte épargne', 'especes' => 'Espèces',
        'carte' => 'Carte de crédit', 'autre' => 'Autre'];
}

function recurrence_labels(): array
{
    return ['none' => 'Unique', 'monthly' => 'Mensuelle', 'quarterly' => 'Trimestrielle',
        'semiannual' => 'Semestrielle', 'yearly' => 'Annuelle'];
}

function all_accounts(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM accounts' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name';
    return db()->query($sql)->fetchAll();
}

function all_categories(?string $type = null): array
{
    if ($type) {
        $stmt = db()->prepare('SELECT * FROM categories WHERE type = ? ORDER BY name');
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }
    return db()->query('SELECT * FROM categories ORDER BY type DESC, name')->fetchAll();
}

/**
 * Vérifie la version de PHP du serveur : faille connue non corrigée ou fin de support.
 * À tenir à jour lors des prochaines alertes de sécurité PHP.
 *
 * @return array{level: string, message: string}|null
 */
function php_security_status(?string $version = null, ?string $today = null): ?array
{
    $version ??= PHP_VERSION;
    $today ??= date('Y-m-d');
    // Versions corrigeant PHP2Shell (CVE-2026-109618 / CVE-2026-109619), publiées le 21.10.2026.
    $fixed = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];
    // Fin des correctifs de sécurité de chaque branche.
    $endOfLife = ['8.2' => '2026-12-31', '8.3' => '2027-12-31', '8.4' => '2028-12-31', '8.5' => '2029-12-31'];

    $branch = implode('.', array_slice(explode('.', $version), 0, 2));
    if (!isset($fixed[$branch]) || $today > $endOfLife[$branch]) {
        return ['level' => 'danger', 'message' => "PHP $version ne reçoit plus de correctifs de sécurité. "
            . 'Choisissez une version récente (8.4 ou plus) dans Plesk → Paramètres PHP.'];
    }
    if (version_compare($version, $fixed[$branch], '<')) {
        return ['level' => 'danger', 'message' => "PHP $version est vulnérable à la faille critique PHP2Shell "
            . "(CVE-2026-109618 / CVE-2026-109619). Mettez à jour vers PHP {$fixed[$branch]} ou plus dans Plesk "
            . '→ Paramètres PHP, ou demandez la mise à jour à votre hébergeur.'];
    }
    $daysLeft = (int) ((strtotime($endOfLife[$branch]) - strtotime($today)) / 86400);
    if ($daysLeft <= 90) {
        return ['level' => 'warning', 'message' => "PHP $branch ne recevra plus de correctifs de sécurité après le "
            . date_ch($endOfLife[$branch]) . ". Prévoyez de passer à PHP 8.4 dans Plesk → Paramètres PHP."];
    }
    return null;
}

/** Réglage enregistré en base (table settings), ou valeur par défaut. */
function setting(string $name, mixed $default = null): mixed
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT name, value FROM settings') as $row) {
                $cache[$row['name']] = $row['value'];
            }
        } catch (\PDOException) {
            // Table pas encore créée (migration en attente).
        }
    }
    return array_key_exists($name, $cache) && $cache[$name] !== '' ? $cache[$name] : $default;
}

function save_setting(string $name, ?string $value): void
{
    db()->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
        ->execute([$name, $value]);
}
