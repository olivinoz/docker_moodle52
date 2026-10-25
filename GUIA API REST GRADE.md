# 🔌 Guía de Integración API REST en Moodle

Esta guía explica de forma clara y accesible cómo funciona la conexión externa con este contenedor de Moodle para buscar alumnos y calificar tareas de forma automática mediante la API REST.

---

## 🎯 ¿Para qué sirve esta integración?

Imagina que tienes un evaluador automático (un script de Python, una plataforma externa o un calificador de código). En lugar de que un profesor entre manualmente a calificar cada alumno en Moodle, el evaluador se comunica directamente con Moodle por internet y le dice:
1. *"¿Existe este alumno con este correo?"*
2. *"Por favor, ponle esta nota en esta tarea."*

Todo esto ocurre a través de peticiones HTTP en formato **REST**.

---

## 🔑 Parámetros configurados en el sistema

El contenedor ya deja todo esto configurado de fábrica al arrancar gracias a los datos de tu archivo `.env`:

| Parámetro / Variable | ¿Qué significa en palabras simples? |
| :--- | :--- |
| **`MOODLE_API_BOT_USERNAME`** | El nombre de usuario del bot o "asistente digital" que Moodle reconoce como el encargado de calificar. |
| **`MOODLE_API_BOT_PASSWORD`** | La contraseña asignada a la cuenta de dicho bot. |
| **`MOODLE_API_BOT_EMAIL`** | Correo asignado a la cuenta del bot. |
| **`MOODLE_API_SERVICE_NAME`** | Nombre del "canal de comunicación" que abrimos en Moodle para que el bot hable con él. |
| **`MOODLE_API_TOKEN`** | **La llave maestra.** Es una cadena de caracteres secreta que el evaluador envía en cada consulta para demostrarle a Moodle que tiene permiso para consultar o calificar sin necesidad de iniciar sesión con usuario y contraseña cada vez. |

---

## ⚙️ ¿Qué funciones están habilitadas?

Para que el evaluador pueda hacer su trabajo, Moodle tiene concedidas dos funciones principales:

1. **`core_user_get_users_by_field` (Buscar Alumnos):**
   * Permite enviar un correo electrónico o nombre de usuario y recibir el `ID` numérico que tiene ese alumno dentro de Moodle.
2. **`mod_assign_save_grade` (Registrar Calificación):**
   * Permite enviar el `ID` de la tarea, el `ID` del alumno y la calificación numérica para que quede guardada de inmediato en el libro de calificaciones.

---

## 🧪 ¿Cómo probar que la API funciona? (Prueba Rápida)

Puedes probar la conexión inmediatamente desde tu terminal usando el comando `curl`. Este comando busca al alumno demo que el contenedor creó de fábrica:

```bash
curl -G "http://localhost:8080/webservice/rest/server.php" \
  --data-urlencode "wstoken=<TU_TOKEN_CONFIGURADO_EN_ENV>" \
  --data-urlencode "wsfunction=core_user_get_users_by_field" \
  --data-urlencode "moodlewsrestformat=json" \
  --data-urlencode "field=email" \
  --data-urlencode "values[0]=daniekrige@geoestadistica.rock"
```

### 🔎 ¿Qué significa cada parte de este comando?

* **`http://localhost:8080/webservice/rest/server.php`**: La dirección donde Moodle recibe las consultas de la API.
* **`wstoken=...`**: Tu llave de acceso (el token configurado en `.env`).
* **`wsfunction=core_user_get_users_by_field`**: La acción que le pedimos a Moodle (buscar un usuario).
* **`moodlewsrestformat=json`**: Le pedimos a Moodle que responda en formato JSON (fácil de leer por computadoras).
* **`field=email`**: Le decimos qué campo queremos usar como filtro de búsqueda (en este caso, el correo).
* **`values[0]=daniekrige@geoestadistica.rock`**: El correo exacto del alumno que estamos buscando.

### ✅ Resultado esperado:

Moodle responderá con la información del alumno:
```json
[
  {
    "id": 4,
    "username": "daniekrige@geoestadistica.rock",
    "firstname": "Danie G.",
    "lastname": "Krige",
    "email": "daniekrige@geoestadistica.rock",
    "country": "ZA"
  }
]
```

Si ves esa respuesta, ¡tu API está 100% activa y lista para calificar!