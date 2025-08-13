<?php
/**
 * Archivo de Configuración Principal del Sistema
 *
 * Incluye la configuración de la base de datos, la gestión de sesiones,
 * y las funciones globales de utilidad y seguridad.
 */

// =================================================================
// 1. GESTIÓN DE SESIÓN Y SALIDA (A PRUEBA DE ERRORES)
// =================================================================

// Inicia el búfer de salida para prevenir errores de "headers already sent".
ob_start();

// Inicia la sesión de forma segura, asegurándose de que solo se haga una vez.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =================================================================
// 2. CONSTANTES DE CONFIGURACIÓN
// =================================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_equipos'); // Asegúrate de que este es el nombre correcto
define('DB_USER', 'root');
define('DB_PASS', '');

// =================================================================
// 3. FUNCIONES PRINCIPALES DEL SISTEMA
// =================================================================

/**
 * Establece una conexión con la base de datos usando PDO.
 * @return PDO Objeto de conexión.
 */
function conectarDB() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        die("Error Crítico de Conexión: " . $e->getMessage());
    }
}

/**
 * Limpia y sanitiza una cadena de texto para prevenir ataques XSS.
 * @param string $data El dato a limpiar.
 * @return string El dato limpio.
 */
function limpiarDatos($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// =================================================================
// 4. SISTEMA DE AUTENTICACIÓN Y PERMISOS
// =================================================================

/**
 * Verifica si un usuario ha iniciado sesión. Si no, lo redirige a login.php.
 */
function verificarLogin() {
    if (!isset($_SESSION['usuario_logueado']) || $_SESSION['usuario_logueado'] !== true) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Un atajo para verificar si el usuario es Administrador.
 * Es más legible que verificar el rol directamente en cada página.
 * @return bool True si es Administrador.
 */
function esAdmin() {
    return isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'Administrador';
}

/**
 * Verifica si el usuario logueado tiene un permiso específico.
 * Los permisos se cargan en la sesión durante el login.
 * @param string $permiso El nombre del permiso (ej: 'permiso_equipos').
 * @return bool True si tiene el permiso.
 */
function tienePermiso($permiso) {
    // El admin siempre tiene todos los permisos.
    if (esAdmin()) {
        return true;
    }
    // Para otros roles, verifica los permisos guardados en la sesión.
    return isset($_SESSION['permisos']) && !empty($_SESSION['permisos'][$permiso]);
}

/**
 * Protege una página completa, permitiendo el acceso solo a Administradores.
 * Si el usuario no es admin, lo redirige al dashboard con un mensaje de error.
 */
function verificarAdmin() {
    verificarLogin(); // Primero, asegurar que está logueado
    if (!esAdmin()) {
        $_SESSION['error_flash'] = 'Acceso denegado. Se requieren permisos de Administrador.';
        header('Location: dashboard.php');
        exit();
    }
}