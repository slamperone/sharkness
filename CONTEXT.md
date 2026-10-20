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

### D. Bucle Infinito de Redirección SSL (HTTP 302 Infinito)
Al exportar la base de datos de local (que corre en HTTP), PrestaShop almacena `PS_SSL_ENABLED = 0`. Al importarla en un servidor con HTTPS forzado por Hostinger/Cloudflare, se crea un bucle infinito (PrestaShop redirige a HTTP y Hostinger lo regresa a HTTPS).
* **Solución SQL inmediata en phpMyAdmin del hosting:**
  ```sql
  UPDATE ps_configuration SET value='1' WHERE name IN ('PS_SSL_ENABLED', 'PS_SSL_ENABLED_EVERYWHERE');
  UPDATE ps_shop_url SET domain='sharkness.develupme.com', domain_ssl='sharkness.develupme.com', physical_uri='/';
  UPDATE ps_configuration SET value='sharkness.develupme.com' WHERE name IN ('PS_SHOP_DOMAIN', 'PS_SHOP_DOMAIN_SSL');
  ```

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
