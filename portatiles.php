<?php
ob_start();

require_once 'config.php';
verificarLogin();

ini_set('display_errors', 1);
error_reporting(E_ALL);

$hardware_types = require 'hardware_config.php';
$estados_generales = ['Operativo', 'En Mantenimiento', 'Dañado', 'De Baja'];
$estados_revision = ['Bueno', 'Regular', 'Malo'];

$pdo = conectarDB();
$mensajeHtml = '';

$vista = isset($_GET['vista']) ? $_GET['vista'] : 'inventario';
$tipo_actual = isset($_GET['tipo']) && isset($hardware_types[$_GET['tipo']]) ? $_GET['tipo'] : (!empty($hardware_types) ? array_key_first($hardware_types) : null);
$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$config_actual = $hardware_types[$tipo_actual] ?? null;

if (!$config_actual && $vista !== 'repuestos') {
    die('Error Crítico: No se ha definido ningún tipo de hardware. <a href="crear_vista.php">Crear nueva vista</a>');
}

if (isset($_SESSION['mensaje_flash'])) { $mensajeHtml = '<div class="alert alert-success">' . $_SESSION['mensaje_flash'] . '</div>'; unset($_SESSION['mensaje_flash']); }
if (isset($_SESSION['error_flash'])) { $mensajeHtml = '<div class="alert alert-danger">' . $_SESSION['error_flash'] . '</div>'; unset($_SESSION['error_flash']); }

// Verificar revisiones vencidas
$alertas_revisiones = [];
if ($config_actual) {
    try {
        $stmt_alertas = $pdo->prepare("
            SELECT h.id, h.nombre_equipo, h.proxima_revision, h.dias_frecuencia
            FROM {$config_actual['tabla']} h
            WHERE h.proxima_revision IS NOT NULL 
            AND h.proxima_revision <= CURDATE()
            AND h.estado != 'De Baja'
            ORDER BY h.proxima_revision ASC
        ");
        $stmt_alertas->execute();
        $alertas_revisiones = $stmt_alertas->fetchAll();
    } catch (PDOException $e) {
        // Tabla aún no tiene campos de revisión, ignorar
    }
}

// PROCESAMIENTO DE FORMULARIOS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        $accion = $_POST['accion'];
        $tipo_form = isset($_POST['tipo']) && isset($hardware_types[$_POST['tipo']]) ? $_POST['tipo'] : null;

        if (in_array($accion, ['agregar_item', 'editar_item']) && $tipo_form) {
            $config_form = $hardware_types[$tipo_form];
            $tabla = $config_form['tabla'];
            $id = ($accion == 'editar_item') ? intval($_POST['id']) : 0;
            
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
                        throw new Exception("El campo '{$config_campo['label']}' con valor '{$valor_unico}' ya existe.");
                    }
                }
            }

            $campos_config = $config_form['campos'];
            $columnas = []; $valores = [];
            foreach ($campos_config as $nombre => $config) {
                $columnas[] = $nombre;
                $valor = isset($_POST[$nombre]) ? limpiarDatos($_POST[$nombre]) : null;
                $valores[] = ($valor === '' && !in_array($config['type'], ['text', 'textarea'])) ? null : $valor;
            }
            $columnas[] = 'estado'; $valores[] = limpiarDatos($_POST['estado']);
            $columnas[] = 'notas'; $valores[] = limpiarDatos($_POST['notas']);
            
            // Campos de revisión programada
            if (isset($_POST['dias_frecuencia']) && !empty($_POST['dias_frecuencia'])) {
                $dias = intval($_POST['dias_frecuencia']);
                $columnas[] = 'dias_frecuencia';
                $valores[] = $dias;
                
                // Calcular próxima revisión
                $fecha_base = !empty($_POST['fecha_primera_revision']) ? $_POST['fecha_primera_revision'] : date('Y-m-d');
                $columnas[] = 'proxima_revision';
                $valores[] = date('Y-m-d', strtotime($fecha_base . " + $dias days"));
            }

            if ($accion == 'agregar_item') {
                $placeholders = implode(', ', array_fill(0, count($columnas), '?'));
                $sql = "INSERT INTO $tabla (" . implode(', ', $columnas) . ") VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($valores);
                $_SESSION['mensaje_flash'] = $config_form['singular'] . ' agregado exitosamente.';
            } else {
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
            $_SESSION['mensaje_flash'] = $hardware_types[$tipo_form]['singular'] . ' y su historial eliminados.';
        } 
        elseif ($accion == 'agregar_revision' && $tipo_form) {
            $item_id_rev = intval($_POST['item_id']);
            $estado_revision = limpiarDatos($_POST['estado_revision']);
            $observaciones = limpiarDatos($_POST['observaciones']);
            $nuevo_estado_item = limpiarDatos($_POST['nuevo_estado_item']);
            $tecnico_id = $_SESSION['usuario_id'];
            $tabla = $hardware_types[$tipo_form]['tabla'];
            
            $pdo->beginTransaction();
            $stmt_rev = $pdo->prepare("INSERT INTO hardware_revisiones (hardware_id, hardware_tipo, tecnico_id, estado_revision, observaciones) VALUES (?, ?, ?, ?, ?)");
            $stmt_rev->execute([$item_id_rev, $tipo_form, $tecnico_id, $estado_revision, $observaciones]);
            
            // Actualizar próxima revisión si tiene frecuencia configurada
            $stmt_get_freq = $pdo->prepare("SELECT dias_frecuencia FROM $tabla WHERE id = ?");
            $stmt_get_freq->execute([$item_id_rev]);
            $item_data = $stmt_get_freq->fetch();
            
            $sql_update = "UPDATE $tabla SET estado = ?, ultima_revision = NOW()";
            $params_update = [$nuevo_estado_item];
            
            if ($item_data && $item_data['dias_frecuencia'] > 0) {
                $dias_freq = intval($item_data['dias_frecuencia']);
                $sql_update .= ", proxima_revision = DATE_ADD(CURDATE(), INTERVAL ? DAY)";
                $params_update[] = $dias_freq;
            }
            
            $sql_update .= " WHERE id = ?";
            $params_update[] = $item_id_rev;
            
            $stmt_item = $pdo->prepare($sql_update);
            $stmt_item->execute($params_update);
            
            $pdo->commit();
            $_SESSION['mensaje_flash'] = 'Revisión agregada y próxima revisión programada.';
        } 
        elseif ($accion == 'agregar_repuesto') {
            $nombre = limpiarDatos($_POST['nombre']); $cantidad = intval($_POST['cantidad']);
            $stmt = $pdo->prepare("INSERT INTO repuestos (nombre, cantidad) VALUES (?, ?)"); $stmt->execute([$nombre, $cantidad]);
            $_SESSION['mensaje_flash'] = 'Repuesto agregado.';
        } 
        elseif ($accion == 'editar_repuesto') {
            $id = intval($_POST['id']); $nombre = limpiarDatos($_POST['nombre']); $cantidad = intval($_POST['cantidad']);
            $stmt = $pdo->prepare("UPDATE repuestos SET nombre = ?, cantidad = ? WHERE id = ?"); $stmt->execute([$nombre, $cantidad, $id]);
            $_SESSION['mensaje_flash'] = 'Repuesto actualizado.';
        } 
        elseif ($accion == 'eliminar_repuesto') {
            $id = intval($_POST['id']);
            $stmt = $pdo->prepare("DELETE FROM repuestos WHERE id = ?"); $stmt->execute([$id]);
            $_SESSION['mensaje_flash'] = 'Repuesto eliminado.';
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($e->getCode() == '42S02') {
             $_SESSION['error_flash'] = 'Error: Tabla no encontrada. Ejecuta el código SQL generado.';
        } elseif ($e->getCode() == 23000) {
            $_SESSION['error_flash'] = 'Error: Campo único duplicado.';
        } else {
            $_SESSION['error_flash'] = 'Error de BD: ' . $e->getMessage();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $_SESSION['error_flash'] = 'Error: ' . $e->getMessage();
    }
    
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

$items = []; $repuestos = []; $revisiones = []; $item_actual = null;
if ($vista === 'inventario' && $config_actual) {
    try {
        $items = $pdo->query("SELECT * FROM {$config_actual['tabla']} ORDER BY nombre_equipo ASC")->fetchAll();
    } catch (PDOException $e) {
        $mensajeHtml = '<div class="alert alert-danger">Error al cargar inventario. <a href="crear_vista.php">Crear vista</a></div>';
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
        :root { --primary-color: #0ea5e9; --secondary-color: #3b82f6; --danger-color: #dc2626; --warning-color: #f59e0b; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f1f5f9; color: #334155; }
        .header { background: white; padding: 1.25rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; flex-wrap: wrap; gap: 1rem; }
        .header h1 { font-size: 1.75rem; display: flex; align-items: center; gap: 12px; }
        .user-info { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
        .logout-btn { background: #fee2e2; color: #ef4444; font-size: 0.9rem; font-weight: 500; text-decoration: none; padding: 8px 16px; border-radius: 20px; transition: all 0.3s; display: flex; align-items: center; gap: 8px; }
        .logout-btn:hover { background: #ef4444; color: white; }
        .container { max-width: 1600px; margin: 2rem auto; padding: 0 1rem; }
        .alert-revisiones { background: linear-gradient(135deg, #fef3c7, #fde68a); border-left: 5px solid var(--warning-color); padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2); }
        .alert-revisiones h4 { color: #92400e; font-size: 1.25rem; margin-bottom: 1rem; }
        .alerta-item { background: white; padding: 1rem; border-radius: 8px; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; }
        .alerta-item .info { flex-grow: 1; }
        .alerta-item strong { color: var(--danger-color); }
        .badge { font-size: 0.8rem; padding: 0.4em 0.8em; border-radius: 20px; font-weight: 600; }
        .bg-vencida { background-color: #fee2e2; color: #991b1b; }
        .bg-operativo { background-color: #dcfce7; color: #166534; } 
        .bg-en-mantenimiento { background-color: #fef9c3; color: #854d0e; }
        .bg-dañado, .bg-de-baja { background-color: #fee2e2; color: #991b1b; }
        .bg-bueno { background-color: #dcfce7; color: #166534; } 
        .bg-regular { background-color: #fef9c3; color: #854d0e; } 
        .bg-malo { background-color: #fee2e2; color: #991b1b; }
        .card { background: white; border-radius: 15px; padding: 1.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); margin-bottom: 2rem; }
        .btn { border: none; border-radius: 25px; font-weight: 600; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; }
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn-success { background-color: #16a34a; color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .nav-tabs { border-bottom: 2px solid #dee2e6; margin-bottom: 0; flex-wrap: wrap; }
        .nav-tabs .nav-link { border: none; border-bottom: 2px solid transparent; color: #6c757d; font-weight: 600; padding: 0.75rem 1.25rem; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .nav-tabs .nav-link.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; }
        tr:hover { background: #f8fafc; }
        input, select, textarea { font-family: inherit; width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        @media (max-width: 768px) {
            .container { padding: 0 0.75rem; }
            .card { padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-server"></i> Gestión de Hardware</h1>
            <div class="user-info">
                <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Volver</a>
                <a class="logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?= $mensajeHtml; ?>
        
        <?php if (!empty($alertas_revisiones) && $vista === 'inventario'): ?>
        <div class="alert-revisiones">
            <h4><i class="fas fa-exclamation-triangle"></i> Revisiones Vencidas o Próximas</h4>
            <?php foreach ($alertas_revisiones as $alerta): 
                $dias_vencido = floor((strtotime(date('Y-m-d')) - strtotime($alerta['proxima_revision'])) / 86400);
            ?>
            <div class="alerta-item">
                <div class="info">
                    <strong><?= htmlspecialchars($alerta['nombre_equipo']) ?></strong>
                    <?php if ($dias_vencido > 0): ?>
                        <span class="badge bg-vencida ms-2">Vencida hace <?= $dias_vencido ?> días</span>
                    <?php else: ?>
                        <span class="badge bg-warning ms-2">Vence hoy</span>
                    <?php endif; ?>
                    <div class="text-muted small mt-1">
                        Última programada: <?= date('d/m/Y', strtotime($alerta['proxima_revision'])) ?>
                        <?php if ($alerta['dias_frecuencia']): ?>
                        | Frecuencia: cada <?= $alerta['dias_frecuencia'] ?> días
                        <?php endif; ?>
                    </div>
                </div>
                <a href="portatiles.php?vista=revisiones&tipo=<?= $tipo_actual ?>&id=<?= $alerta['id'] ?>" class="btn btn-sm btn-warning">
                    <i class="fas fa-tools"></i> Revisar Ahora
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
             <ul class="nav nav-tabs">
                <?php if (!empty($hardware_types)): foreach($hardware_types as $key => $config): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($vista !== 'repuestos' && $key === $tipo_actual) ? 'active' : '' ?>" href="portatiles.php?vista=inventario&tipo=<?= $key ?>">
                            <i class="<?= htmlspecialchars($config['icono']) ?>"></i> <?= htmlspecialchars($config['plural']) ?>
                        </a>
                    </li>
                <?php endforeach; endif; ?>
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
            <div class="d-flex justify-content-between align-items-center mb-3">
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
                                <th>Estado</th>
                                <th>Próxima Revisión</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="6" class="text-center p-5">No hay equipos registrados.</td></tr>
                            <?php else: foreach ($items as $item): 
                                $revision_vencida = false;
                                if (isset($item['proxima_revision']) && $item['proxima_revision']) {
                                    $revision_vencida = strtotime($item['proxima_revision']) <= strtotime(date('Y-m-d'));
                                }
                            ?>
                                <tr style="<?= $revision_vencida ? 'background-color: #fef3c7;' : '' ?>">
                                    <td><strong><?= htmlspecialchars($item['nombre_equipo']); ?></strong></td>
                                    <td><?= htmlspecialchars($item['usuario_asignado'] ?? $item['ubicacion'] ?? 'N/A'); ?></td>
                                    <td><?= htmlspecialchars(($item['marca'] ?? '') . ' ' . ($item['modelo'] ?? '')); ?></td>
                                    <td><span class="badge bg-<?= strtolower(str_replace(' ', '-', $item['estado'])); ?>"><?= htmlspecialchars($item['estado']); ?></span></td>
                                    <td>
                                        <?php if (isset($item['proxima_revision']) && $item['proxima_revision']): ?>
                                            <?= date('d/m/Y', strtotime($item['proxima_revision'])) ?>
                                            <?php if ($revision_vencida): ?>
                                                <span class="badge bg-vencida ms-1">VENCIDA</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No programada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="portatiles.php?vista=revisiones&tipo=<?= $tipo_actual ?>&id=<?= $item['id']; ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-history"></i></a>
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditarItem<?= $item['id'] ?>"><i class="fas fa-edit"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($vista === 'revisiones' && isset($item_actual)): ?>
            <a href="portatiles.php?vista=inventario&tipo=<?= $tipo_actual ?>" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Volver</a>
            <h2 class="text-center mb-3">Historial: <?= htmlspecialchars($item_actual['nombre_equipo']); ?></h2>
            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#modalAgregarRevision"><i class="fas fa-plus"></i> Nueva Revisión</button>
            
            <div class="card">
                <h3>Resumen del Equipo</h3>
                <div class="row">
                    <?php foreach ($config_actual['campos'] as $key => $config): 
                        if (isset($item_actual[$key]) && $item_actual[$key] !== ''): ?>
                        <div class="col-md-3"><strong><?= htmlspecialchars($config['label']) ?>:</strong><br><?= htmlspecialchars($item_actual[$key]) ?></div>
                    <?php endif; endforeach; ?>
                    <div class="col-md-3"><strong>Estado:</strong><br><span class="badge bg-<?= strtolower(str_replace(' ', '-', $item_actual['estado'])); ?>"><?= htmlspecialchars($item_actual['estado']); ?></span></div>
                    <?php if (isset($item_actual['proxima_revision']) && $item_actual['proxima_revision']): ?>
                    <div class="col-md-3"><strong>Próxima Revisión:</strong><br><?= date('d/m/Y', strtotime($item_actual['proxima_revision'])) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                 <h3>Historial de Revisiones</h3>
                 <table>
                    <thead><tr><th>Fecha</th><th>Técnico</th><th>Estado</th><th>Observaciones</th></tr></thead>
                    <tbody>
                        <?php if (empty($revisiones)): ?>
                            <tr><td colspan="4" class="text-center p-5">Sin revisiones.</td></tr>
                        <?php else: foreach ($revisiones as $revision): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($revision['fecha