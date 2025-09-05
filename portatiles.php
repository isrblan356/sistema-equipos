<?php
// Inicia el búfer de salida para prevenir errores de "headers already sent"
ob_start();

require_once 'config.php';
verificarLogin();

// Habilitar reporte de errores detallado para depuración (puedes comentar estas 2 líneas en un entorno de producción)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Carga la configuración de los tipos de hardware desde un archivo separado ---
// Esto permite que el archivo de configuración sea modificado sin tocar la lógica principal.
$hardware_types = require 'hardware_config.php';

// --- DEFINICIONES GLOBALES ---
$estados_generales = ['Operativo', 'En Mantenimiento', 'Dañado', 'De Baja'];
$estados_revision = ['Bueno', 'Regular', 'Malo'];

$pdo = conectarDB();
$mensajeHtml = '';

// --- DETERMINAR LA VISTA ACTUAL ---
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'inventario';
// Si el array de configuración no está vacío, el tipo por defecto es el primero.
$tipo_actual = isset($_GET['tipo']) && isset($hardware_types[$_GET['tipo']]) ? $_GET['tipo'] : (!empty($hardware_types) ? array_key_first($hardware_types) : null);
$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener la configuración para el tipo de hardware actual.
$config_actual = $hardware_types[$tipo_actual] ?? null;

// Si no hay ninguna configuración de hardware, no se puede continuar.
if (!$config_actual && $vista !== 'repuestos') {
    die('Error Crítico: No se ha definido ningún tipo de hardware en <code>hardware_config.php</code>. Por favor, usa la <a href="crear_vista.php">herramienta de creación de vistas</a> para añadir al menos uno.');
}

// Mostrar mensajes flash de la sesión (usando clases de Bootstrap)
if (isset($_SESSION['mensaje_flash'])) { $mensajeHtml = '<div class="alert alert-success">' . $_SESSION['mensaje_flash'] . '</div>'; unset($_SESSION['mensaje_flash']); }
if (isset($_SESSION['error_flash'])) { $mensajeHtml = '<div class="alert alert-danger">' . $_SESSION['error_flash'] . '</div>'; unset($_SESSION['error_flash']); }

// --- LÓGICA DE PROCESAMIENTO DE FORMULARIOS (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        $accion = $_POST['accion'];
        $tipo_form = isset($_POST['tipo']) && isset($hardware_types[$_POST['tipo']]) ? $_POST['tipo'] : null;

        if (in_array($accion, ['agregar_item', 'editar_item']) && $tipo_form) {
            $config_form = $hardware_types[$tipo_form];
            $tabla = $config_form['tabla'];
            $id = ($accion == 'editar_item') ? intval($_POST['id']) : 0;
            
            // Validar campos únicos antes de insertar/actualizar
            foreach ($config_form['campos'] as $nombre_campo => $config_campo) {
                if (!empty($config_campo['unique'])) {
                    $valor_unico = limpiarDatos($_POST[$nombre_campo]);
                    if (empty($valor_unico)) continue;
                    $sql_check = "SELECT id FROM $tabla WHERE $nombre_campo = ?";
                    $params_check = [$valor_unico];
                    if ($accion == 'editar_item') {
                        $sql_check .= " AND id != ?";
                        $params_check[] = $id;
                    }
                    $stmt_check = $pdo->prepare($sql_check);
                    $stmt_check->execute($params_check);
                    if ($stmt_check->fetch()) {
                        throw new Exception("El campo '{$config_campo['label']}' con valor '{$valor_unico}' ya existe en otro registro.");
                    }
                }
            }

            // Preparar columnas y valores para la consulta
            $campos_config = $config_form['campos'];
            $columnas = []; $valores = [];
            foreach ($campos_config as $nombre => $config) {
                $columnas[] = $nombre;
                $valor = isset($_POST[$nombre]) ? limpiarDatos($_POST[$nombre]) : null;
                $valores[] = ($valor === '' && !in_array($config['type'], ['text', 'textarea'])) ? null : $valor;
            }
            $columnas[] = 'estado'; $valores[] = limpiarDatos($_POST['estado']);
            $columnas[] = 'notas'; $valores[] = limpiarDatos($_POST['notas']);

            if ($accion == 'agregar_item') {
                $placeholders = implode(', ', array_fill(0, count($columnas), '?'));
                $sql = "INSERT INTO $tabla (" . implode(', ', $columnas) . ") VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($valores);
                $_SESSION['mensaje_flash'] = $config_form['singular'] . ' agregado exitosamente.';
            } else { // editar_item
                $set_parts = array_map(fn($col) => "$col = ?", $columnas);
                $sql = "UPDATE $tabla SET " . implode(', ', $set_parts) . " WHERE id = ?";
                $valores[] = $id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($valores);
                $_SESSION['mensaje_flash'] = $config_form['singular'] . ' actualizado exitosamente.';
            }
        } 
        elseif ($accion == 'eliminar_item' && $tipo_form) {
            $id = intval($_POST['id']);
            $tabla = $hardware_types[$tipo_form]['tabla'];
            $pdo->beginTransaction();
            $stmt_del_item = $pdo->prepare("DELETE FROM $tabla WHERE id = ?"); $stmt_del_item->execute([$id]);
            $stmt_del_rev = $pdo->prepare("DELETE FROM hardware_revisiones WHERE hardware_id = ? AND hardware_tipo = ?"); $stmt_del_rev->execute([$id, $tipo_form]);
            $pdo->commit();
            $_SESSION['mensaje_flash'] = $hardware_types[$tipo_form]['singular'] . ' y su historial han sido eliminados.';
        } elseif ($accion == 'agregar_revision' && $tipo_form) {
            $item_id_rev = intval($_POST['item_id']);
            $estado_revision = limpiarDatos($_POST['estado_revision']);
            $observaciones = limpiarDatos($_POST['observaciones']);
            $nuevo_estado_item = limpiarDatos($_POST['nuevo_estado_item']);
            $tecnico_id = $_SESSION['usuario_id'];
            $tabla = $hardware_types[$tipo_form]['tabla'];
            
            $pdo->beginTransaction();
            $stmt_rev = $pdo->prepare("INSERT INTO hardware_revisiones (hardware_id, hardware_tipo, tecnico_id, estado_revision, observaciones) VALUES (?, ?, ?, ?, ?)");
            $stmt_rev->execute([$item_id_rev, $tipo_form, $tecnico_id, $estado_revision, $observaciones]);
            $stmt_item = $pdo->prepare("UPDATE $tabla SET estado = ?, ultima_revision = NOW() WHERE id = ?");
            $stmt_item->execute([$nuevo_estado_item, $item_id_rev]);
            $pdo->commit();
            $_SESSION['mensaje_flash'] = 'Revisión agregada y estado del equipo actualizado.';
        } elseif ($accion == 'agregar_repuesto') {
            $nombre = limpiarDatos($_POST['nombre']); $cantidad = intval($_POST['cantidad']);
            $stmt = $pdo->prepare("INSERT INTO repuestos (nombre, cantidad) VALUES (?, ?)"); $stmt->execute([$nombre, $cantidad]);
            $_SESSION['mensaje_flash'] = 'Repuesto agregado al inventario.';
        } elseif ($accion == 'editar_repuesto') {
            $id = intval($_POST['id']); $nombre = limpiarDatos($_POST['nombre']); $cantidad = intval($_POST['cantidad']);
            $stmt = $pdo->prepare("UPDATE repuestos SET nombre = ?, cantidad = ? WHERE id = ?"); $stmt->execute([$nombre, $cantidad, $id]);
            $_SESSION['mensaje_flash'] = 'Repuesto actualizado correctamente.';
        } elseif ($accion == 'eliminar_repuesto') {
            $id = intval($_POST['id']);
            $stmt = $pdo->prepare("DELETE FROM repuestos WHERE id = ?"); $stmt->execute([$id]);
            $_SESSION['mensaje_flash'] = 'Repuesto eliminado del inventario.';
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($e->getCode() == '42S02') { // Error específico para "Table not found"
             $_SESSION['error_flash'] = 'Error de base de datos: La tabla para este tipo de hardware no fue encontrada. Asegúrate de haber ejecutado el código SQL generado.';
        } elseif ($e->getCode() == 23000) {
            $_SESSION['error_flash'] = 'Error: No se pudo guardar. Es posible que un campo marcado como único (Serie, IMEI, MAC) ya exista en otro registro.';
        } else {
            $_SESSION['error_flash'] = 'Error de base de datos: ' . $e->getMessage();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $_SESSION['error_flash'] = 'Error: ' . $e->getMessage();
    }
    
    // --- Redirección después del POST para evitar reenvío del formulario ---
    $redirect_url = 'portatiles.php';
    if ($vista === 'repuestos' || strpos($accion, 'repuesto') !== false) {
        $redirect_url = "portatiles.php?vista=repuestos";
    } elseif ($vista === 'revisiones' || $accion == 'agregar_revision') {
        $redirect_id = isset($item_id_rev) ? $item_id_rev : $item_id;
        $redirect_url = "portatiles.php?vista=revisiones&tipo=" . ($tipo_form ?? $tipo_actual) . "&id=" . $redirect_id;
    } else {
        $redirect_url = "portatiles.php?vista=inventario&tipo=" . ($tipo_form ?? $tipo_actual);
    }
    header("Location: " . $redirect_url);
    exit();
}

// --- LÓGICA DE CARGA DE DATOS PARA LAS VISTAS (GET) ---
$items = []; $repuestos = []; $revisiones = []; $item_actual = null;
if ($vista === 'inventario' && $config_actual) {
    try {
        $items = $pdo->query("SELECT * FROM {$config_actual['tabla']} ORDER BY nombre_equipo ASC")->fetchAll();
    } catch (PDOException $e) {
        $mensajeHtml = '<div class="alert alert-danger">Error al cargar el inventario: La tabla <strong>' . htmlspecialchars($config_actual['tabla']) . '</strong> no existe. Por favor, créala usando la <a href="crear_vista.php" class="alert-link">herramienta de generación de vistas</a>.</div>';
    }
} elseif ($vista === 'revisiones' && $item_id > 0 && $config_actual) {
    $stmt_item = $pdo->prepare("SELECT * FROM {$config_actual['tabla']} WHERE id = ?"); $stmt_item->execute([$item_id]);
    $item_actual = $stmt_item->fetch();
    if (!$item_actual) { header("Location: portatiles.php?tipo=$tipo_actual"); exit(); }
    $stmt_revisiones = $pdo->prepare("SELECT r.*, u.nombre as tecnico_nombre FROM hardware_revisiones r LEFT JOIN usuarios u ON r.tecnico_id = u.id WHERE r.hardware_id = ? AND r.hardware_tipo = ? ORDER BY r.fecha_revision DESC");
    $stmt_revisiones->execute([$item_id, $tipo_actual]);
    $revisiones = $stmt_revisiones->fetchAll();
} elseif ($vista === 'repuestos') {
    $repuestos = $pdo->query("SELECT * FROM repuestos ORDER BY nombre ASC")->fetchAll();
}

// --- FUNCIONES AUXILIARES DE RENDERIZADO ---
function render_form_fields($campos, $item = null) {
    $html = '';
    foreach ($campos as $name => $config) {
        $value = $item ? htmlspecialchars($item[$name] ?? '') : '';
        $required = $config['required'] ?? false ? 'required' : '';
        $placeholder = $config['placeholder'] ?? '';
        $label = htmlspecialchars($config['label']);
        $html .= '<div class="col-md-6"><div class="form-group">';
        $html .= "<label for='field_{$name}'>{$label} " . ($required ? '*' : '') . "</label>";
        $type = $config['type'] ?? 'text';
        $html .= "<input type='{$type}' id='field_{$name}' name='{$name}' class='form-control' value='{$value}' placeholder='{$placeholder}' {$required}>";
        $html .= '</div></div>';
    }
    return $html;
}

// Envía el contenido del búfer al navegador
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Hardware</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary-color: #0ea5e9; --secondary-color: #3b82f6; --text-color: #334155; --bg-color: #f1f5f9; --card-bg: white; --shadow: 0 10px 30px rgba(0,0,0,0.08); --border-radius: 15px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .header { background: var(--card-bg); padding: 1.25rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; }
        .header h1 { font-size: 1.75rem; display: flex; align-items: center; gap: 12px; }
        .header h1 i { color: var(--primary-color); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-info a { text-decoration: none; color: inherit; font-weight: 500; }
        .logout-btn { background: #fee2e2; color: #ef4444; font-size: 0.9rem; font-weight: 500; text-decoration: none; padding: 8px 16px; border-radius: 20px; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
        .logout-btn:hover { background: #ef4444; color: white; }
        .container { max-width: 1600px; margin: 2rem auto; padding: 0 2rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h2 { font-size: 2.5rem; }
        .card { background: var(--card-bg); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow); margin-bottom: 2rem; }
        .card-header { background: none; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { font-size: 1.5rem; }
        .btn { border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 12px 24px; font-size: 1rem; }
        .btn-sm { padding: 8px 16px; font-size: 0.875rem; }
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn-secondary { background: #64748b; color: white; }
        .btn-success { background-color: #16a34a; color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        input, select, textarea { font-family: inherit; font-size: 1rem; width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: #475569; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        tr:hover { background: #f8fafc; }
        .badge { font-size: 0.8rem; padding: 0.4em 0.8em; border-radius: 20px; font-weight: 600; }
        .bg-operativo { background-color: #dcfce7; color: #166534; } .bg-en-mantenimiento { background-color: #fef9c3; color: #854d0e; }
        .bg-dañado, .bg-de-baja { background-color: #fee2e2; color: #991b1b; }
        .bg-bueno { background-color: #dcfce7; color: #166534; } .bg-regular { background-color: #fef9c3; color: #854d0e; } .bg-malo { background-color: #fee2e2; color: #991b1b; }
        .table-actions { display: flex; gap: 0.5rem; }
        .table-actions .btn { padding: 8px 12px; border-radius: 20px; }
        .modal-content { border: none; border-radius: var(--border-radius); }
        .modal-header { border-bottom: none; }
        .modal-footer { border-top: 1px solid #e2e8f0; padding-top: 1rem; }
        .modal-body .row > div { margin-bottom: 1rem; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; }
        .nav-tabs { border-bottom: 2px solid #dee2e6; margin-bottom: 0; flex-wrap: wrap; }
        .nav-tabs .nav-link { border: none; border-bottom: 2px solid transparent; color: #6c757d; font-weight: 600; padding: 0.75rem 1.25rem; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .nav-tabs .nav-link.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-server"></i> Gestión de Hardware</h1>
            <div class="user-info">
                <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
                <a class="logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?= $mensajeHtml; ?>
        
        <div class="d-flex justify-content-between align-items-center flex-wrap-reverse gap-3 mb-4">
             <ul class="nav nav-tabs">
                <?php if (!empty($hardware_types)): ?>
                    <?php foreach($hardware_types as $key => $config): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= ($vista !== 'repuestos' && $key === $tipo_actual) ? 'active' : '' ?>" href="portatiles.php?vista=inventario&tipo=<?= $key ?>">
                                <i class="<?= htmlspecialchars($config['icono']) ?>"></i> <?= htmlspecialchars($config['plural']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?= $vista === 'repuestos' ? 'active' : '' ?>" href="portatiles.php?vista=repuestos">
                        <i class="fas fa-tools"></i> Repuestos
                    </a>
                </li>
            </ul>
            <a href="crear_vista.php" class="btn btn-success">
                <i class="fas fa-magic"></i> Crear Nueva Vista
            </a>
        </div>

        <?php if ($vista === 'inventario' && $config_actual): ?>
            <div class="page-header">
                <h2>Inventario de <?= htmlspecialchars($config_actual['plural']) ?></h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarItem">
                    <i class="fas fa-plus"></i> Agregar <?= htmlspecialchars($config_actual['singular']) ?>
                </button>
            </div>
            <div class="card">
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre Equipo</th>
                                <th>Usuario/Ubicación</th>
                                <th>Marca/Modelo</th>
                                <th>Identificador Único</th>
                                <th>Estado</th>
                                <th>Última Revisión</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="7" class="text-center p-5">No hay <?= strtolower(htmlspecialchars($config_actual['plural'])) ?> registrados.</td></tr>
                            <?php else: foreach ($items as $item): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($item['nombre_equipo']); ?></strong></td>
                                    <td><?= htmlspecialchars($item['usuario_asignado'] ?? $item['ubicacion'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars(($item['marca'] ?? '') . ' ' . ($item['modelo'] ?? '')); ?></td>
                                    <td><code><?= htmlspecialchars($item['numero_serie'] ?? $item['imei'] ?? $item['mac_address'] ?? 'N/A'); ?></code></td>
                                    <td><span class="badge bg-<?= strtolower(str_replace(' ', '-', $item['estado'])); ?>"><?= htmlspecialchars($item['estado']); ?></span></td>
                                    <td><?= $item['ultima_revision'] ? date('d/m/Y', strtotime($item['ultima_revision'])) : 'Nunca'; ?></td>
                                    <td class="table-actions">
                                        <a href="portatiles.php?vista=revisiones&tipo=<?= $tipo_actual ?>&id=<?= $item['id']; ?>" class="btn btn-sm btn-outline-info" title="Ver Revisiones"><i class="fas fa-history"></i></a>
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditarItem<?= $item['id'] ?>" title="Editar"><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este equipo y todo su historial?');">
                                            <input type="hidden" name="id" value="<?= $item['id'] ?>"><input type="hidden" name="tipo" value="<?= $tipo_actual ?>"><input type="hidden" name="accion" value="eliminar_item">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($vista === 'revisiones' && isset($item_actual)): ?>
            <div class="page-header">
                <a href="portatiles.php?vista=inventario&tipo=<?= $tipo_actual ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
                <h2 style="flex-grow: 1; text-align: center;">Historial de: <?= htmlspecialchars($item_actual['nombre_equipo']); ?></h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarRevision"><i class="fas fa-plus"></i> Nueva Revisión</button>
            </div>
            <div class="card">
                <div class="card-header"><h3><i class="<?= htmlspecialchars($config_actual['icono']) ?>"></i> Resumen del Equipo</h3></div>
                <div class="summary-grid">
                    <?php foreach ($config_actual['campos'] as $key => $config): 
                        if (isset($item_actual[$key]) && $item_actual[$key] !== ''): ?>
                        <div><strong><?= htmlspecialchars($config['label']) ?>:</strong><br><?= htmlspecialchars($item_actual[$key]) ?></div>
                    <?php endif; endforeach; ?>
                    <div><strong>Estado Actual:</strong><br><span class="badge bg-<?= strtolower(str_replace(' ', '-', $item_actual['estado'])); ?>"><?= htmlspecialchars($item_actual['estado']); ?></span></div>
                    <div><strong>Última Revisión:</strong><br><?= $item_actual['ultima_revision'] ? date('d/m/Y H:i', strtotime($item_actual['ultima_revision'])) : 'Nunca'; ?></div>
                </div>
            </div>
            <div class="card">
                 <div class="card-header"><h3><i class="fas fa-history"></i> Historial de Revisiones</h3></div>
                 <table>
                    <thead><tr><th>Fecha</th><th>Técnico</th><th>Estado Reportado</th><th>Observaciones</th></tr></thead>
                    <tbody>
                        <?php if (empty($revisiones)): ?>
                            <tr><td colspan="4" class="text-center p-5">Este equipo no tiene revisiones registradas.</td></tr>
                        <?php else: foreach ($revisiones as $revision): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($revision['fecha_revision'])); ?></td>
                                <td><?= htmlspecialchars($revision['tecnico_nombre'] ?? 'N/A'); ?></td>
                                <td><span class="badge bg-<?= strtolower(htmlspecialchars($revision['estado_revision'])); ?>"><?= htmlspecialchars($revision['estado_revision']); ?></span></td>
                                <td><?= nl2br(htmlspecialchars($revision['observaciones'])); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($vista === 'repuestos'): ?>
            <div class="page-header"><h2>Inventario de Repuestos</h2><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarRepuesto"><i class="fas fa-plus"></i> Agregar Repuesto</button></div>
            <div class="card">
                <table>
                    <thead><tr><th>Nombre del Repuesto</th><th>Cantidad en Stock</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php if (empty($repuestos)): ?>
                            <tr><td colspan="3" class="text-center p-5">No hay repuestos registrados.</td></tr>
                        <?php else: foreach ($repuestos as $repuesto): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($repuesto['nombre']); ?></strong></td>
                                <td><h3><?= htmlspecialchars($repuesto['cantidad']); ?></h3></td>
                                <td class="table-actions">
                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditarRepuesto<?= $repuesto['id'] ?>" title="Editar"><i class="fas fa-edit"></i></button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este repuesto?');"><input type="hidden" name="id" value="<?= $repuesto['id'] ?>"><input type="hidden" name="accion" value="eliminar_repuesto"><button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button></form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modales dinámicos -->
    <?php if (($vista === 'inventario' || $vista === 'revisiones') && $config_actual): ?>
        <div class="modal fade" id="modalAgregarItem" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Agregar Nuevo <?= htmlspecialchars($config_actual['singular']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form method="POST">
                        <input type="hidden" name="accion" value="agregar_item"><input type="hidden" name="tipo" value="<?= $tipo_actual ?>">
                        <div class="modal-body"><div class="row">
                            <?= render_form_fields($config_actual['campos']) ?>
                            <div class="col-md-6"><div class="form-group"><label>Estado Inicial *</label><select name="estado" class="form-select" required><?php foreach($estados_generales as $e) echo "<option value='".htmlspecialchars($e)."'>".htmlspecialchars($e)."</option>"; ?></select></div></div>
                            <div class="col-12"><div class="form-group"><label>Notas Adicionales</label><textarea name="notas" rows="3" class="form-control"></textarea></div></div>
                        </div></div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
                    </form>
                </div>
            </div>
        </div>
        <?php if ($vista === 'inventario' && !empty($items)): foreach ($items as $item): ?>
            <div class="modal fade" id="modalEditarItem<?= $item['id'] ?>" tabindex="-1">
                 <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Editar <?= htmlspecialchars($config_actual['singular']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <form method="POST">
                            <input type="hidden" name="accion" value="editar_item"><input type="hidden" name="tipo" value="<?= $tipo_actual ?>"><input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <div class="modal-body"><div class="row">
                                <?= render_form_fields($config_actual['campos'], $item) ?>
                                <div class="col-md-6"><div class="form-group"><label>Estado *</label><select name="estado" class="form-select" required><?php foreach($estados_generales as $e) { $sel = ($item['estado'] == $e) ? 'selected' : ''; echo "<option value='".htmlspecialchars($e)."' $sel>".htmlspecialchars($e)."</option>"; } ?></select></div></div>
                                <div class="col-12"><div class="form-group"><label>Notas Adicionales</label><textarea name="notas" rows="3" class="form-control"><?= htmlspecialchars($item['notas'] ?? '') ?></textarea></div></div>
                            </div></div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar Cambios</button></div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
        <?php if ($vista === 'revisiones' && isset($item_actual)): ?>
            <div class="modal fade" id="modalAgregarRevision" tabindex="-1">
                 <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Agregar Nueva Revisión</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <form method="POST">
                            <input type="hidden" name="accion" value="agregar_revision"><input type="hidden" name="tipo" value="<?= $tipo_actual ?>"><input type="hidden" name="item_id" value="<?= $item_actual['id'] ?>">
                            <div class="modal-body">
                                <div class="form-group"><label>Estado Reportado</label><select name="estado_revision" class="form-select" required><?php foreach($estados_revision as $e) echo "<option value='".htmlspecialchars($e)."'>".htmlspecialchars($e)."</option>"; ?></select></div>
                                <div class="form-group"><label>Nuevo Estado General</label><select name="nuevo_estado_portatil" class="form-select" required><?php foreach($estados_generales as $e) { $sel = ($item_actual['estado'] == $e) ? 'selected' : ''; echo "<option value='".htmlspecialchars($e)."' $sel>".htmlspecialchars($e)."</option>"; } ?></select></div>
                                <div class="form-group"><label>Observaciones</label><textarea name="observaciones" rows="4" class="form-control" required></textarea></div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar Revisión</button></div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Modales para Repuestos -->
    <?php if ($vista === 'repuestos'): ?>
        <div class="modal fade" id="modalAgregarRepuesto" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Agregar Repuesto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST"><div class="modal-body"><input type="hidden" name="accion" value="agregar_repuesto"><div class="form-group"><label>Nombre *</label><input type="text" name="nombre" class="form-control" required></div><div class="form-group"><label>Cantidad Inicial *</label><input type="number" name="cantidad" class="form-control" required min="0" value="0"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div></form>
        </div></div></div>
        <?php if (!empty($repuestos)): foreach ($repuestos as $repuesto): ?>
        <div class="modal fade" id="modalEditarRepuesto<?= $repuesto['id'] ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Editar Repuesto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST"><div class="modal-body"><input type="hidden" name="accion" value="editar_repuesto"><input type="hidden" name="id" value="<?= $repuesto['id'] ?>"><div class="form-group"><label>Nombre *</label><input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($repuesto['nombre']) ?>" required></div><div class="form-group"><label>Cantidad en Stock *</label><input type="number" name="cantidad" class="form-control" value="<?= htmlspecialchars($repuesto['cantidad']) ?>" required min="0"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar Cambios</button></div></form>
        </div></div></div>
        <?php endforeach; endif; ?>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>