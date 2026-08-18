<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

$AUTOMATION_DIR = getenv('ITOP_AUTOMATION_DIR') ?: __DIR__ . '/../automation';
$SCRIPT = $AUTOMATION_DIR . '/exportacionitop.py';
$LOCK_FILE = $AUTOMATION_DIR . '/actualizando.lock';
$LOG_FILE = $AUTOMATION_DIR . '/actualizando.log';
$PYTHON_BIN = getenv('ITOP_PYTHON_BIN') ?: 'python3';

function responder(array $body, int $code = 200): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function pidVivo(int $pid): bool {
    return $pid > 0 && is_dir("/proc/$pid");
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    responder(['ok' => false, 'error' => 'Método no permitido, usar POST'], 405);
}

if (!is_file($SCRIPT)) {
    responder(['ok' => false, 'error' => 'No se encontró automation/exportacionitop.py'], 500);
}

// Si ya hay una actualización en curso, no se lanza una segunda en paralelo
// (dos Chrome abriendo sesión al mismo tiempo pisarían la descarga del otro).
if (is_file($LOCK_FILE)) {
    $info = json_decode((string) file_get_contents($LOCK_FILE), true);
    $pidPrevio = (int) ($info['pid'] ?? 0);
    if (pidVivo($pidPrevio)) {
        responder(['ok' => false, 'error' => 'Ya hay una actualización en curso']);
    }
    // El lock quedó de una corrida anterior que terminó/crasheó: se descarta.
    @unlink($LOCK_FILE);
}

$comando = sprintf(
    'exec %s %s >> %s 2>&1',
    escapeshellarg($PYTHON_BIN),
    escapeshellarg($SCRIPT),
    escapeshellarg($LOG_FILE)
);

$descriptores = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proceso = proc_open($comando, $descriptores, $pipes, $AUTOMATION_DIR);

if (!is_resource($proceso)) {
    responder(['ok' => false, 'error' => 'No se pudo iniciar el proceso de actualización'], 500);
}

// El script queda corriendo en segundo plano escribiendo su propio log y su
// propio estado.json; PHP no necesita esperar a que termine ni leer stdout.
foreach ($pipes as $pipe) {
    fclose($pipe);
}

$estadoProceso = proc_get_status($proceso);

file_put_contents($LOCK_FILE, json_encode([
    'pid' => $estadoProceso['pid'],
    'inicio' => date('c'),
]));

responder(['ok' => true, 'data' => ['pid' => $estadoProceso['pid']]]);
