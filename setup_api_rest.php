<?php
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/authlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');

global $DB, $CFG;

echo ">>> [API Setup] Iniciando preconfiguración automática de API REST...\n";

// Variables desde el entorno
$bot_username = getenv('MOODLE_API_BOT_USERNAME') ?: 'apibot_git';
$bot_password = getenv('MOODLE_API_BOT_PASSWORD') ?: 'ApiBotPassword2026*';
$bot_email    = getenv('MOODLE_API_BOT_EMAIL') ?: 'apibot@evaluador.local';
$service_name = getenv('MOODLE_API_SERVICE_NAME') ?: 'Evaluador Service';
$service_shortname = getenv('MOODLE_API_SERVICE_SHORTNAME') ?: 'evaluador_service';
$api_token    = getenv('MOODLE_API_TOKEN') ?: '9a8b7c6d5e4f3a2b1c0d9e8f7a6b5c4d';

// =========================================================================
// 1. Habilitar Web Services y Protocolo REST
// =========================================================================
set_config('enablewebservices', 1);

$active_protocols = empty($CFG->webserviceprotocols) ? [] : explode(',', $CFG->webserviceprotocols);
if (!in_array('rest', $active_protocols)) {
    $active_protocols[] = 'rest';
    set_config('webserviceprotocols', implode(',', array_filter($active_protocols)));
}
echo ">>> [1/7] Web Services y protocolo REST activados.\n";

// =========================================================================
// 2. Configurar Visibilidad de la Identidad del Usuario (Username y Email)
// =========================================================================
$current_identity = empty($CFG->showuseridentity) ? [] : explode(',', $CFG->showuseridentity);
$required_fields = ['username', 'email'];
$new_identity = array_unique(array_merge($current_identity, $required_fields));
set_config('showuseridentity', implode(',', $new_identity));
echo ">>> [2/7] Visibilidad de usuario configurada (showuseridentity = " . implode(',', $new_identity) . ").\n";

// =========================================================================
// 3. Crear la Cuenta del API Bot
// =========================================================================
$bot_user = $DB->get_record('user', ['username' => $bot_username, 'mnethostid' => $CFG->mnet_localhost_id]);
if (!$bot_user) {
    $user_record = new stdClass();
    $user_record->auth = 'manual';
    $user_record->confirmed = 1;
    $user_record->username = $bot_username;
    $user_record->password = hash_internal_user_password($bot_password);
    $user_record->firstname = 'API';
    $user_record->lastname = 'Bot';
    $user_record->email = $bot_email;
    $user_record->maildisplay = 1;
    $user_record->mnethostid = $CFG->mnet_localhost_id;
    $user_record->lang = 'en';
    $user_record->timecreated = time();
    $user_record->timemodified = time();

    $user_id = $DB->insert_record('user', $user_record);
    $bot_user = $DB->get_record('user', ['id' => $user_id]);
    echo ">>> [3/7] Usuario Bot '{$bot_username}' creado exitosamente (ID: {$user_id}).\n";
} else {
    // Actualizar contraseña por si cambió en el .env
    $update_user = new stdClass();
    $update_user->id = $bot_user->id;
    $update_user->password = hash_internal_user_password($bot_password);
    $update_user->email = $bot_email;
    $DB->update_record('user', $update_user);
    echo ">>> [3/7] Usuario Bot '{$bot_username}' ya existe (ID: {$bot_user->id}). Credenciales verificadas.\n";
}

// =========================================================================
// 4. Elevación de Privilegios: Administrador del Sitio
// =========================================================================
$site_admins = empty($CFG->siteadmins) ? [] : explode(',', $CFG->siteadmins);
if (!in_array((string)$bot_user->id, $site_admins)) {
    $site_admins[] = (string)$bot_user->id;
    set_config('siteadmins', implode(',', array_filter($site_admins)));
    echo ">>> [4/7] Usuario '{$bot_username}' agregado a Administradores del Sitio.\n";
} else {
    echo ">>> [4/7] Usuario '{$bot_username}' ya es Administrador del Sitio.\n";
}

// =========================================================================
// 5. Crear Rol (api_role), Asignar Capacidades y Contexto
// =========================================================================
$role = $DB->get_record('role', ['shortname' => 'api_role']);
if (!$role) {
    $role_id = create_role('API Role', 'api_role', 'Rol especial para Bot de API REST y Calificación', 'system');
    echo ">>> [5/7] Rol 'api_role' creado (ID: {$role_id}).\n";
} else {
    $role_id = $role->id;
    echo ">>> [5/7] Rol 'api_role' ya existe (ID: {$role_id}).\n";
}

// Asegurar que el contexto System esté habilitado para este rol
set_role_contextlevels($role_id, [CONTEXT_SYSTEM]);

// Asignar capacidades requeridas en el contexto del sistema
$sys_context = context_system::instance();
$capabilities = [
    'moodle/user:viewdetails',
    'moodle/site:viewuseridentity',
    'moodle/user:viewhiddendetails',
    'mod/assign:grade',
    'moodle/webservice:createtoken'
];

foreach ($capabilities as $cap) {
    assign_capability($cap, CAP_ALLOW, $role_id, $sys_context->id, true);
}

// Asignar rol a nivel de sistema para el bot
role_assign($role_id, $bot_user->id, $sys_context->id);
echo ">>> [5/7] Capacidades y rol de sistema asignados al Bot.\n";

// =========================================================================
// 6. Crear el Servicio Web Externo y Vincular Funciones
// =========================================================================
$service = $DB->get_record('external_services', ['shortname' => $service_shortname]);
if (!$service) {
    $new_service = new stdClass();
    $new_service->name = $service_name;
    $new_service->shortname = $service_shortname;
    $new_service->enabled = 1;
    $new_service->requiredcapability = '';
    $new_service->restrictedusers = 0; // Authorized users only = DESMARCADO
    $new_service->downloadfiles = 0;
    $new_service->uploadfiles = 0;
    $new_service->timecreated = time();
    $new_service->timemodified = time();

    $service_id = $DB->insert_record('external_services', $new_service);
    echo ">>> [6/7] Servicio Web '{$service_name}' creado (ID: {$service_id}).\n";
} else {
    $service_id = $service->id;
    $update_service = new stdClass();
    $update_service->id = $service_id;
    $update_service->enabled = 1;
    $update_service->restrictedusers = 0;
    $DB->update_record('external_services', $update_service);
    echo ">>> [6/7] Servicio Web '{$service_name}' ya existente (ID: {$service_id}).\n";
}

// Agregar funciones requeridas al servicio
$functions = [
    'core_user_get_users_by_field',
    'mod_assign_save_grade'
];

$ws_manager = new webservice();
foreach ($functions as $func_name) {
    $func_exists = $DB->record_exists('external_services_functions', [
        'externalserviceid' => $service_id,
        'functionname' => $func_name
    ]);
    if (!$func_exists) {
        $ws_manager->add_external_function_to_service($func_name, $service_id);
        echo "    + Función '{$func_name}' añadida al servicio.\n";
    }
}

// =========================================================================
// 7. Generar o Asegurar el Token de Conexión
// =========================================================================
$existing_token = $DB->get_record('external_tokens', [
    'userid' => $bot_user->id,
    'externalserviceid' => $service_id,
    'tokentype' => EXTERNAL_TOKEN_PERMANENT
]);

if (!$existing_token) {
    $token_record = new stdClass();
    $token_record->token = $api_token;
    $token_record->privatetoken = null;
    $token_record->tokentype = EXTERNAL_TOKEN_PERMANENT;
    $token_record->userid = $bot_user->id;
    $token_record->externalserviceid = $service_id;
    $token_record->contextid = $sys_context->id;
    $token_record->creatorid = $bot_user->id;
    $token_record->timecreated = time();
    $token_record->validuntil = 0;

    $DB->insert_record('external_tokens', $token_record);
    echo ">>> [7/7] Token permanente asignado: {$api_token}\n";
} else {
    // Si ya existía, actualizamos el hash/token con el del .env
    $update_token = new stdClass();
    $update_token->id = $existing_token->id;
    $update_token->token = $api_token;
    $DB->update_record('external_tokens', $update_token);
    echo ">>> [7/7] Token actualizado con valor de entorno: {$api_token}\n";
}

// =========================================================================
// 8. Crear / Asegurar Usuario Demo (Danie G. Krige)
// =========================================================================
$demo_username  = getenv('DEMO_USER_USERNAME') ?: 'daniekrige@geoestadistica.rock';
$demo_password  = getenv('DEMO_USER_PASSWORD') ?: '#%7B5bbbf1dd';
$demo_firstname = getenv('DEMO_USER_FIRSTNAME') ?: 'Danie G.';
$demo_lastname  = getenv('DEMO_USER_LASTNAME') ?: 'Krige';
$demo_email     = getenv('DEMO_USER_EMAIL') ?: 'daniekrige@geoestadistica.rock';
$demo_country   = getenv('DEMO_USER_COUNTRY') ?: 'ZA';

$demo_user = $DB->get_record('user', ['username' => $demo_username, 'mnethostid' => $CFG->mnet_localhost_id]);
if (!$demo_user) {
    $demo_record = new stdClass();
    $demo_record->auth = 'manual';
    $demo_record->confirmed = 1;
    $demo_record->username = $demo_username;
    $demo_record->password = hash_internal_user_password($demo_password);
    $demo_record->firstname = $demo_firstname;
    $demo_record->lastname = $demo_lastname;
    $demo_record->email = $demo_email;
    $demo_record->country = $demo_country;
    $demo_record->maildisplay = 1;
    $demo_record->mnethostid = $CFG->mnet_localhost_id;
    $demo_record->lang = 'en';
    $demo_record->timecreated = time();
    $demo_record->timemodified = time();

    $demo_id = $DB->insert_record('user', $demo_record);
    echo ">>> [8/8] Usuario Demo '{$demo_firstname} {$demo_lastname}' ({$demo_username}) creado exitosamente (ID: {$demo_id}).\n";
} else {
    $demo_update = new stdClass();
    $demo_update->id = $demo_user->id;
    $demo_update->password = hash_internal_user_password($demo_password);
    $demo_update->firstname = $demo_firstname;
    $demo_update->lastname = $demo_lastname;
    $demo_update->email = $demo_email;
    $demo_update->country = $demo_country;
    $DB->update_record('user', $demo_update);
    echo ">>> [8/8] Usuario Demo '{$demo_username}' ya existe (ID: {$demo_user->id}). Datos actualizados.\n";
}

echo ">>> ✅ Preconfiguración completada con éxito (API REST + Usuario Demo).\n";
