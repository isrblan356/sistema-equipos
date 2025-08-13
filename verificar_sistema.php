<?php
// Archivo: verificar_sistema.php
// Este archivo te ayudará a diagnosticar problemas con el sistema

require_once 'config.php';

echo "<h1>Verificación del Sistema</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .info{background:#e3f2fd;padding:15px;margin:10px 0;border-radius:5px;} .error{background:#ffebee;padding:15px;margin:10px 0;border-radius:5px;} .success{background:#e8f5e9;padding:15px;margin:10px 0;border-radius:5px;}</style>";

// 1. Verificar conexión a la base de datos
echo "<h2>1. Verificando conexión a la base de datos</h2>";
try {
    $pdo = conectarDB();
    echo "<div class='success'>✅ Conexión a la base de datos: EXITOSA</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Error de conexión: " . $e->getMessage() . "</div>";
    exit;
}

// 2. Verificar que las tablas existan
echo "<h2>2. Verificando estructura de tablas</h2>";
try {
    $tablas = ['usuarios', 'roles'];
    foreach ($tablas as $tabla) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabla'");
        if ($stmt->rowCount() > 0) {
            echo "<div class='success'>✅ Tabla '$tabla': EXISTE</div>";
        } else {
            echo "<div class='error'>❌ Tabla '$tabla': NO EXISTE</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error verificando tablas: " . $e->getMessage() . "</div>";
}

// 3. Verificar datos en tabla roles
echo "<h2>3. Verificando roles en la base de datos</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM roles ORDER BY id");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($roles)) {
        echo "<div class='error'>❌ No hay roles en la base de datos</div>";
        echo "<div class='info'>💡 Ejecuta este SQL para crear roles básicos:<br>";
        echo "INSERT INTO roles (nombre_rol) VALUES ('Administrador'), ('Usuario'), ('Editor');</div>";
    } else {
        echo "<div class='success'>✅ Roles encontrados:</div>";
        echo "<table border='1' style='border-collapse:collapse;margin:10px 0;'>";
        echo "<tr style='background:#f5f5f5;'><th style='padding:10px;'>ID</th><th style='padding:10px;'>Nombre del Rol</th></tr>";
        foreach ($roles as $rol) {
            echo "<tr><td style='padding:10px;'>" . $rol['id'] . "</td><td style='padding:10px;'>" . htmlspecialchars($rol['nombre_rol']) . "</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error consultando roles: " . $e->getMessage() . "</div>";
}

// 4. Verificar usuarios y sus roles
echo "<h2>4. Verificando usuarios y sus roles asignados</h2>";
try {
    $stmt = $pdo->query("
        SELECT u.id, u.nombre, u.email, u.rol_id, r.nombre_rol 
        FROM usuarios u 
        LEFT JOIN roles r ON u.rol_id = r.id 
        ORDER BY u.id
    ");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($usuarios)) {
        echo "<div class='error'>❌ No hay usuarios en la base de datos</div>";
    } else {
        echo "<div class='success'>✅ Usuarios encontrados:</div>";
        echo "<table border='1' style='border-collapse:collapse;margin:10px 0;'>";
        echo "<tr style='background:#f5f5f5;'><th style='padding:10px;'>ID</th><th style='padding:10px;'>Nombre</th><th style='padding:10px;'>Email</th><th style='padding:10px;'>Rol ID</th><th style='padding:10px;'>Nombre Rol</th></tr>";
        foreach ($usuarios as $usuario) {
            $rolClass = $usuario['nombre_rol'] == 'Administrador' ? 'style="background:#fff3cd;"' : '';
            echo "<tr $rolClass>";
            echo "<td style='padding:10px;'>" . $usuario['id'] . "</td>";
            echo "<td style='padding:10px;'>" . htmlspecialchars($usuario['nombre']) . "</td>";
            echo "<td style='padding:10px;'>" . htmlspecialchars($usuario['email']) . "</td>";
            echo "<td style='padding:10px;'>" . ($usuario['rol_id'] ?? 'NULL') . "</td>";
            echo "<td style='padding:10px;'>" . htmlspecialchars($usuario['nombre_rol'] ?? 'SIN ROL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar si hay administradores
        $admins = array_filter($usuarios, function($u) { return $u['nombre_rol'] === 'Administrador'; });
        if (empty($admins)) {
            echo "<div class='error'>❌ No hay usuarios con rol 'Administrador'</div>";
            echo "<div class='info'>💡 Para asignar rol de administrador a un usuario, ejecuta:<br>";
            echo "UPDATE usuarios SET rol_id = (SELECT id FROM roles WHERE nombre_rol = 'Administrador') WHERE email = 'tu_email@ejemplo.com';</div>";
        } else {
            echo "<div class='success'>✅ Administradores encontrados: " . count($admins) . "</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error consultando usuarios: " . $e->getMessage() . "</div>";
}

// 5. Verificar variables de sesión (si hay usuario logueado)
echo "<h2>5. Verificando variables de sesión</h2>";
if (isset($_SESSION['usuario_id'])) {
    echo "<div class='success'>✅ Usuario logueado detectado</div>";
    echo "<div class='info'>";
    echo "<strong>Variables de sesión:</strong><br>";
    echo "- usuario_id: " . ($_SESSION['usuario_id'] ?? 'NO SET') . "<br>";
    echo "- usuario_nombre: " . ($_SESSION['usuario_nombre'] ?? 'NO SET') . "<br>";
    echo "- usuario_email: " . ($_SESSION['usuario_email'] ?? 'NO SET') . "<br>";
    echo "- usuario_rol: " . ($_SESSION['usuario_rol'] ?? 'NO SET') . "<br>";
    echo "- rol_id: " . ($_SESSION['rol_id'] ?? 'NO SET') . "<br>";
    echo "</div>";
    
    // Verificar si puede obtener el rol
    if (!isset($_SESSION['usuario_rol'])) {
        echo "<div class='error'>❌ Variable 'usuario_rol' no está definida en la sesión</div>";
        echo "<div class='info'>💡 Intentando cargar datos del usuario...</div>";
        
        if (cargarDatosUsuario()) {
            echo "<div class='success'>✅ Datos del usuario cargados exitosamente</div>";
            echo "- usuario_rol después de cargar: " . ($_SESSION['usuario_rol'] ?? 'AÚN NO SET') . "<br>";
        } else {
            echo "<div class='error'>❌ No se pudieron cargar los datos del usuario</div>";
        }
    }
    
    // Verificar función obtenerRolUsuario
    $rolObtenido = obtenerRolUsuario();
    echo "<div class='info'>Resultado de obtenerRolUsuario(): '$rolObtenido'</div>";
    echo "<div class='info'>¿Es Administrador?: " . (($rolObtenido === 'Administrador') ? 'SI' : 'NO') . "</div>";
    
} else {
    echo "<div class='error'>❌ No hay usuario logueado</div>";
    echo "<div class='info'>💡 <a href='login.php'>Ir al login</a></div>";
}

// 6. Recomendaciones
echo "<h2>6. Recomendaciones</h2>";
echo "<div class='info'>";
echo "<strong>Para resolver problemas comunes:</strong><br>";
echo "1. Si las tablas no existen, ejecuta el script de creación de tablas<br>";
echo "2. Si no tienes roles, inserta los roles básicos<br>";
echo "3. Si tu usuario no es administrador, actualiza su rol_id<br>";
echo "4. Si las variables de sesión no están definidas, verifica tu proceso de login<br>";
echo "5. Limpia las cookies y vuelve a iniciar sesión<br>";
echo "</div>";

echo "<hr>";
echo "<p><a href='dashboard.php'>← Volver al Dashboard</a> | <a href='login.php'>Ir al Login</a></p>";
?>