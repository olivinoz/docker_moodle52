# 🚀 Moodle 5.2 Automatizado con Docker

Un entorno preconfigurado y listo para usar de **Moodle 5.2**, diseñado para ahorrar tiempo en configuraciones manuales e integrarse inmediatamente con sistemas externos para calificar y buscar usuarios sin fricción.

---

## 🎯 ¿Cuál es el objetivo de este proyecto?

El objetivo principal es disponer de un campus virtual de Moodle que, desde el primer segundo en que se levanta:
1. **Se instale solo:** Sin pantallas de bienvenida ni asistentes web paso a paso.
2. **Venga listo para evaluadores automáticos:** Ya deja habilitada la API REST, con su usuario bot, roles y token de conexión listos para consultar alumnos y asignar calificaciones desde scripts o plataformas externas.
3. **Cree un usuario de prueba:** Inserta de fábrica un estudiante demo con credenciales conocidas para validar el sistema de inmediato.

---

## 🏗️ ¿Con qué está construido?

El entorno combina tres piezas principales empaquetadas en contenedores:
* **Moodle 5.2:** La plataforma educativa, corriendo sobre el servidor web **Apache** y **PHP 8.3**.
* **PostgreSQL 16:** El motor de base de datos donde se almacena toda la información del campus de forma persistente.
* **Orquestación con Docker Compose:** Para coordinar y enlazar la aplicación y la base de datos con un único comando.

---

## 📋 Antes de empezar: ¿Qué debes tener en cuenta?

Antes de poner en marcha el proyecto, considera estos puntos clave:

* **Puerto local disponible:** La plataforma utilizará el puerto `8080` de tu máquina. Asegúrate de que no haya otro programa ocupándolo.
* **Archivos de configuración inicial:** Por seguridad, las credenciales reales no se guardan en el repositorio. Necesitarás generar tus archivos `.env` y `entrypoint.sh` a partir de sus respectivas plantillas de ejemplo (`.example`).
* **Primera instalación:** El primer arranque toma un par de minutos mientras Moodle crea las tablas iniciales en la base de datos y configura la API.

---

## 🚦 Guía paso a paso: Puesta en marcha

Sigue este hilo de pasos sencillos para tener tu plataforma corriendo:

### 1. Preparar las variables de entorno
Crea tu archivo de secretos copiando la plantilla:
```bash
cp .env.example .env
```
*(Opcional: puedes abrir `.env` y cambiar contraseñas, nombres o correos si lo deseas).*

### 2. Preparar el script de arranque
Copia la plantilla del script de inicio y dale permisos de ejecución:
```bash
cp entrypoint.sh.example entrypoint.sh
chmod +x entrypoint.sh
```

### 3. Levantar los contenedores
Construye la imagen e inicia los servicios en segundo plano:
```bash
docker compose -f docker-compose_apache81_academy.yml up -d --build
```

### 4. Verificar el progreso
Puedes ver cómo avanza la instalación en tiempo real con:
```bash
docker logs -f moodle-container-galoc52
```
Cuando en el registro leas `>>> Iniciando Apache...`, ¡todo estará listo!

---

## 🌐 Acceso a la plataforma

Una vez iniciado, entra desde tu navegador a:
👉 **[http://localhost:8080](http://localhost:8080)**

* **Usuario Administrador:** El usuario que hayas definido en tu `.env` (ejemplo en plantilla: `admin_usuario`)
* **Contraseña Administrador:** La contraseña que hayas definido en tu `.env`
* **Usuario Demo Estudiante:** `daniekrige@geoestadistica.rock` (Contraseña: la que hayas definido en `DEMO_USER_PASSWORD` dentro de tu `.env`)

---

## 🔌 Conexión con la API REST

Para consultar usuarios o enviar calificaciones desde un script externo sin entrar al panel web, consulta la guía detallada:
👉 **[GUIA API REST GRADE.md](GUIA%20API%20REST%20GRADE.md)**: Explica de forma clara qué hace cada parámetro, cómo funciona el token y cómo enviar una prueba rápida con `curl`.

---

## 🛑 Detener o reiniciar el entorno

* **Pausar los contenedores (conservando los datos):**
  ```bash
  docker compose -f docker-compose_apache81_academy.yml down
  ```

* **Borrar todo y empezar desde cero (elimina también la base de datos):**
  ```bash
  docker compose -f docker-compose_apache81_academy.yml down -v
  ```
