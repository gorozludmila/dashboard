DASHBOARD UGD - SOPORTE TIMBÓ Y FIRMA DIGITAL

1) Requisitos en Ubuntu
   sudo apt install php8.3-cli php8.3-zip

2) Abrir una terminal en la carpeta portal-santa-fe:
   cd /ruta/donde/descomprimiste/portal-santa-fe

3) Levantar el servidor PHP (con router.php, ver ACTUALIZACIÓN AUTOMÁTICA):
   php -S localhost:8000 router.php

4) Abrir en Chrome:
   http://localhost:8000/portal.html

IMPORTANTE:
- Se navega siempre a archivos .html.
- Los .php están dentro de api/ y JavaScript los consulta con fetch().
- No hay botones de descarga: el acceso del portal redirige a soporte-itop/index.html.

DATOS:
Por defecto PHP lee:
  soporte-itop/data/Incidente.csv
  soporte-itop/data/Requerimiento.csv
  soporte-itop/data/Administradores.xlsx

DRIVE:
Cuando montes Google Drive en una carpeta, podés apuntar el backend sin cambiar código:
  ITOP_DATA_DIR=/home/usuario/drive-itop php -S localhost:8000

Los tres archivos deben conservar estos nombres:
  Incidente.csv
  Requerimiento.csv
  Administradores.xlsx

NOTA SOBRE ADMINISTRADORES:
Los CSV no traen el correo de "Reportado por". Por eso el cruce principal se hace por nombre normalizado contra Nombre + Apellidos del Excel. Si el creador y el reportante coinciden, también se utiliza el correo del creador como comprobación secundaria.

MINISTERIOS:
El archivo includes/config.php contiene el mapa de códigos iTop a nombres de Ministerios. Si algún código institucional cambia, se corrige solamente ahí.

ACTUALIZACIÓN AUTOMÁTICA (botón "Actualizar datos desde iTop"):
El botón hero de cada página dispara automation/exportacionitop.py (Playwright),
que inicia sesión en iTop, exporta Requerimientos e Incidentes, y los guarda
directo en data/Requerimiento.csv y data/Incidente.csv, pisando los anteriores.

1) Instalar dependencias (una sola vez):
   pip install playwright --break-system-packages
   playwright install chrome

2) Credenciales: ya están cargadas en automation/config_local.py. Si hay que
   cambiarlas, editar ese archivo (o copiar config_local.example.py). Ese
   archivo NUNCA se sube a git (ya está en automation/.gitignore).

3) Levantar el servidor SIEMPRE con router.php:
   php -S localhost:8000 router.php
   Sin router.php, el servidor embebido de PHP serviría automation/*.py y
   config_local.py como archivos de texto planos ante cualquiera que
   conozca la URL. router.php bloquea esas rutas y deja todo lo demás
   funcionando exactamente igual.

4) Click en el botón: lanza el script en segundo plano (no bloquea el
   navegador), muestra el progreso paso a paso y, al terminar, recarga la
   página con los datos nuevos. Solo puede haber una actualización corriendo
   a la vez (si se hace click de nuevo mientras corre, se avisa que ya hay
   una en curso).

5) Este flujo abre una ventana real de Chrome en la máquina donde corre
   php -S (headless=False), pensado para uso local en tu propia PC. Si algún
   día se corre en un servidor sin entorno gráfico, definir la variable de
   entorno PLAYWRIGHT_HEADLESS=1 antes de levantar el server.

6) Igual que con ITOP_DATA_DIR, se puede override desde afuera si hace falta:
   ITOP_AUTOMATION_DIR=/otra/ruta   (carpeta del script y su estado)
   ITOP_PYTHON_BIN=/ruta/al/python3 (si no alcanza con "python3" del PATH)
