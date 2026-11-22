<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!current_user()) {
    redirect('/index.php');
}

$submittedToken = $_POST['csrf_token'] ?? null;

if (!is_string($submittedToken) || !verify_csrf_token($submittedToken)) {
    http_response_code(403);
    exit('Invalid request. Please return to the portal and try again.');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?: 'Lax',
    ]);
}

session_destroy();

header('Cache-Control: no-store');
redirect('/index.php');
