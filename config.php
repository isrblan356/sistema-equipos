<?php
/**
 * Archivo de Configuración Principal del Sistema
 */

// =================================================================
// 1. GESTIÓN DE SESIÓN Y SALIDA (A PRUEBA DE ERRORES)
// =================================================================
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =================================================================
// 2. CONSTANTES DE CONFIGURACIÓN
// =================================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_equipos');
define('DB_USER', 'root');
define('DB_PASS', '');

// =================================================================
// 3. FUNCIONES PRINCIPALES DEL SISTEMA
// =================================================================
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

function limpiarDatos($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// ✅ Declarar `limpiar()` solo si no existe ya
if (!function_exists('limpiar')) {
    function limpiar($dato) {
        return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
    }
}

// =================================================================
// 4. SISTEMA DE AUTENTICACIÓN Y PERMISOS
// =================================================================
function verificarLogin() {
    if (!isset($_SESSION['usuario_logueado']) || $_SESSION['usuario_logueado'] !== true) {
        header('Location: login.php');
        exit();
    }
}

function esAdmin() {
    return isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'Administrador';
}

function tienePermiso($permiso) {
    if (esAdmin()) {
        return true;
    }
    return isset($_SESSION['permisos']) && !empty($_SESSION['permisos'][$permiso]);
}

function verificarAdmin() {
    verificarLogin();
    if (!esAdmin()) {
        $_SESSION['error_flash'] = 'Acceso denegado. Se requieren permisos de Administrador.';
        header('Location: dashboard.php');
        exit();
    }
}
?>
