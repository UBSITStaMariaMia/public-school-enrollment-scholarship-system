<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('/login.php');
    }
}

function require_role(string ...$allowedRoles): void
{
    require_login();
    $role = current_user()['role_name'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submittedToken);
}


function dashboard_path_for_role(string $role): string
{
    return match ($role) {
        'Student' => '/student/dashboard.php',
        'System Administrator' => '/admin/dashboard.php',
        'School Registrar' => '/registrar/dashboard.php',
        default => '/staff/dashboard.php',
    };
}
