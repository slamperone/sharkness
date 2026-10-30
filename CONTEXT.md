# 🦈 Sharkness — Guía de Arquitectura, Despliegue y Hallazgos Técnicos

Documento de referencia para el equipo de desarrollo sobre la infraestructura, entornos, flujo de despliegue continuo y resolución de problemas de **Sharkness** (PrestaShop 9.2).

---

## 1. Arquitectura de Entornos

| Entorno | Dominio | Infraestructura | Stack |
|---|---|---|---|
| **Local (Desarrollo)** | `http://localhost:8090` | Docker (OrbStack en macOS) | PrestaShop 9.2, PHP 8.2, MariaDB 10.11, Apache |
| **Back Office Local** | `http://localhost:8090/admin-sharkness` | — | `admin@sharkness.local` / `Sharkness2026!` |
| **Staging** | `https://sharkness.develupme.com/` | Hostinger Compartido (hPanel) | PHP 8.2.34, MySQL, Apache + HCDN |
| **Producción (Próxima)** | `https://sharkness.mx/` | Hostinger Compartido (hPanel) | PHP 8.2.x, MySQL, Apache + HCDN |

---

## 2. Estrategia de Ramas en Git

Para evitar exponer archivos de Docker en el servidor web y permitir que la herramienta **Git de Hostinger** funcione de forma 100% nativa en `public_html/`:

* **Rama `main` (Desarrollo Local):**
  - Contiene la orquestación Docker (`docker-compose.yml`, `Makefile`, `docker/`).
  - La tienda PrestaShop completa vive dentro de `www/`.
* **Rama `stage` (Despliegue Hostinger Staging):**
  - Contiene **únicamente** los archivos web de PrestaShop en la raíz (sin la carpeta `www/`, sin Docker).
  - Hostinger Git apunta a esta rama hacia `/public_html` (o subcarpeta del subdominio).

### Cómo sincronizar cambios de Local a Staging:
```bash
# 1. En la rama main, guardas y subes tus cambios:
git add .
git commit -m "Mensaje de cambios"
git push origin main

# 2. Sincronizas automáticamente a la rama stage con un solo comando:
make deploy-stage
```

---

## 3. Protocolo Completo de Despliegue a Staging (Hostinger)

Cuando se despliegan cambios de código, base de datos o catálogo a Hostinger, se debe seguir este flujo:

### 1. Implementar Código (hPanel ➔ Git)
* Ir a **hPanel ➔ Git** en el repositorio de Staging (`stage`) y dar clic en **Implementar (Deploy)**.

### 2. Archivo `parameters.php` (Si es una instalación nueva o se borró)
* Ruta en Hostinger: `public_html/app/config/parameters.php`
* Contiene credenciales de BD y **llaves obligatorias de Symfony OAuth2** (`api_public_key`, `api_private_key`).
* Existe una plantilla lista para Staging en: `backups/parameters_staging.php`.

### 3. Limpieza de Caché Manual en Hostinger (SSH / File Manager)
Cuando no se puede entrar al Back Office para limpiar la caché desde la UI, se borra directamente por filesystem:
* **Desde File Manager:** Borrar todo el contenido dentro de:
  - `public_html/var/cache/prod/`
  - `public_html/var/cache/dev/`
* **O por SSH / Terminal de Hostinger:**
  ```bash
  rm -rf public_html/var/cache/prod public_html/var/cache/dev
  ```
*(PrestaShop y Symfony la regeneran automáticamente en la siguiente petición HTTP).*

### 4. Regenerar `.htaccess` (Evita errores 403 / 404 en categorías e imágenes)
* **Vía Back Office:** Ir a **Configurar ➔ Parámetros de la tienda ➔ Tráfico y SEO** y dar clic en **Guardar**.
* **Vía File Manager:** Si no hay acceso al Back Office, copiar el contenido de `backups/htaccess_staging` a `public_html/.htaccess`.

### 5. Sincronizar Base de Datos (Si hay cambios en el catálogo)
* Exportar local: `make backup-db` (genera archivo en `backups/*.sql.gz`).
* Importar en **phpMyAdmin de Hostinger** (Base de datos de staging).
* **Ajuste de dominio y SSL obligatorio tras importar:**
  ```sql
  UPDATE ps_configuration SET value='1' WHERE name IN ('PS_SSL_ENABLED', 'PS_SSL_ENABLED_EVERYWHERE');
  UPDATE ps_shop_url SET domain='sharkness.develupme.com', domain_ssl='sharkness.develupme.com', physical_uri='/';
  UPDATE ps_configuration SET value='sharkness.develupme.com' WHERE name IN ('PS_SHOP_DOMAIN', 'PS_SHOP_DOMAIN_SSL');
  ```

### 6. Subir Imágenes de Productos (`img/p/`)
* Como `img/p/` no viaja por Git, se comprime localmente (`backups/catalog-images.zip`), se sube a `public_html/img/` en el Administrador de Archivos de Hostinger y se extrae.

---

## 3. Hallazgos Técnicos Críticos y Lecciones Aprendidas

### A. Archivo `app/config/parameters.php` (PrestaShop 9.2)
* **Nunca se versiona en Git** (está en `.gitignore`) porque contiene credenciales de BD y claves de cifrado específicas de cada entorno.
* **Obligatorio en PrestaShop 9.2:** A diferencia de PrestaShop 8, en la versión 9.2 Symfony **exige** que existan las llaves de la API OAuth2:
  - `api_public_key`
  - `api_private_key`
  - `cookie_key` y `new_cookie_key`
  - `ps_creation_date`
  *Si alguna falta, el back office arrojará `Error 500: You have requested a non-existent parameter "api_private_key"` o el frontend dirá `Error: "install" directory is missing`.*

### B. Archivo `.env` en PrestaShop 9 (Symfony Dotenv)
* PrestaShop 9 incluye Symfony Dotenv en `index.php:25`.
* En `.gitignore` la regla debe ser **`/.env`** (con pleca al inicio) para ignorar únicamente las variables de Docker en la raíz, pero **permitir que viaje `www/.env`**, el cual define flags de Symfony (`PS_FF_DEFAULT_THEME=hummingbird`).

### C. Extensiones de PHP en Hostinger (hPanel)
En *hPanel ➔ Avanzado ➔ Configuración de PHP ➔ Extensiones de PHP*, deben estar activas:
* **`intl`** ⚠️ *(Crítica: viene desactivada por defecto en Hostinger y PrestaShop 9 no arranca sin ella).*
* **`zip`**, **`fileinfo`**, **`gd`**, **`sodium`**, **`opcache`**.

### D. Redirección 302 a `localhost:8090` o Bucle Infinito SSL
* **Síntoma 1 (Redirección a Local):** Al visitar `https://sharkness.develupme.com/` el navegador redirige a `https://localhost:8090/`. Ocurre porque la base de datos importada de local tiene guardado `localhost:8090` en `ps_shop_url` y `ps_configuration`.
* **Síntoma 2 (Bucle 302 Infinito):** PrestaShop corre en HTTP en local (`PS_SSL_ENABLED = 0`). Al importarla en Hostinger con HTTPS forzado por Cloudflare/HCDN, PrestaShop intenta redirigir a HTTP y Hostinger lo regresa a HTTPS.
* **Solución SQL inmediata en phpMyAdmin de Hostinger:**
  ```sql
  UPDATE ps_shop_url SET domain='sharkness.develupme.com', domain_ssl='sharkness.develupme.com', physical_uri='/';
  UPDATE ps_configuration SET value='sharkness.develupme.com' WHERE name IN ('PS_SHOP_DOMAIN', 'PS_SHOP_DOMAIN_SSL');
  UPDATE ps_configuration SET value='1' WHERE name IN ('PS_SSL_ENABLED', 'PS_SSL_ENABLED_EVERYWHERE');
  ```
* **Paso crítico indispensable:** Inmediatamente después de ejecutar la consulta SQL, se DEBE borrar la carpeta de caché en el File Manager de Hostinger:
  `public_html/var/cache/prod/`
  *(Si no se borra, Symfony mantiene en memoria el dominio `localhost:8090` y sigue redirigiendo aunque la base de datos ya esté corregida).*

### E. Error 500 en Menús y Categorías (`ValueError: vsprintf`)
* **Causa:** La traducción oficial al español (`es-MX` y `es-ES`) de PrestaShop contenía una errata en la clave `ShopThemeCatalog`:
  `<target state="final">Subcategorías de %s%</target>` (con un `%` de más al final).
* En PHP 8.2, `vsprintf` rechaza formatos incompletos y lanza un `ValueError`.
* **Solución:** Se corrigió a `Subcategorías de %s` en `www/translations/es-ES/ShopThemeCatalog.es-ES.xlf` y `es-MX`.

### F. Errores 404 en Imágenes y URLs Amigables (`/4-hombre`)
* PrestaShop depende del archivo `.htaccess` para traducir URLs amigables a controladores y para servir las imágenes de productos.
* Como `.htaccess` no se versiona en Git, en una instalación nueva debe generarse desde el Back Office:
  *Ir a **Configurar ➔ Parámetros de la tienda ➔ Tráfico y SEO** y dar clic en **Guardar**.*

### G. Imágenes Físicas de Productos (`img/p/` e `img/c/`)
* Se excluyen de Git para no inflar el repositorio con binarios pesados.
* Para el ambiente de pruebas, las imágenes demo se encuentran empaquetadas en:
  `backups/demo-images.zip` (se sube y descomprime en la raíz de la tienda en Hostinger).

### H. Formato de URLs Limpias (Sin prefijos numéricos)
* Se implementó un override en [Dispatcher.php](file:///Users/migueladriantrejodominguez/Projects/sabhop/sharkness/www/override/classes/Dispatcher.php) que:
  - Elimina la obligatoriedad del `{id}` en `product_rule` y `category_rule`.
  - Resuelve dinámicamente los productos y categorías en base a su `link_rewrite`.
  - Limpia parámetros de consulta (`?id=...`, `?category=...`) de `createUrl`.
* Estructura final:
  - **Categorías:** `http://localhost:8090/bebidas`, `http://localhost:8090/herramientas`
  - **Productos:** `http://localhost:8090/bebidas/tolima-t-308.html`, `http://localhost:8090/herramientas/buzz-hr-031.html`

---

## 4. Comandos Útiles del Makefile

| Comando | Acción |
|---|---|
| `make up` | Enciende todos los contenedores Docker en local |
| `make down` | Apaga los contenedores (conservando los datos) |
| `make logs` | Monitorea los logs del servidor Apache/PHP en vivo |
| `make cache` | Limpia la caché local de Symfony/PrestaShop |
| `make backup-db` | Genera un respaldo comprimido de la BD en `backups/` |
| `make restore-db FILE=...` | Restaura un archivo `.sql.gz` en MariaDB local |
| `make deploy-stage` | Extrae `www/` y lo empuja a la rama `stage` en GitHub |
