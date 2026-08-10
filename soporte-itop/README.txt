DASHBOARD UGD - SOPORTE TIMBÓ Y FIRMA DIGITAL

1) Requisitos en Ubuntu
   sudo apt install php8.3-cli php8.3-zip

2) Abrir una terminal en la carpeta portal-santa-fe:
   cd /ruta/donde/descomprimiste/portal-santa-fe

3) Levantar el servidor PHP:
   php -S localhost:8000

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
