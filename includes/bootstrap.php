<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('bcnchs_rebuild_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (!empty($_SESSION['user']['must_change_password'])) {
    $requestScript = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    $projectRoot = dirname(__DIR__);
    $passwordPath = $_SESSION['user']['role_name'] === 'Student' ? '/student/change_password.php' : '/change_password.php';
    if (!in_array($requestScript, [
        realpath($projectRoot . $passwordPath),
        realpath($projectRoot . '/logout.php'),
    ], true)) {
        redirect($passwordPath);
    }
}
