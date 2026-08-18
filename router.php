<?php
// Router para el servidor embebido de PHP (php -S localhost:8000 router.php).
//
// Por qué existe: `php -S` sin router sirve CUALQUIER archivo del proyecto
// tal cual (incluyendo .py, .json, .log), y ahora dentro de soporte-itop/
// vive automation/, que contiene el script de Playwright y sus credenciales
// (config_local.py). Este router corta esas rutas antes de que el servidor
// las entregue como archivo estático. El resto del sitio funciona igual
// que antes (HTML, CSS, JS y los endpoints .php se sirven normalmente).

$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$patronesBloqueados = [
    '#/automation/#',   // toda la carpeta de automatización
    '#\.py$#',          // scripts Python sueltos
    '#\.lock$#',        // lock de ejecución en curso
    '#\.log$#',         // log de la última corrida
];

foreach ($patronesBloqueados as $patron) {
    if (preg_match($patron, $uri)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No encontrado';
        return true;
    }
}

// Cualquier otra ruta: que el servidor embebido de PHP la maneje como siempre
// (sirve el archivo estático si existe, o ejecuta el .php correspondiente).
return false;
