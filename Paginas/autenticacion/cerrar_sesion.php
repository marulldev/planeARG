<?php
require_once __DIR__ . '/../includes/sesion.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /');
    exit;
}

$tokenEnviado = (string) ($_POST['csrf_token'] ?? '');
if (!hash_equals($_SESSION['csrf_auth'], $tokenEnviado)) {
    http_response_code(403);
    exit('La sesión venció. Volvé al sitio e intentá nuevamente.');
}

$_SESSION = [];
session_regenerate_id(true);
$_SESSION['csrf_auth'] = bin2hex(random_bytes(32));

header('Location: /');
exit;
