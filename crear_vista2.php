<?php
ob_start();
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$mensajeHtml = '';
$errorHtml = '';

// Mostrar mensajes flash
if (isset($_SESSION['mensaje_flash'])) {
    $mensajeHtml = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' . $_SESSION['mensaje_flash'] . '</div>';
    unset($_SESSION['mensaje_flash']);
}
if (isset($_SESSION['error_flash'])) {
    $errorHtml = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' . $_SESSION['error_flash'] . '</div>';
    unset($_SESSION['error_flash']);
}

// Tipos de campo disponibles
$tipos_campo = [
    'text' => 'Texto',
    'number' => 'Número',
    'email' => 'Email',
    'tel' => 'Teléfono',
    'date' => 'Fecha',
    'textarea' => 'Área de Texto'
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_vista'])) {
    try {
        $nombre_singular = limpiarDatos($_POST['nombre_singular']);
        $nombre_plural = limpiarDatos($_POST['nombre_plural']);
        $icono = limpiarDatos($_POST['icono']);
        
        // Generar clave única para el tipo de hardware
        $clave_tipo = strtolower(str_replace([' ', 'á', 'é', 'í', 'ó', 'ú', 'ñ'], ['_', 'a', 'e', 'i', 'o', 'u', 'n'], $nombre_plural));
        $nombre_tabla = 'hardware_' . $clave_tipo;
        
        // Validar que no exista ya
        $config_existente = require 'hardware_config.php';
        if (isset($config_existente[$clave_tipo])) {
            throw new Exception("Ya existe una vista con ese nombre.");
        }
        
        // Construir array de campos personalizados
        $campos_personalizados = [];
        if (isset($_POST['campos_nombres']) && is_array($_POST['campos_nombres'])) {
            foreach ($_POST['campos_nombres'] as $index => $nombre_campo) {
                if (empty($nombre_campo)) continue;
                
                $campo_key = strtolower(str_replace([' ', 'á', 'é', 'í', 'ó', 'ú', 'ñ'], ['_', 'a', 'e', 'i', 'o', 'u', 'n'], $nombre_campo));
                $campos_personalizados[$campo_key] = [
                    'label' => $nombre_campo,
                    'type' => $_POST['campos_tipos'][$index] ?? 'text',
                    'required' => false,
                    'unique' => isset($_POST['campos_unicos'][$index])
                ];
            }
        }
        
        // Crear la tabla en la base de datos
        $sql_create_table = "CREATE TABLE IF NOT EXISTS `$nombre_tabla` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nombre_equipo` VARCHAR(255) NOT NULL,
            `marca` VARCHAR(100),
            `modelo` VARCHAR(100),
            `usuario_asignado` VARCHAR(255),";
        
        // Agregar campos personalizados a la tabla
        foreach ($campos_personalizados as $campo_key => $campo_config) {
            $tipo_sql = match($campo_config['type']) {
                'number' => 'INT',
                'email', 'tel' => 'VARCHAR(100)',
                'date' => 'DATE',
                'textarea' => 'TEXT',
                default => 'VARCHAR(255)'
            };
            
            $unique_constraint = $campo_config['unique'] ? ' UNIQUE' : '';
            $sql_create_table .= "\n    `$campo_key` $tipo_sql$unique_constraint,";
        }
        
        $sql_create_table .= "
            `estado` VARCHAR(50) NOT NULL,
            `notas` TEXT,
            `ultima_revision` DATETIME,
            `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_nombre (`nombre_equipo`),
            INDEX idx_estado (`estado`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        // Ejecutar creación de tabla
        $pdo->exec($sql_create_table);
        
        // Actualizar archivo de configuración
        $nueva_config = [
            $clave_tipo => [
                'singular' => $nombre_singular,
                'plural' => $nombre_plural,
                'tabla' => $nombre_tabla,
                'icono' => $icono,
                'campos' => $campos_personalizados
            ]
        ];
        
        // Leer configuración existente y agregar la nueva
        $config_actual = $config_existente;
        $config_actual[$clave_tipo] = $nueva_config[$clave_tipo];
        
        // Escribir archivo de configuración
        $config_php = "<?php\nreturn " . var_export($config_actual, true) . ";\n";
        file_put_contents('hardware_config.php', $config_php);
        
        $_SESSION['mensaje_flash'] = "Vista '$nombre_plural' creada exitosamente. La tabla '$nombre_tabla' ha sido generada automáticamente.";
        header("Location: portatiles.php?vista=inventario&tipo=$clave_tipo");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'Error de base de datos: ' . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error_flash'] = $e->getMessage();
    }
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nueva Vista de Hardware</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0ea5e9;
            --secondary-color: #3b82f6;
            --success-color: #16a34a;
            --danger-color: #dc2626;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .main-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border: none;
        }
        .card-header h1 {
            margin: 0;
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .card-body {
            padding: 2rem;
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .campo-dinamico {
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
            transition: all 0.3s ease;
        }
        .campo-dinamico:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.1);
        }
        .btn-remove-campo {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--danger-color);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-remove-campo:hover {
            background: #b91c1c;
            transform: scale(1.1);
        }
        .form-label {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        .form-control, .form-select {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }
        .btn {
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(14, 165, 233, 0.4);
        }
        .btn-success {
            background: var(--success-color);
        }
        .btn-success:hover {
            background: #15803d;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6b7280;
        }
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: white;
        }
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
        }
        @media (max-width: 768px) {
            .card-body {
                padding: 1rem;
            }
            .campo-dinamico {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?= $mensajeHtml . $errorHtml; ?>
        
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-magic"></i> Crear Nueva Vista de Hardware</h1>
                <p class="mb-0 mt-2">Configura un nuevo tipo de hardware sin escribir código. Todo se generará automáticamente.</p>
            </div>
            
            <div class="card-body">
                <form method="POST" id="formCrearVista">
                    <input type="hidden" name="crear_vista" value="1">
                    
                    <!-- Sección 1: Información Básica -->
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i>
                        1. Define el Tipo de Hardware
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Nombre en Singular *</label>
                            <input type="text" name="nombre_singular" class="form-control" placeholder="Ej: Proyector" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nombre en Plural *</label>
                            <input type="text" name="nombre_plural" class="form-control" placeholder="Ej: Proyectores" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Icono (FontAwesome) *</label>
                            <input type="text" name="icono" class="form-control" placeholder="Ej: fas fa-video" value="fas fa-laptop" required>
                            <small class="text-muted">Busca iconos en: <a href="https://fontawesome.com/icons" target="_blank">fontawesome.com</a></small>
                        </div>
                    </div>
                    
                    <!-- Sección 2: Campos Adicionales -->
                    <div class="section-title">
                        <i class="fas fa-list"></i>
                        2. Define los Campos Adicionales del Formulario
                    </div>
                    
                    <p class="text-muted mb-3">
                        Los campos como 'nombre_equipo', 'marca', 'modelo', 'usuario_asignado', 'estado' y 'notas' se agregarán automáticamente.
                    </p>
                    
                    <div id="campos-container">
                        <!-- Los campos se agregarán dinámicamente aquí -->
                    </div>
                    
                    <button type="button" class="btn btn-outline-primary mb-4" onclick="agregarCampo()">
                        <i class="fas fa-plus"></i> Agregar Campo Adicional
                    </button>
                    
                    <!-- Botones de Acción -->
                    <div class="d-flex gap-3 justify-content-end">
                        <a href="portatiles.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-magic"></i> Crear Vista Automáticamente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let contadorCampos = 0;
        
        function agregarCampo() {
            contadorCampos++;
            const container = document.getElementById('campos-container');
            const campoHtml = `
                <div class="campo-dinamico" id="campo-${contadorCampos}">
                    <button type="button" class="btn-remove-campo" onclick="eliminarCampo(${contadorCampos})">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="row">
                        <div class="col-md-5">
                            <label class="form-label">Nombre del Campo (Ej: Lúmenes)</label>
                            <input type="text" name="campos_nombres[]" class="form-control" placeholder="Nombre descriptivo" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo de Campo</label>
                            <select name="campos_tipos[]" class="form-select">
                                <option value="text">Texto</option>
                                <option value="number">Número</option>
                                <option value="email">Email</option>
                                <option value="tel">Teléfono</option>
                                <option value="date">Fecha</option>
                                <option value="textarea">Área de Texto</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Opciones</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="campos_unicos[${contadorCampos}]" id="unico-${contadorCampos}">
                                <label class="form-check-label" for="unico-${contadorCampos}">
                                    Es Único (UNIQUE)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', campoHtml);
        }
        
        function eliminarCampo(id) {
            const campo = document.getElementById(`campo-${id}`);
            if (campo) {
                campo.style.opacity = '0';
                campo.style.transform = 'scale(0.9)';
                setTimeout(() => campo.remove(), 300);
            }
        }
        
        // Agregar un campo por defecto al cargar
        document.addEventListener('DOMContentLoaded', function() {
            agregarCampo();
        });
    </script>
</body>
</html>