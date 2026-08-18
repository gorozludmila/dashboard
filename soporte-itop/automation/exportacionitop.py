import json
import os
import sys
import traceback
from datetime import datetime
from pathlib import Path

from playwright.sync_api import sync_playwright, Page


# =========================
# CONFIGURACIÓN
# =========================

URL = "https://app.santafe.gob.ar/itsm/pages/UI.php?c%5Bmenu%5D=WelcomeMenuPage"

# Las credenciales NO se hardcodean acá: se leen de automation/config_local.py
# (archivo local, fuera del control de versiones) o, si no existe, de las
# variables de entorno ITOP_USUARIO / ITOP_CONTRASENA.
try:
    from config_local import USUARIO, CONTRASENA  # type: ignore
except ImportError:
    USUARIO = os.environ.get("ITOP_USUARIO", "")
    CONTRASENA = os.environ.get("ITOP_CONTRASENA", "")

# Carpeta donde el dashboard lee los CSV (misma variable ITOP_DATA_DIR que usa
# includes/config.php, para que ambos apunten siempre al mismo lugar).
BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = Path(os.environ.get("ITOP_DATA_DIR") or (BASE_DIR.parent / "data"))
DATA_DIR.mkdir(parents=True, exist_ok=True)

# Archivo de estado que consulta el dashboard (vía api/estado_actualizacion.php)
# para mostrar el progreso en el botón "Actualizar datos".
ESTADO_FILE = BASE_DIR / "estado.json"

# En un uso local (php -S en la propia máquina) conviene ver el Chrome real
# para detectar bloqueos o pasos que cambiaron en el sitio de iTop. Se puede
# forzar modo headless (sin ventana) con PLAYWRIGHT_HEADLESS=1 si el script
# corre en un servidor sin entorno gráfico.
HEADLESS = os.environ.get("PLAYWRIGHT_HEADLESS", "0") == "1"


# =========================
# ESTADO / PROGRESO
# =========================

def guardar_estado(
    paso: str,
    progreso: int | None = None,
    en_progreso: bool = True,
    error: str | None = None,
):
    """Escribe automation/estado.json de forma atómica (escribe a un .tmp y
    renombra), para que el dashboard nunca lea un archivo a medio escribir."""

    estado = {
        "paso": paso,
        "progreso": progreso,
        "en_progreso": en_progreso,
        "error": error,
        "actualizado": datetime.now().isoformat(timespec="seconds"),
    }

    tmp = ESTADO_FILE.with_suffix(".tmp")
    tmp.write_text(
        json.dumps(estado, ensure_ascii=False),
        encoding="utf-8"
    )
    tmp.replace(ESTADO_FILE)


def verificar_credenciales():
    if not USUARIO or not CONTRASENA:
        raise RuntimeError(
            "Faltan credenciales de iTop. Creá automation/config_local.py "
            "(a partir de config_local.example.py) o definí las variables de "
            "entorno ITOP_USUARIO e ITOP_CONTRASENA."
        )


# =========================
# FUNCIONES GENERALES
# =========================

def abrir_pagina(page: Page):
    print("🌐 Ingresando a ITSM...")

    page.goto(
        URL,
        wait_until="domcontentloaded",
        timeout=60_000
    )

    print("✅ Página cargada")
    print(f"📍 URL actual: {page.url}")


def iniciar_sesion(page: Page):
    print("🔐 Verificando sesión...")

    usuario = page.get_by_placeholder(
        "Ingrese su CUIL o IUP"
    )

    try:
        usuario.wait_for(
            state="visible",
            timeout=3000
        )

    except:
        print("✅ La sesión ya está iniciada")
        print(f"📍 URL actual: {page.url}")
        return

    print("🔐 Completando credenciales...")

    usuario.fill(
        USUARIO
    )

    print("✅ Usuario ingresado")

    password = page.get_by_placeholder(
        "Ingrese su contraseña"
    )

    password.wait_for(
        state="visible",
        timeout=30_000
    )

    password.fill(
        CONTRASENA
    )

    print("✅ Contraseña ingresada")

    boton_login = page.get_by_role(
        "button",
        name="Iniciar sesión"
    )

    boton_login.wait_for(
        state="visible",
        timeout=30_000
    )

    boton_login.click()

    print("✅ Click en Iniciar sesión")

    page.wait_for_load_state(
        "domcontentloaded"
    )

    print(
        f"📍 URL después del login: {page.url}"
    )


def abrir_exportacion_csv(
    page: Page,
    seccion: str
):
    print(f"📂 Entrando a {seccion}...")

    boton_seccion = page.locator(
        "a.summary"
    ).filter(
        has_text=seccion
    )

    boton_seccion.wait_for(
        state="visible",
        timeout=30_000
    )

    boton_seccion.click()

    print(
        f"✅ Sección {seccion} abierta"
    )

    page.wait_for_timeout(1000)

    print("⚙️ Abriendo menú de acciones...")

    otras_acciones = page.get_by_role(
        "button",
        name="Otras Acciones"
    )

    otras_acciones.wait_for(
        state="visible",
        timeout=30_000
    )

    otras_acciones.click()

    print("✅ Menú abierto")

    page.wait_for_timeout(500)

    print("📥 Seleccionando Exportar a CSV...")

    exportar_csv = page.get_by_text(
        "Exportar a CSV...",
        exact=True
    )

    exportar_csv.wait_for(
        state="visible",
        timeout=30_000
    )

    exportar_csv.click()

    print("✅ Click en Exportar a CSV realizado")


# =========================
# FORMULARIO REQUERIMIENTOS
# =========================

def llenar_formulario_requerimientos(page: Page):
    print("📝 Completando formulario de Requerimientos...")

    page.wait_for_timeout(1000)

    # Id (Clave Primaria)
    id_clave_primaria = page.locator(
        "#tfs_interactive_fields_csv_Service_id"
    )

    id_clave_primaria.wait_for(
        state="visible",
        timeout=30_000
    )

    id_clave_primaria.check()

    print("✅ Id (Clave Primaria) seleccionado")

    # Creador
    creador = page.locator(
        "#tfs_interactive_fields_csv_UserRequest_creator_id_multi"
    )

    creador.wait_for(
        state="visible",
        timeout=30_000
    )

    creador.check()

    print("✅ Creador seleccionado")

    # Fecha de Asignación
    fecha_asignacion = page.locator(
        "#tfs_interactive_fields_csv_UserRequest_assignment_date"
    )

    fecha_asignacion.check()

    print("✅ Fecha de Asignación seleccionada")

    # Fecha de Solución
    fecha_solucion = page.locator(
        "#tfs_interactive_fields_csv_UserRequest_resolution_date"
    )

    fecha_solucion.check()

    print("✅ Fecha de Solución seleccionada")

    # Fecha de Cierre
    fecha_cierre = page.locator(
        "#tfs_interactive_fields_csv_UserRequest_close_date"
    )

    fecha_cierre.check()

    print("✅ Fecha de Cierre seleccionada")

    # Fecha de Fin
    fecha_fin = page.locator(
        "#tfs_interactive_fields_csv_UserRequest_end_date"
    )

    fecha_fin.check()

    page.wait_for_timeout(1000)

    print("✅ Fecha de Fin seleccionada")
    print("✅ Campos de Requerimientos seleccionados correctamente")


# =========================
# FORMULARIO INCIDENTES
# =========================

def llenar_formulario_incidentes(page: Page):
    print("📝 Completando formulario de Incidentes...")

    page.wait_for_timeout(1000)

    # Id (Clave Primaria)
    id_clave_primaria = page.locator(
        "#tfs_interactive_fields_csv_Service_id"
    )

    id_clave_primaria.wait_for(
        state="visible",
        timeout=30_000
    )

    id_clave_primaria.check()

    print("✅ Id (Clave Primaria) seleccionado")

    # Creador
    creador = page.locator(
        "#tfs_interactive_fields_csv_Incident_creator_id_multi"
    )

    creador.wait_for(
        state="visible",
        timeout=30_000
    )

    creador.check()

    print("✅ Creador seleccionado")

    # Fecha de Asignación
    fecha_asignacion = page.locator(
        "#tfs_interactive_fields_csv_Incident_assignment_date"
    )

    fecha_asignacion.check()

    print("✅ Fecha de Asignación seleccionada")

    # Fecha de Solución
    fecha_solucion = page.locator(
        "#tfs_interactive_fields_csv_Incident_resolution_date"
    )

    fecha_solucion.check()

    print("✅ Fecha de Solución seleccionada")

    # Fecha de Cierre
    fecha_cierre = page.locator(
        "#tfs_interactive_fields_csv_Incident_close_date"
    )

    fecha_cierre.check()

    print("✅ Fecha de Cierre seleccionada")

    # Fecha de Fin
    fecha_fin = page.locator(
        "#tfs_interactive_fields_csv_Incident_end_date"
    )

    fecha_fin.check()

    page.wait_for_timeout(1000)

    print("✅ Fecha de Fin seleccionada")
    print("✅ Campos de Incidentes seleccionados correctamente")


# =========================
# DESCARGA REQUERIMIENTOS
# =========================

def descargar_requerimientos(page: Page):
    print("📦 Confirmando exportación de Requerimientos...")

    boton_exportar = page.get_by_role(
        "button",
        name="Exportar",
        exact=True
    )

    boton_exportar.wait_for(
        state="visible",
        timeout=30_000
    )

    boton_exportar.click()

    print("✅ Exportación confirmada")
    print("⏳ Esperando enlace de descarga...")

    enlace_descarga = page.get_by_text(
        "Click aquí para descargar",
        exact=False
    )

    enlace_descarga.wait_for(
        state="visible",
        timeout=60_000
    )

    print("✅ Enlace de descarga disponible")

    # Se descarga directo a la carpeta data/ del dashboard, con el nombre
    # exacto que espera includes/config.php: Requerimiento.csv.
    # Primero se escribe a un .tmp y recién al final se renombra, para que
    # el dashboard nunca llegue a leer un CSV a medio escribir.
    ruta_final = DATA_DIR / "Requerimiento.csv"
    ruta_temp = DATA_DIR / "Requerimiento.csv.tmp"

    with page.expect_download(
        timeout=30_000
    ) as download_info:

        enlace_descarga.click()

    download = download_info.value

    download.save_as(
        str(ruta_temp)
    )

    ruta_temp.replace(ruta_final)

    print("✅ Archivo de Requerimientos descargado correctamente")
    print(f"📁 Guardado en: {ruta_final}")

    return ruta_final


# =========================
# DESCARGA INCIDENTES
# =========================

def descargar_incidentes(page: Page):
    print("📦 Confirmando exportación de Incidentes...")

    # Botón Exportar
    boton_exportar = page.get_by_role(
        "button",
        name="Exportar",
        exact=True
    )

    boton_exportar.wait_for(
        state="visible",
        timeout=30_000
    )

    boton_exportar.click()

    print("✅ Exportación de Incidentes confirmada")
    print("⏳ Esperando enlace de descarga de Incidentes...")

    # El texto mostrado es:
    # Click aquí para descargar Incidente Exportar.csv
    enlace_descarga = page.get_by_text(
        "Click aquí para descargar Incidente Exportar.csv",
        exact=False
    )

    enlace_descarga.wait_for(
        state="visible",
        timeout=60_000
    )

    print("✅ Enlace de descarga de Incidentes disponible")

    # =========================
    # DESCARGA DIRECTO A data/
    # =========================

    ruta_final = DATA_DIR / "Incidente.csv"
    ruta_temp = DATA_DIR / "Incidente.csv.tmp"

    with page.expect_download(
        timeout=30_000
    ) as download_info:

        enlace_descarga.click()

    download = download_info.value

    download.save_as(
        str(ruta_temp)
    )

    ruta_temp.replace(ruta_final)

    print("✅ Archivo de Incidentes descargado correctamente")
    print(f"📁 Guardado en: {ruta_final}")

    return ruta_final


# =========================
# EJECUCIÓN
# =========================

def ejecutar():

    verificar_credenciales()

    guardar_estado("Iniciando actualización", progreso=5)

    with sync_playwright() as p:

        print("🚀 Iniciando Chrome...")

        navegador = p.chromium.launch(
            headless=HEADLESS,
            slow_mo=300,
            channel="chrome"
        )

        contexto = navegador.new_context(
            viewport={
                "width": 1100,
                "height": 720
            },
            accept_downloads=True
        )

        try:

            # =====================================
            # REQUERIMIENTOS
            # =====================================

            print("\n")
            print("===========================")
            print("📘 REQUERIMIENTOS")
            print("===========================")
            print("\n")

            guardar_estado("Iniciando sesión en iTop (Requerimientos)", progreso=15)

            page_requerimientos = contexto.new_page()

            abrir_pagina(
                page_requerimientos
            )

            iniciar_sesion(
                page_requerimientos
            )

            abrir_exportacion_csv(
                page_requerimientos,
                "Requerimientos"
            )

            llenar_formulario_requerimientos(
                page_requerimientos
            )

            guardar_estado("Exportando Requerimientos", progreso=30)

            ruta_requerimientos = descargar_requerimientos(
                page_requerimientos
            )

            print("\n✅ REQUERIMIENTOS TERMINADO")
            print(f"📁 {ruta_requerimientos}")

            guardar_estado("Requerimientos actualizados", progreso=45)

            # =====================================
            # INCIDENTES
            # =====================================

            print("\n")
            print("===========================")
            print("📕 INCIDENTES")
            print("===========================")
            print("\n")

            print("🆕 Abriendo nueva pestaña...")

            guardar_estado("Iniciando sesión en iTop (Incidentes)", progreso=55)

            page_incidentes = contexto.new_page()

            abrir_pagina(
                page_incidentes
            )

            iniciar_sesion(
                page_incidentes
            )

            abrir_exportacion_csv(
                page_incidentes,
                "Incidentes"
            )

            llenar_formulario_incidentes(
                page_incidentes
            )

            guardar_estado("Exportando Incidentes", progreso=70)

            ruta_incidentes = descargar_incidentes(
                page_incidentes
            )

            print("\n✅ INCIDENTES TERMINADO")
            print(f"📁 {ruta_incidentes}")

            guardar_estado("Incidentes actualizados", progreso=90)

            # =====================================
            # FINAL
            # =====================================

            print("\n")
            print("===========================")
            print("🎉 PROCESO COMPLETO")
            print("===========================")

            print("✅ Requerimientos exportados")
            print("✅ Incidentes exportados")

            print("\n📁 ARCHIVOS GENERADOS:")

            print(
                f"📄 {ruta_requerimientos}"
            )

            print(
                f"📄 {ruta_incidentes}"
            )

            guardar_estado(
                "Actualización completa",
                progreso=100,
                en_progreso=False,
                error=None
            )

        except Exception as error:

            print("\n❌ ERROR:")
            print(error)
            traceback.print_exc()

            guardar_estado(
                "Error durante la actualización",
                en_progreso=False,
                error=str(error)
            )

            raise

        finally:

            navegador.close()


# =========================
# INICIO
# =========================

if __name__ == "__main__":
    try:
        ejecutar()
    except Exception as error:
        # El error ya quedó guardado en estado.json por ejecutar(); acá solo
        # se define el código de salida del proceso para quien lo invoque
        # (por ejemplo api/actualizar.php, que lo lanza en segundo plano).
        print(f"❌ La actualización terminó con error: {error}")
        sys.exit(1)

    sys.exit(0)
