# 🦈 Sharkness

Catálogo en línea hecho con **PrestaShop 8.2** en **modo catálogo**: sin carrito, sin pagos y sin envíos.
Producción: **Hostinger**. Desarrollo local: **Docker (OrbStack)**.

## Estructura

```
sharkness/
├── www/                  ← PrestaShop completo (esto se despliega a public_html)
├── docker/php/           ← ajustes de PHP (replicar en hPanel)
├── docker-compose.yml    ← entorno local
├── .env.example          ← variables locales (copiar a .env)
├── Makefile              ← atajos
└── backups/              ← respaldos de BD (no se suben a Git)
```

## Arranque rápido

Requisitos: [OrbStack](https://orbstack.dev) abierto.

```bash
cp .env.example .env   # solo la primera vez
make up                # enciende todo
make                   # lista de comandos disponibles
```

| Servicio | URL |
|---|---|
| Tienda | http://localhost:8090 |
| Back office | http://localhost:8090/admin-sharkness |
| phpMyAdmin | http://localhost:8091 |
| Mailpit (correos) | http://localhost:8025 |

Acceso al back office en local: el correo y la contraseña están en `.env` (`ADMIN_MAIL` / `ADMIN_PASSWORD`).

## Comandos útiles

| Comando | Qué hace |
|---|---|
| `make up` / `make down` | Encender / apagar |
| `make logs` | Ver errores en vivo |
| `make cache` | Limpiar la caché de PrestaShop |
| `make backup-db` | Respaldar la BD en `backups/` |
| `make restore-db FILE=...` | Restaurar un respaldo |
| `make shell` | Abrir una terminal dentro del contenedor |

## Reglas de oro

- **El código** (tema, módulos, archivos) viaja: Mac ➜ GitHub ➜ Hostinger.
- **Los datos** (productos, imágenes, configuración del back office) viven en producción. Nunca se sube la BD local a producción una vez que el sitio esté en vivo.
- `app/config/parameters.php` y `.env` **nunca** se suben a Git.
