<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

$AUTOMATION_DIR = getenv('ITOP_AUTOMATION_DIR') ?: __DIR__ . '/../automation';
$ESTADO_FILE = $AUTOMATION_DIR . '/estado.json';
$LOCK_FILE = $AUTOMATION_DIR . '/actualizando.lock';

function pidVivo(int $pid): bool {
    return $pid > 0 && is_dir("/proc/$pid");
}

$estado = null;
if (is_file($ESTADO_FILE)) {
    $estado = json_decode((string) file_get_contents($ESTADO_FILE), true);
}

$enProgreso = false;
if (is_file($LOCK_FILE)) {
    $info = json_decode((string) file_get_contents($LOCK_FILE), true);
    $pid = (int) ($info['pid'] ?? 0);
    $enProgreso = pidVivo($pid);
    if (!$enProgreso) {
        @unlink($LOCK_FILE);
    }
}

// Si el proceso murió (o el servidor se reinició) sin llegar a escribir un
// estado final, se lo reporta como error en vez de dejar el botón "colgado".
if (!$enProgreso && is_array($estado) && ($estado['en_progreso'] ?? false)) {
    $estado['en_progreso'] = false;
    $estado['error'] = $estado['error'] ?? 'El proceso se interrumpió antes de terminar';
}

$rutaIncidentes = $DATA_DIR . '/Incidente.csv';
$rutaRequerimientos = $DATA_DIR . '/Requerimiento.csv';

$ultimaFechaDatos = null;
$mtimes = array_filter([
    is_file($rutaIncidentes) ? filemtime($rutaIncidentes) : false,
    is_file($rutaRequerimientos) ? filemtime($rutaRequerimientos) : false,
]);
if ($mtimes) {
    $ultimaFechaDatos = date('c', max($mtimes));
}

echo json_encode([
    'ok' => true,
    'data' => [
        'en_progreso' => $enProgreso,
        'paso' => $estado['paso'] ?? null,
        'progreso' => $estado['progreso'] ?? null,
        'error' => $estado['error'] ?? null,
        'actualizado' => $estado['actualizado'] ?? null,
        'ultima_actualizacion_datos' => $ultimaFechaDatos,
    ],
], JSON_UNESCAPED_UNICODE);
