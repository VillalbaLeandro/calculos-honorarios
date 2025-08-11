<?php
declare(strict_types=1);

// No rompas el JSON con warnings/notices
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

// Cookies de sesión válidas para https del túnel
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

// Lee body JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || !array_key_exists('password', $data)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
  exit;
}

// Asegurá la ruta del config al mismo directorio del script
$cfgPath = __DIR__ . '/config.json';
$cfgRaw  = @file_get_contents($cfgPath);
if ($cfgRaw === false) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'No se pudo leer config.json']);
  exit;
}

$config = json_decode($cfgRaw, true);
if (!is_array($config) || !isset($config['admin_password'])) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'config.json inválido']);
  exit;
}

$ok = hash_equals((string)$config['admin_password'], (string)$data['password']);

$_SESSION['admin_logged_in'] = $ok ? true : false;
echo json_encode(['success' => $ok, 'message' => $ok ? null : 'Contraseña incorrecta']);
exit;
