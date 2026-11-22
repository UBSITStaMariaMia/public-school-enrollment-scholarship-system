<?php
require_once __DIR__ . '/includes/bootstrap.php';

/* Logout must be submitted through the confirmation form using POST. */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/* Validate the CSRF token generated in the logout confirmation form. */
$submittedToken = $_POST['csrf_token'] ?? '';

if (!is_string($submittedToken) || !hash_equals(csrf_token(), $submittedToken)) {
    http_response_code(403);
    exit('Invalid request. Please return to the portal and try again.');
}

/* Clear session data and invalidate the session cookie. */
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: ' . BASE_URL . '/index.php');
exit;
