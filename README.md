# Vaulta — Aplicación de Finanzas Personales

Aplicación web de gestión de finanzas personales desarrollada con **PHP 8.1**, **MySQL 5.7** y **Nginx**, todo orquestado con **Docker Compose**.

---

## Requisitos previos

Instala estos dos programas antes de empezar:

| Herramienta | Enlace de descarga |
|---|---|
| **Docker Desktop** | https://www.docker.com/products/docker-desktop/ |
| **Git** (opcional, para clonar el repo) | https://git-scm.com/downloads |

> Asegúrate de que Docker Desktop esté **abierto y ejecutándose** antes de continuar.

---

## Estructura del proyecto

```
docker/
├── docker-compose.yml      ← Orquestador de contenedores
├── Dockerfile.nginx        ← Imagen del servidor web (Nginx + SSL)
├── Dockerfile.php          ← Imagen del procesador PHP
├── nginx.conf              ← Configuración de Nginx
├── finanzas_app.sql        ← Base de datos completa (se importa automáticamente)
└── src/                    ← Código fuente de la aplicación PHP
```

---

## Pasos para arrancar el proyecto

### 1. Obtener el proyecto

Elige una de estas dos opciones:

**Opción A — Descargar como ZIP** (no requiere Git)

1. Ve a https://github.com/diegoarc19/vaulta
2. Haz clic en el botón verde **"Code"** → **"Download ZIP"**
3. Descomprime el ZIP en una carpeta de tu elección, por ejemplo `C:\Vaulta\`
4. Entra dentro de la carpeta descomprimida antes de continuar

**Opción B — Clonar con Git** (requiere tener Git instalado)

```bash
git clone https://github.com/diegoarc19/vaulta.git
cd vaulta
```

### 2. Abrir una terminal en la carpeta del proyecto

- En Windows: clic derecho dentro de la carpeta → **"Abrir en Terminal"** (o PowerShell)
- En Mac/Linux: abre una terminal y navega a la carpeta

### 3. Construir y arrancar los contenedores

Ejecuta este único comando:

```bash
docker compose up --build -d
```

> La primera vez tardará unos minutos mientras descarga las imágenes. Las siguientes veces será inmediato.

### 4. Acceder a la aplicación

Una vez que los contenedores estén en marcha, abre el navegador:

| Servicio | URL |
|---|---|
| **Aplicación principal** | https://localhost:8443 |
| **phpMyAdmin** (gestor de BD) | http://localhost:8081 |
| **MailHog** (bandeja de email de prueba) | http://localhost:8025 |
| **Uptime Kuma** (monitorización) | http://localhost:3001 |

> ⚠️ Al abrir la app, el navegador mostrará un aviso de seguridad por el certificado SSL autofirmado. Es normal en desarrollo local. Haz clic en **"Avanzado" → "Continuar igualmente"**.

---

## Datos de acceso a phpMyAdmin

| Campo | Valor |
|---|---|
| Servidor | `db` |
| Usuario | `root` |
| Contraseña | `root` |

> La base de datos `finanzas_app` se crea e importa **automáticamente** al arrancar por primera vez. No es necesario hacer nada manual.

---

## Crear el primer usuario

1. Ve a https://localhost:8443/register.php
2. Rellena el formulario de registro
3. Inicia sesión en https://localhost:8443/login.php
4. Configura tu primera cuenta bancaria en la pantalla de inicio

---

## Parar y reiniciar los contenedores

```bash
# Parar (conserva los datos)
docker compose stop

# Volver a arrancar
docker compose start

# Parar y eliminar los contenedores (los datos de la BD se conservan en el volumen)
docker compose down

# Eliminar TAMBIÉN los datos de la base de datos (reinicio completo)
docker compose down -v
```

---

## Funcionalidades de la aplicación

- 📊 **Dashboard** con resumen de saldos y movimientos recientes
- 💸 **Movimientos** — registro de ingresos y gastos
- 🔁 **Recurrentes** — pagos y cobros periódicos automáticos
- 🔄 **Transferencias** entre cuentas
- 🎯 **Objetivos** de ahorro
- 📈 **Previsión** financiera
- 🧮 **Estimador de gastos**
- 👤 **Perfil** con cambio de contraseña y 2FA (doble factor de autenticación)
- 🌙 **Modo oscuro**
- 📧 **Recuperación de contraseña** por email (capturado en MailHog)

---

## Solución de problemas frecuentes

**El puerto 3306, 8080 o 8443 ya está en uso**
→ Cierra cualquier MySQL o servidor web local que tengas corriendo, o cambia los puertos en `docker-compose.yml`.

**"Cannot connect to the Docker daemon"**
→ Asegúrate de que Docker Desktop está abierto y ejecutándose.

**La base de datos aparece vacía en phpMyAdmin**
→ La importación automática solo ocurre la primera vez. Si ya existía el volumen, ejecuta:
```bash
docker compose down -v
docker compose up --build -d
```
