<?php
require_once 'config.php';
verificarLogin();
 $pdo = conectarDB();

// --- CREACIÓN DE TABLAS INICIALES ---
 $pdo->exec("CREATE TABLE IF NOT EXISTS productos ( id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(255) NOT NULL, codigo VARCHAR(100) NOT NULL UNIQUE, part_number VARCHAR(100) NULL, descripcion TEXT, stock_actual INT DEFAULT 0, stock_minimo INT DEFAULT 0, fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP )");
 $pdo->exec("CREATE TABLE IF NOT EXISTS movimientos ( id INT AUTO_INCREMENT PRIMARY KEY, producto_id INT NOT NULL, tipo VARCHAR(50) NOT NULL, subtipo VARCHAR(50) NULL, cantidad INT NOT NULL, tecnico_id INT NULL, usuario_registro VARCHAR(255) NULL, fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE )");

// Agregar columna usuario_registro si no existe
try {
    $sedes_temp = $pdo->query("SELECT tabla_movimientos FROM sedes WHERE activa = 1")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sedes_temp as $sede_temp) {
        $tabla_mov = $sede_temp['tabla_movimientos'];
        $columns = $pdo->query("SHOW COLUMNS FROM `$tabla_mov` LIKE 'usuario_registro'")->fetchAll();
        if (empty($columns)) {
            $pdo->exec("ALTER TABLE `$tabla_mov` ADD COLUMN usuario_registro VARCHAR(255) NULL AFTER tecnico_id");
            error_log("Columna usuario_registro agregada a la tabla $tabla_mov");
        }
    }
} catch (Exception $e) {
    error_log("Error al agregar columna usuario_registro: " . $e->getMessage());
}

// --- FUNCIONES ---
function limpiar($dato) { return htmlspecialchars(trim($dato)); }
function obtenerEstadisticasSede($pdo, $tabla_productos, $tabla_movimientos) { 
    $hoy = date('Y-m-d'); 
    $stats = []; 
    $stats['total_productos'] = $pdo->query("SELECT COUNT(*) FROM `$tabla_productos`")->fetchColumn(); 
    $stats['stock_bajo'] = $pdo->query("SELECT COUNT(*) FROM `$tabla_productos` WHERE stock_actual <= stock_minimo AND stock_actual > 0")->fetchColumn(); 
    $stats['sin_stock'] = $pdo->query("SELECT COUNT(*) FROM `$tabla_productos` WHERE stock_actual <= stock_minimo")->fetchColumn(); 
    $stats['movimientos_hoy'] = $pdo->query("SELECT COUNT(*) FROM `$tabla_movimientos` WHERE DATE(fecha) = '$hoy'")->fetchColumn(); 
    return $stats; 
}
function hex2rgb($hex) { 
    $hex = str_replace("#", "", $hex); 
    if(strlen($hex) == 3) { 
        $r = hexdec(substr($hex,0,1).substr($hex,0,1)); 
        $g = hexdec(substr($hex,1,1).substr($hex,1,1)); 
        $b = hexdec(substr($hex,2,1).substr($hex,2,1)); 
    } else { 
        $r = hexdec(substr($hex,0,2)); 
        $g = hexdec(substr($hex,2,2)); 
        $b = hexdec(substr($hex,4,2)); 
    } 
    return "$r, $g, $b"; 
}

// --- OBTENER TÉCNICOS ---
 $tecnicos = [];
try {
    $tecnicos_query = $pdo->query("SELECT id, nombre FROM tecnicos WHERE estado = 'activo' AND nombre NOT LIKE '%Maria Camila Ossa%' ORDER BY nombre");
    if ($tecnicos_query) { $tecnicos = $tecnicos_query->fetchAll(PDO::FETCH_ASSOC); }
} catch (Exception $e) {
    try {
        $tecnicos_query = $pdo->query("SELECT id, nombre FROM tecnicos WHERE nombre NOT LIKE '%Maria Camila Ossa%' ORDER BY nombre");
        if ($tecnicos_query) { $tecnicos = $tecnicos_query->fetchAll(PDO::FETCH_ASSOC); }
    } catch (Exception $e2) {
        $tecnicos = [['id' => 1, 'nombre' => 'Técnico de Ejemplo 1'], ['id' => 2, 'nombre' => 'Técnico de Ejemplo 2']];
    }
}

// --- CONFIGURACIÓN DINÁMICA DE SEDES ---
 $sedes_query = $pdo->query("SELECT * FROM sedes WHERE activa = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
 $sedes_config = [];
foreach ($sedes_query as $sede) { $sedes_config[$sede['id']] = $sede; }

// --- VISTA ACTUAL ---
 $vista_actual = isset($_GET['sede_id']) ? $_GET['sede_id'] : 'vista_inventario_dashboard';

// --- PROCESAR CONFIRMACIÓN DE MOVIMIENTO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirmar_movimiento']) && $_POST['confirmar_movimiento'] == '1') {
    $config = $sedes_config[$vista_actual];
    $tabla_productos = $config['tabla_productos'];
    $tabla_movimientos = $config['tabla_movimientos'];
    
    $producto_id = intval($_POST['producto_id']);
    $tipo = limpiar($_POST['tipo']);
    $tecnico_id = !empty($_POST['tecnico_id']) ? intval($_POST['tecnico_id']) : NULL;
    $cantidad = abs(intval($_POST['cantidad']));
    
    // Obtener el usuario logueado
    $usuario_registro = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 
                       (isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 
                       (isset($_SESSION['email']) ? $_SESSION['email'] : 'Usuario desconocido'));
    
    error_log("DEBUG - Valores a insertar: Producto ID: $producto_id | Tipo: $tipo | Cantidad: $cantidad | Técnico ID: " . ($tecnico_id ?? 'NULL'));
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO `$tabla_movimientos` (producto_id, tipo, cantidad, tecnico_id, usuario_registro, fecha) VALUES (?, ?, ?, ?, ?, NOW())"); 
        $result = $stmt->execute([$producto_id, $tipo, $cantidad, $tecnico_id, $usuario_registro]);
        
        if (!$result) {
            error_log("ERROR: Fallo al insertar movimiento");
        }
        
        $inserted_id = $pdo->lastInsertId();
        error_log("Movimiento insertado con ID: " . $inserted_id);
        
        $tipos_entrada = ['Desinstalaciones', 'Sobrantes'];
        $tipos_salida = ['Preinstalaciones'];
        $tipos_sin_afectar_stock = ['Instalaciones OK'];
        
        $update_sql = null;
        if (in_array($tipo, $tipos_entrada)) {
            $update_sql = "UPDATE `$tabla_productos` SET stock_actual = stock_actual + ? WHERE id = ?";
            error_log("Aplicando entrada de stock: +" . $cantidad);
        } elseif (in_array($tipo, $tipos_salida)) {
            $update_sql = "UPDATE `$tabla_productos` SET stock_actual = stock_actual - ? WHERE id = ?";
            error_log("Aplicando salida de stock: -" . $cantidad);
        } elseif (in_array($tipo, $tipos_sin_afectar_stock)) {
            error_log("Tipo 'Instalaciones OK': Movimiento registrado sin afectar stock");
        }
        
        if ($update_sql) {
            $stmt_update = $pdo->prepare($update_sql); 
            $update_result = $stmt_update->execute([$cantidad, $producto_id]);
            if (!$update_result) {
                error_log("ERROR: Fallo al actualizar stock");
            }
        }
        
        $pdo->commit();
        error_log("Transacción completada exitosamente");
        
        $_SESSION['mensaje_exito'] = [
            'titulo' => '✅ Movimiento Registrado',
            'mensaje' => 'El movimiento se ha registrado correctamente en el sistema.'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error al registrar movimiento: " . $e->getMessage());
        
        $_SESSION['error_validacion'] = [
            'titulo' => '❌ Error al Registrar',
            'mensaje' => 'Ocurrió un error al procesar el movimiento.',
            'detalles' => ['Error técnico: ' . $e->getMessage()]
        ];
    }
    
    header("Location: inventario.php?sede_id=" . $vista_actual); 
    exit();
}

// --- PROCESAR VALIDACIONES DE FORMULARIOS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $vista_actual != 'vista_inventario_dashboard') {
    $config = $sedes_config[$vista_actual];
    $tabla_productos = $config['tabla_productos'];
    $tabla_movimientos = $config['tabla_movimientos'];
    
    if ($_POST['accion'] == 'editar') { 
        $stmt = $pdo->prepare("UPDATE `$tabla_productos` SET nombre=?, codigo=?, part_number=?, stock_minimo=? WHERE id=?"); 
        $stmt->execute([limpiar($_POST['nombre']), limpiar($_POST['codigo']), limpiar($_POST['part_number']), intval($_POST['stock_minimo']), intval($_POST['id'])]); 
        header("Location: inventario.php?sede_id=" . $vista_actual); exit();
    }
    elseif ($_POST['accion'] == 'eliminar') { 
        $stmt = $pdo->prepare("DELETE FROM `$tabla_productos` WHERE id=?"); 
        $stmt->execute([intval($_POST['id'])]); 
        header("Location: inventario.php?sede_id=" . $vista_actual); exit();
    }
    elseif ($_POST['accion'] == 'validar_movimiento') {
        if (!empty($_POST['producto_id']) && !empty($_POST['tipo']) && !empty($_POST['cantidad'])) {
            $producto_id = intval($_POST['producto_id']);
            $tipo = limpiar($_POST['tipo']);
            $tecnico_id = !empty($_POST['tecnico_id']) ? intval($_POST['tecnico_id']) : NULL;
            $cantidad = abs(intval($_POST['cantidad']));
            
            // Obtener información del producto
            $stmt_producto = $pdo->prepare("SELECT nombre, stock_actual FROM `$tabla_productos` WHERE id = ?");
            $stmt_producto->execute([$producto_id]);
            $producto_info = $stmt_producto->fetch(PDO::FETCH_ASSOC);
            
            if (!$producto_info) {
                $_SESSION['error_validacion'] = [
                    'titulo' => '❌ Error',
                    'mensaje' => 'El producto seleccionado no existe.',
                    'detalles' => ['Por favor, selecciona un producto válido de la lista.']
                ];
                header("Location: inventario.php?sede_id=" . $vista_actual); 
                exit();
            }
            
            $nombre_producto = $producto_info['nombre'];
            $stock_disponible = $producto_info['stock_actual'];
            
            // VALIDACIÓN PARA PREINSTALACIONES - VERIFICAR STOCK DISPONIBLE
            if ($tipo == 'Preinstalaciones') {
                if ($cantidad > $stock_disponible) {
                    $diferencia = $cantidad - $stock_disponible;
                    
                    $_SESSION['error_validacion'] = [
                        'titulo' => '❌ Stock Insuficiente',
                        'mensaje' => "No hay suficiente stock en bodega para realizar esta Preinstalación.",
                        'detalles' => [
                            "Producto: <strong>{$nombre_producto}</strong>",
                            "Stock actual en bodega: <strong style='color: #27ae60;'>{$stock_disponible} unidades</strong>",
                            "Cantidad que intentas entregar: <strong style='color: #e74c3c;'>{$cantidad} unidades</strong>",
                            "Exceso detectado: <strong style='color: #e74c3c;'>+{$diferencia} unidades</strong>",
                            "💡 Solo puedes entregar hasta <strong>{$stock_disponible} unidades</strong> de este producto"
                        ]
                    ];
                    
                    error_log("VALIDACIÓN FALLIDA - Preinstalación excede stock disponible");
                    header("Location: inventario.php?sede_id=" . $vista_actual); 
                    exit();
                }
            }
            
            // VALIDACIÓN PARA SOBRANTES
            if ($tipo == 'Sobrantes') {
                if ($tecnico_id === NULL) {
                    $_SESSION['error_validacion'] = [
                        'titulo' => '⚠️ Técnico Requerido',
                        'mensaje' => "Para registrar Sobrantes es obligatorio seleccionar el técnico que devuelve el material.",
                        'detalles' => [
                            "El campo <strong>Técnico Responsable</strong> es obligatorio para el tipo <strong>Sobrantes</strong>",
                            "Esto permite validar que las devoluciones coincidan con las entregas previas"
                        ]
                    ];
                    
                    error_log("VALIDACIÓN FALLIDA - Sobrantes sin técnico asignado");
                    header("Location: inventario.php?sede_id=" . $vista_actual); 
                    exit();
                }
                
                // Obtener totales de movimientos del técnico para este producto (solo ayer y hoy)
                $stmt_preinstalaciones = $pdo->prepare("
                    SELECT COALESCE(SUM(cantidad), 0) as total_entregado 
                    FROM `$tabla_movimientos` 
                    WHERE producto_id = ? AND tecnico_id = ? AND tipo = 'Preinstalaciones'
                    AND DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE(fecha) <= CURDATE()
                ");
                $stmt_preinstalaciones->execute([$producto_id, $tecnico_id]);
                $total_entregado = $stmt_preinstalaciones->fetchColumn();
                
                $stmt_instalaciones = $pdo->prepare("
                    SELECT COALESCE(SUM(cantidad), 0) as total_instalado 
                    FROM `$tabla_movimientos` 
                    WHERE producto_id = ? AND tecnico_id = ? AND tipo = 'Instalaciones OK'
                    AND DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE(fecha) <= CURDATE()
                ");
                $stmt_instalaciones->execute([$producto_id, $tecnico_id]);
                $total_instalado = $stmt_instalaciones->fetchColumn();
                
                $stmt_sobrantes = $pdo->prepare("
                    SELECT COALESCE(SUM(cantidad), 0) as total_devuelto 
                    FROM `$tabla_movimientos` 
                    WHERE producto_id = ? AND tecnico_id = ? AND tipo = 'Sobrantes'
                    AND DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE(fecha) <= CURDATE()
                ");
                $stmt_sobrantes->execute([$producto_id, $tecnico_id]);
                $total_devuelto = $stmt_sobrantes->fetchColumn();
                
                // Calcular el total que se reportaría con este nuevo movimiento
                $total_reportado_con_nuevo = $total_instalado + $total_devuelto + $cantidad;
                
                // VALIDACIÓN 1: Verificar que NO se esté devolviendo MÁS de lo entregado
                if ($total_reportado_con_nuevo > $total_entregado) {
                    $exceso = $total_reportado_con_nuevo - $total_entregado;
                    $disponible_devolver = $total_entregado - ($total_instalado + $total_devuelto);
                    
                    $_SESSION['error_validacion'] = [
                        'titulo' => '❌ Error: Exceso en Sobrantes',
                        'mensaje' => "Estás intentando devolver MÁS equipos de los que se entregaron al técnico.",
                        'detalles' => [
                            "<strong style='color: #e74c3c;'>⚠️ PROBLEMA DETECTADO:</strong> El total reportado supera lo entregado",
                            "",
                            "<strong>📦 ENTREGAS (Preinstalaciones):</strong>",
                            "• Total entregado al técnico: <strong style='color: #3498db;'>{$total_entregado} unidades</strong>",
                            "",
                            "<strong>📊 REPORTES DEL TÉCNICO:</strong>",
                            "• Instalaciones OK (ya reportadas): <strong>{$total_instalado} unidades</strong>",
                            "• Sobrantes (ya devueltos): <strong>{$total_devuelto} unidades</strong>",
                            "• Sobrantes (intentando devolver ahora): <strong style='color: #e74c3c;'>{$cantidad} unidades</strong>",
                            "",
                            "<strong>🔢 CÁLCULO:</strong>",
                            "• Total reportado = {$total_instalado} (Instalaciones) + {$total_devuelto} (Sobrantes previos) + {$cantidad} (Sobrantes ahora) = <strong style='color: #e74c3c;'>{$total_reportado_con_nuevo} unidades</strong>",
                            "• Total entregado = <strong style='color: #3498db;'>{$total_entregado} unidades</strong>",
                            "",
                            "<strong style='color: #e74c3c;'>❌ EXCESO DETECTADO: +{$exceso} unidades</strong>",
                            "",
                            "💡 <strong>SOLUCIÓN:</strong> Solo puedes devolver hasta <strong style='color: #27ae60;'>{$disponible_devolver} unidades</strong> en este momento"
                        ]
                    ];
                    
                    error_log("VALIDACIÓN FALLIDA - Sobrantes EXCEDEN lo entregado | Producto: {$nombre_producto} | Técnico ID: {$tecnico_id}");
                    error_log("  Entregado: {$total_entregado} | Instalado: {$total_instalado} | Devuelto: {$total_devuelto} | Intentando devolver: {$cantidad} | Total: {$total_reportado_con_nuevo}");
                    header("Location: inventario.php?sede_id=" . $vista_actual); 
                    exit();
                }
                
                // VALIDACIÓN 2: Verificar que NO falten equipos (que el total reportado sea igual a lo entregado)
                // Esta validación solo se aplica si ya hay instalaciones reportadas
                if ($total_instalado > 0) {
                    $equipos_restantes = $total_entregado - $total_reportado_con_nuevo;
                    
                    if ($equipos_restantes > 0) {
                        $_SESSION['error_validacion'] = [
                            'titulo' => '⚠️ Advertencia: Faltan Equipos por Reportar',
                            'mensaje' => "El técnico no ha reportado todos los equipos que se le entregaron. Faltan equipos por contabilizar.",
                            'detalles' => [
                                "<strong style='color: #f39c12;'>⚠️ INCONSISTENCIA DETECTADA:</strong> El total reportado es MENOR a lo entregado",
                                "",
                                "<strong>📦 ENTREGAS (Preinstalaciones):</strong>",
                                "• Total entregado al técnico: <strong style='color: #3498db;'>{$total_entregado} unidades</strong>",
                                "",
                                "<strong>📊 REPORTES DEL TÉCNICO:</strong>",
                                "• Instalaciones OK (ya reportadas): <strong>{$total_instalado} unidades</strong>",
                                "• Sobrantes (ya devueltos): <strong>{$total_devuelto} unidades</strong>",
                                "• Sobrantes (intentando devolver ahora): <strong>{$cantidad} unidades</strong>",
                                "",
                                "<strong>🔢 CÁLCULO:</strong>",
                                "• Total reportado = {$total_instalado} (Instalaciones) + {$total_devuelto} (Sobrantes previos) + {$cantidad} (Sobrantes ahora) = <strong>{$total_reportado_con_nuevo} unidades</strong>",
                                "• Total entregado = <strong style='color: #3498db;'>{$total_entregado} unidades</strong>",
                                "",
                                "<strong style='color: #e74c3c;'>❌ FALTAN: {$equipos_restantes} unidades por reportar</strong>",
                                "",
                                "💡 <strong>POSIBLES CAUSAS:</strong>",
                                "• El técnico aún tiene equipos en su poder",
                                "• Falta registrar más instalaciones o sobrantes",
                                "• Puede haber equipos perdidos o dañados",
                                "",
                                "🔍 <strong>ACCIÓN REQUERIDA:</strong> Verificar con el técnico el estado de los {$equipos_restantes} equipos faltantes antes de continuar"
                            ]
                        ];
                        
                        error_log("VALIDACIÓN FALLIDA - FALTAN equipos por reportar | Producto: {$nombre_producto} | Técnico ID: {$tecnico_id}");
                        error_log("  Entregado: {$total_entregado} | Instalado: {$total_instalado} | Devuelto: {$total_devuelto} | Intentando devolver: {$cantidad} | Total reportado: {$total_reportado_con_nuevo} | Faltan: {$equipos_restantes}");
                        header("Location: inventario.php?sede_id=" . $vista_actual); 
                        exit();
                    }
                }
                
                error_log("VALIDACIÓN EXITOSA - Sobrantes: Producto: {$nombre_producto} | Técnico ID: {$tecnico_id} | Cantidad: {$cantidad}");
                error_log("  Entregado: {$total_entregado} | Instalado: {$total_instalado} | Devuelto previo: {$total_devuelto} | Total después: {$total_reportado_con_nuevo}");
            }
            
            // VALIDACIÓN PARA INSTALACIONES OK - VERIFICAR QUE NO EXCEDA LAS PREINSTALACIONES
            if ($tipo == 'Instalaciones OK') {
                if ($tecnico_id === NULL) {
                    $_SESSION['error_validacion'] = [
                        'titulo' => '⚠️ Técnico Requerido',
                        'mensaje' => "Para registrar Instalaciones OK es obligatorio seleccionar el técnico que realizó la instalación.",
                        'detalles' => [
                            "El campo <strong>Técnico Responsable</strong> es obligatorio para el tipo <strong>Instalaciones OK</strong>",
                            "Esto permite validar que las instalaciones coincidan con las entregas previas"
                        ]
                    ];
                    
                    error_log("VALIDACIÓN FALLIDA - Instalaciones OK sin técnico asignado");
                    header("Location: inventario.php?sede_id=" . $vista_actual); 
                    exit();
                }
                
                // Obtener totales de preinstalaciones e instalaciones ok del técnico para este producto (solo ayer y hoy)
                $stmt_preinstalaciones = $pdo->prepare("
                    SELECT COALESCE(SUM(cantidad), 0) as total_entregado 
                    FROM `$tabla_movimientos` 
                    WHERE producto_id = ? AND tecnico_id = ? AND tipo = 'Preinstalaciones'
                    AND DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE(fecha) <= CURDATE()
                ");
                $stmt_preinstalaciones->execute([$producto_id, $tecnico_id]);
                $total_entregado = $stmt_preinstalaciones->fetchColumn();
                
                $stmt_instalaciones = $pdo->prepare("
                    SELECT COALESCE(SUM(cantidad), 0) as total_instalado 
                    FROM `$tabla_movimientos` 
                    WHERE producto_id = ? AND tecnico_id = ? AND tipo = 'Instalaciones OK'
                    AND DATE(fecha) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE(fecha) <= CURDATE()
                ");
                $stmt_instalaciones->execute([$producto_id, $tecnico_id]);
                $total_instalado = $stmt_instalaciones->fetchColumn();
                
                // Calcular el total que se reportaría con este nuevo movimiento
                $total_reportado_con_nuevo = $total_instalado + $cantidad;
                
                // Verificar que no se instalen más de lo entregado
                if ($total_reportado_con_nuevo > $total_entregado) {
                    $exceso = $total_reportado_con_nuevo - $total_entregado;
                    $disponible_instalar = $total_entregado - $total_instalado;
                    
                    $_SESSION['error_validacion'] = [
                        'titulo' => '❌ Error: Exceso en Instalaciones OK',
                        'mensaje' => "Estás intentando instalar MÁS equipos de los que se entregaron al técnico.",
                        'detalles' => [
                            "<strong style='color: #e74c3c;'>⚠️ PROBLEMA DETECTADO:</strong> El total instalado supera lo entregado",
                            "",
                            "<strong>📦 ENTREGAS (Preinstalaciones):</strong>",
                            "• Total entregado al técnico: <strong style='color: #3498db;'>{$total_entregado} unidades</strong>",
                            "",
                            "<strong>📊 REPORTES DEL TÉCNICO:</strong>",
                            "• Instalaciones OK (ya reportadas): <strong>{$total_instalado} unidades</strong>",
                            "• Instalaciones OK (intentando reportar ahora): <strong style='color: #e74c3c;'>{$cantidad} unidades</strong>",
                            "",
                            "<strong>🔢 CÁLCULO:</strong>",
                            "• Total reportado = {$total_instalado} (previas) + {$cantidad} (nuevas) = <strong style='color: #e74c3c;'>{$total_reportado_con_nuevo} unidades</strong>",
                            "• Total entregado = <strong style='color: #3498db;'>{$total_entregado} unidades</strong>",
                            "",
                            "<strong style='color: #e74c3c;'>❌ EXCESO DETECTADO: +{$exceso} unidades</strong>",
                            "",
                            "💡 <strong>SOLUCIÓN:</strong> Solo puedes instalar hasta <strong style='color: #27ae60;'>{$disponible_instalar} unidades</strong> en este momento"
                        ]
                    ];
                    
                    error_log("VALIDACIÓN FALLIDA - Instalaciones OK EXCEDEN lo entregado | Producto: {$nombre_producto} | Técnico ID: {$tecnico_id}");
                    error_log("  Entregado: {$total_entregado} | Instalado previo: {$total_instalado} | Intentando instalar: {$cantidad} | Total: {$total_reportado_con_nuevo}");
                    header("Location: inventario.php?sede_id=" . $vista_actual); 
                    exit();
                }
                
                // Verificar que se estén instalando todos los equipos (solo si hay equipos pendientes)
                if ($total_entregado > $total_reportado_con_nuevo) {
                    $faltan_instalar = $total_entregado - $total_reportado_con_nuevo;
                    
                    $_SESSION['advertencia_instalacion'] = [
                        'titulo' => '⚠️ Advertencia: Faltan Equipos por Instalar',
                        'mensaje' => "El técnico no ha reportado todas las instalaciones de los equipos que se le entregaron.",
                        'detalles' => [
                            "<strong style='color: #f39c12;'>⚠️ INCONSISTENCIA DETECTADA:</strong> El total instalado es MENOR a lo entregado",
                            "",
                            "<strong>📦 ENTREGAS (Preinstalaciones):</strong>",
                            "• Total entregado al técnico: <strong style='color: #3498db;'>{$total_entregado} unidades</strong>",
                            "",
                            "<strong>📊 REPORTES DEL TÉCNICO:</strong>",
                            "• Instalaciones OK (ya reportadas): <strong>{$total_instalado} unidades</strong>",
                            "• Instalaciones OK (intentando reportar ahora): <strong>{$cantidad} unidades</strong>",
                            "",
                            "<strong>🔢 CÁLCULO:</strong>",
                            "• Total reportado = {$total_instalado} (previas) + {$cantidad} (nuevas) = <strong>{$total_reportado_con_nuevo} unidades</strong>",
                            "• Total entregado = <strong style='color: #3498db;'>{$total_entregado} unidades</strong>",
                            "",
                            "<strong style='color: #e74c3c;'>❌ FALTAN: {$faltan_instalar} unidades por instalar</strong>",
                            "",
                            "💡 <strong>POSIBLES CAUSAS:</strong>",
                            "• El técnico aún tiene equipos sin instalar",
                            "• Falta registrar más instalaciones",
                            "• Puede haber equipos perdidos o dañados",
                            "",
                            "🔍 <strong>ACCIÓN REQUERIDA:</strong> Verificar con el técnico el estado de los {$faltan_instalar} equipos faltantes antes de continuar"
                        ],
                        'datos' => [
                            'producto_id' => $producto_id,
                            'tipo' => $tipo,
                            'tecnico_id' => $tecnico_id,
                            'cantidad' => $cantidad
                        ]
                    ];
                    
                    error_log("VALIDACIÓN CON ADVERTENCIA - FALTAN equipos por instalar | Producto: {$nombre_producto} | Técnico ID: {$tecnico_id}");
                    error_log("  Entregado: {$total_entregado} | Instalado previo: {$total_instalado} | Intentando instalar: {$cantidad} | Total reportado: {$total_reportado_con_nuevo} | Faltan: {$faltan_instalar}");
                } else {
                    error_log("VALIDACIÓN EXITOSA - Instalaciones OK: Producto: {$nombre_producto} | Técnico ID: {$tecnico_id} | Cantidad: {$cantidad}");
                    error_log("  Entregado: {$total_entregado} | Instalado previo: {$total_instalado} | Total después: {$total_reportado_con_nuevo}");
                }
            }
            
            // Si todas las validaciones pasan, mostrar confirmación
            $tecnico_nombre = 'Sin asignar';
            if ($tecnico_id) {
                $stmt_tecnico = $pdo->prepare("SELECT nombre FROM tecnicos WHERE id = ?");
                $stmt_tecnico->execute([$tecnico_id]);
                $tecnico_result = $stmt_tecnico->fetch(PDO::FETCH_ASSOC);
                if ($tecnico_result) {
                    $tecnico_nombre = $tecnico_result['nombre'];
                }
            }
            
            $nuevo_stock = $stock_disponible;
            if ($tipo == 'Preinstalaciones') {
                $nuevo_stock = $stock_disponible - $cantidad;
            } elseif (in_array($tipo, ['Desinstalaciones', 'Sobrantes'])) {
                $nuevo_stock = $stock_disponible + $cantidad;
            }
            
            $_SESSION['confirmacion_movimiento'] = [
                'titulo' => '⚠️ Confirmar Movimiento',
                'mensaje' => '¿Estás seguro de registrar este movimiento?',
                'detalles' => [
                    "Producto: <strong>{$nombre_producto}</strong>",
                    "Tipo de movimiento: <strong>{$tipo}</strong>",
                    "Cantidad: <strong>{$cantidad} unidades</strong>",
                    "Técnico: <strong>{$tecnico_nombre}</strong>",
                    "Stock actual: <strong>{$stock_disponible} unidades</strong>",
                    "Stock después del movimiento: <strong style='color: " . ($nuevo_stock < $stock_disponible ? '#e74c3c' : ($nuevo_stock > $stock_disponible ? '#27ae60' : '#3498db')) . ";'>{$nuevo_stock} unidades</strong>"
                ],
                'datos' => [
                    'producto_id' => $producto_id,
                    'tipo' => $tipo,
                    'tecnico_id' => $tecnico_id,
                    'cantidad' => $cantidad
                ]
            ];
            
            if ($tipo == 'Sobrantes') {
                $_SESSION['confirmacion_movimiento']['detalles'][] = "⚠️ <strong>NOTA:</strong> Se validó que esta devolución no supera las entregas previas al técnico";
            } elseif ($tipo == 'Instalaciones OK') {
                $_SESSION['confirmacion_movimiento']['detalles'][] = "⚠️ <strong>NOTA:</strong> Se validó que las instalaciones coinciden con las preinstalaciones realizadas";
            }
        }
    }
}

// --- OBTENER DATOS PARA LA VISTA ---
if ($vista_actual == 'vista_inventario_dashboard') {
    $stats_por_sede = []; 
    foreach($sedes_config as $id => $sede) { 
        $stats_por_sede[$id] = obtenerEstadisticasSede($pdo, $sede['tabla_productos'], $sede['tabla_movimientos']); 
    }
    $totales = [ 
        'total_productos' => array_sum(array_column($stats_por_sede, 'total_productos')), 
        'stock_bajo'      => array_sum(array_column($stats_por_sede, 'stock_bajo')), 
        'sin_stock'       => array_sum(array_column($stats_por_sede, 'sin_stock')), 
        'movimientos_hoy' => array_sum(array_column($stats_por_sede, 'movimientos_hoy')) 
    ];
    $ultimos_movimientos = []; 
    foreach($sedes_config as $id => $sede) { 
        $movs_query = $pdo->query("SELECT m.*, p.nombre as producto_nombre, t.nombre as tecnico_nombre, m.usuario_registro, '{$sede['nombre']}' as sede_nombre FROM `{$sede['tabla_movimientos']}` m JOIN `{$sede['tabla_productos']}` p ON m.producto_id = p.id LEFT JOIN tecnicos t ON m.tecnico_id = t.id ORDER BY m.fecha DESC LIMIT 5"); 
        if($movs_query) { 
            $ultimos_movimientos = array_merge($ultimos_movimientos, $movs_query->fetchAll(PDO::FETCH_ASSOC)); 
        } 
    }
    usort($ultimos_movimientos, fn($a, $b) => strtotime($b['fecha']) - strtotime($a['fecha'])); 
    $ultimos_movimientos = array_slice($ultimos_movimientos, 0, 10);
} else {
    if (!isset($sedes_config[$vista_actual])) { 
        header("Location: inventario.php"); 
        exit(); 
    }
    $config = $sedes_config[$vista_actual]; 
    $tabla_productos = $config['tabla_productos']; 
    $tabla_movimientos = $config['tabla_movimientos'];
    
    try {
        $check_table = $pdo->query("SHOW COLUMNS FROM tipo_tecnologia")->fetchAll(PDO::FETCH_ASSOC);
        $id_column = 'id';
        
        foreach ($check_table as $column) {
            if (stripos($column['Field'], 'id') !== false && $column['Key'] == 'PRI') {
                $id_column = $column['Field'];
                break;
            }
        }
        
        $query = "SELECT p.*, tt.nombre as tipo_tecnologia_nombre 
                  FROM `$tabla_productos` p 
                  LEFT JOIN tipo_tecnologia tt ON p.tipo_tecnologia_id = tt.$id_column 
                  ORDER BY p.id DESC";
        
        $productos = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error al obtener productos: " . $e->getMessage());
        
        $productos = $pdo->query("SELECT * FROM `$tabla_productos` ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($productos as &$producto) {
            if (isset($producto['tipo_tecnologia_id']) && !empty($producto['tipo_tecnologia_id'])) {
                try {
                    $stmt = $pdo->prepare("SELECT nombre FROM tipo_tecnologia WHERE id = ?");
                    $stmt->execute([$producto['tipo_tecnologia_id']]);
                    $tipo = $stmt->fetch(PDO::FETCH_ASSOC);
                    $producto['tipo_tecnologia_nombre'] = $tipo ? $tipo['nombre'] : 'Sin asignar';
                } catch (Exception $e2) {
                    $producto['tipo_tecnologia_nombre'] = 'Sin asignar';
                }
            } else {
                $producto['tipo_tecnologia_nombre'] = 'Sin asignar';
            }
        }
    }
    
    $movimientos = $pdo->query("SELECT m.*, p.nombre, t.nombre as tecnico_nombre, m.usuario_registro FROM `$tabla_movimientos` m JOIN `$tabla_productos` p ON m.producto_id = p.id LEFT JOIN tecnicos t ON m.tecnico_id = t.id ORDER BY m.fecha DESC")->fetchAll(PDO::FETCH_ASSOC);
    $stats_sede = obtenerEstadisticasSede($pdo, $tabla_productos, $tabla_movimientos);
    $total_productos = $stats_sede['total_productos']; 
    $movimientos_hoy = $stats_sede['movimientos_hoy']; 
    $stock_bajo = $stats_sede['stock_bajo']; 
    $sin_stock = $stats_sede['sin_stock'];
}

 $color_actual_hex = '#667eea'; 
 $color_actual_rgb = '102, 126, 234';
if ($vista_actual != 'vista_inventario_dashboard' && isset($sedes_config[$vista_actual])) { 
    $color_actual_hex = $sedes_config[$vista_actual]['color']; 
    $color_actual_rgb = hex2rgb($color_actual_hex); 
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $vista_actual == 'vista_inventario_dashboard' ? 'Dashboard de Inventario' : 'Inventario ' . ($sedes_config[$vista_actual]['nombre'] ?? '') ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: <?= $vista_actual == 'vista_inventario_dashboard' ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : (isset($sedes_config[$vista_actual]) ? $sedes_config[$vista_actual]['gradient'] : '#fff') ?>; min-height: 100vh; color: #333; }
        .header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); padding: 1.5rem 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; flex-wrap: wrap; }
        .header h1 { color: #2c3e50; font-size: 2rem; font-weight: 700; display: flex; align-items: center; gap: 15px; }
        .header h1 i { color: <?= $color_actual_hex ?>; font-size: 2.2rem; }
        .sede-badge { background: <?= $vista_actual != 'vista_inventario_dashboard' && isset($sedes_config[$vista_actual]) ? $sedes_config[$vista_actual]['gradient'] : 'linear-gradient(45deg, #667eea, #764ba2)' ?>; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; margin-left: 15px; }
        .nav-buttons { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn-nav { background: #f8f9fa; color: #333; padding: 10px 20px; border: none; border-radius: 25px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; border: 1px solid #ddd; }
        .btn-nav:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .btn-nav.active { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; border: 1px solid #2c3e50; }
        .user-info { display: flex; align-items: center; gap: 15px; color: #555; font-weight: 500; }
        .user-info i { color: <?= $color_actual_hex ?>; }
        .logout-btn { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; border: none;}
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .stats-overview, .sedes-section, .actions-section { margin-bottom: 2rem; }
        .stats-title { color: white; font-size: 1.5rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; text-shadow: 1px 1px 3px rgba(0,0,0,0.2); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.15); transition: all 0.3s ease; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .stat-card.success::before { background: linear-gradient(45deg, #27ae60, #2ecc71); }
        .stat-card.warning::before { background: linear-gradient(45deg, #f39c12, #e67e22); }
        .stat-card.danger::before { background: linear-gradient(45deg, #e74c3c, #c0392b); }
        .stat-card.info::before { background: linear-gradient(45deg, #9b59b6, #8e44ad); }
        .stat-card i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.8; }
        .stat-card.success i { color: #27ae60; } 
        .stat-card.warning i { color: #f39c12; } 
        .stat-card.danger i { color: #e74c3c; } 
        .stat-card.info i { color: #9b59b6; }
        .stat-card h3 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; color: #2c3e50; }
        .stat-card p { color: #666; font-size: 1rem; font-weight: 500; }
        .content-grid { display: grid; grid-template-columns: 1fr; gap: 2rem; margin-bottom: 2rem; }
        .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; padding: 1.5rem; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
        .card h3 { color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; position: sticky; top: 0; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px); z-index: 50; padding: 1rem 0; margin: -1.5rem -1.5rem 1.5rem -1.5rem; padding-left: 1.5rem; padding-right: 1.5rem; border-radius: 15px 15px 0 0; }
        .card h3 i { color: <?= $color_actual_hex ?>; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { margin-bottom: 0.5rem; font-weight: 500; color: #555; }
        .btn { padding: 12px 24px; border: none; border-radius: 25px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; margin: 5px; }
        .btn-primary { background: <?= $vista_actual == 'vista_inventario_dashboard' ? 'linear-gradient(45deg, #667eea, #764ba2)' : (isset($sedes_config[$vista_actual]) ? $sedes_config[$vista_actual]['gradient'] : '#ccc') ?>; color: white; }
        .btn-secondary { background: linear-gradient(45deg, #6c757d, #545b62); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-top: 1.5rem; }
        .table-container { 
            max-height: 400px; 
            overflow: hidden;
            border-radius: 8px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            margin-top: 0; 
            position: relative; 
            background: white;
        }
        .table-container table { margin-top: 0; box-shadow: none; border-radius: 0; }
        .table-container thead { display: block; position: relative; z-index: 100; }
        .table-container tbody { display: block; max-height: 340px; overflow-y: auto; }
        .table-container tbody::-webkit-scrollbar { width: 8px; }
        .table-container tbody::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        .table-container tbody::-webkit-scrollbar-thumb { background: <?= $color_actual_hex ?>; border-radius: 4px; }
        .table-container tbody::-webkit-scrollbar-thumb:hover { background: rgba(<?= $color_actual_rgb ?>, 0.8); }
        .table-container thead tr, .table-container tbody tr { display: table; width: 100%; table-layout: fixed; }
        .table-container th { background: <?= $vista_actual == 'vista_inventario_dashboard' ? 'linear-gradient(45deg, #34495e, #2c3e50)' : (isset($sedes_config[$vista_actual]) ? $sedes_config[$vista_actual]['gradient'] : '#ccc') ?> !important; position: relative; box-shadow: none; }
        th { background: <?= $vista_actual == 'vista_inventario_dashboard' ? 'linear-gradient(45deg, #34495e, #2c3e50)' : (isset($sedes_config[$vista_actual]) ? $sedes_config[$vista_actual]['gradient'] : '#ccc') ?>; color: white; padding: 15px 10px; text-align: left; font-weight: 600; }
        td { padding: 12px 10px; border-bottom: 1px solid #ecf0f1; vertical-align: middle; }
        input, select, textarea { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; transition: all 0.3s ease; background: #fdfdfd; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: <?= $color_actual_hex ?>; box-shadow: 0 0 0 4px rgba(<?= $color_actual_rgb ?>, 0.2); }
        .tecnico-badge { background: linear-gradient(45deg, #6c757d, #495057); color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 500; display: inline-block; }
        .usuario-badge { background: linear-gradient(45deg, #3498db, #2980b9); color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 500; display: inline-block; }
        .movement-badge { padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600; color: white; display: inline-flex; align-items: center; gap: 6px; }
        .movement-in { background: linear-gradient(45deg, #28a745, #218838); }
        .movement-out { background: linear-gradient(45deg, #dc3545, #c82333); }
        .movement-neutral { background: linear-gradient(45deg, #17a2b8, #117a8b); }
        .actions-grid { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); display: flex; justify-content: center; align-items: center; gap: 2rem; flex-wrap: wrap; }
        .btn-action { padding: 15px 30px; font-size: 1.1rem; text-transform: uppercase; font-weight: 600; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transform: translateY(0); }
        .btn-action:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; justify-content: center; align-items: center; z-index: 10000; animation: fadeIn 0.3s ease; }
        .modal-content { background: white; border-radius: 20px; padding: 2rem; max-width: 600px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.3s ease; max-height: 90vh; overflow-y: auto; }
        .modal-header { text-align: center; margin-bottom: 1.5rem; }
        .modal-icon { width: 80px; height: 80px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; animation: pulse 2s infinite; }
        .modal-icon.error { background: linear-gradient(45deg, #e74c3c, #c0392b); }
        .modal-icon.warning { background: linear-gradient(45deg, #f39c12, #e67e22); }
        .modal-icon.success { background: linear-gradient(45deg, #27ae60, #2ecc71); }
        .modal-icon i { font-size: 2.5rem; color: white; }
        .modal-title { color: #2c3e50; margin: 0; font-size: 1.8rem; }
        .modal-message { background: #fff3cd; border-left: 4px solid #f39c12; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .modal-message.error { background: #f8d7da; border-left-color: #e74c3c; }
        .modal-message.success { background: #d4edda; border-left-color: #27ae60; }
        .modal-message.warning { background: #fff3cd; border-left-color: #f39c12; }
        .modal-message p { margin: 0; color: #856404; font-size: 1.1rem; font-weight: 500; }
        .modal-message.error p { color: #721c24; }
        .modal-message.success p { color: #155724; }
        .modal-message.warning p { color: #856404; }
        .modal-details { background: #f8f9fa; padding: 1.5rem; border-radius: 10px; margin-bottom: 1.5rem; }
        .modal-details h3 { color: #2c3e50; margin-top: 0; margin-bottom: 1rem; font-size: 1.2rem; }
        .modal-details ul { list-style: none; padding: 0; margin: 0; }
        .modal-details li { padding: 0.7rem; margin-bottom: 0.5rem; background: white; border-radius: 5px; color: #555; font-size: 0.95rem; border-left: 3px solid #667eea; }
        .modal-details li i { color: #667eea; margin-right: 8px; }
        .modal-buttons { text-align: center; display: flex; gap: 1rem; justify-content: center; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>
                <i class="fas fa-<?= $vista_actual == 'vista_inventario_dashboard' ? 'tachometer-alt' : 'boxes' ?>"></i> 
                <?= $vista_actual == 'vista_inventario_dashboard' ? 'Dashboard de Inventario' : 'Inventario ' . ($sedes_config[$vista_actual]['nombre'] ?? 'Desconocida') ?>
                <?php if ($vista_actual != 'vista_inventario_dashboard' && isset($sedes_config[$vista_actual])): ?>
                    <span class="sede-badge" style="background: <?= $sedes_config[$vista_actual]['gradient'] ?>;"><?= htmlspecialchars($sedes_config[$vista_actual]['nombre']) ?></span>
                <?php endif; ?>
            </h1>
            <div class="nav-buttons">
                <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Página Principal</a>
                <a href="inventario.php" class="btn-nav <?= $vista_actual == 'vista_inventario_dashboard' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt"></i> Volver a Menu</a>
                <?php foreach($sedes_config as $id => $sede): ?>
                    <a href="?sede_id=<?= $id ?>" class="btn-nav <?= $vista_actual == $id ? 'active' : '' ?>" style="<?= $vista_actual == $id ? '' : 'background: '.$sede['gradient'].'; color: white; border-color: transparent;' ?>"><i class="fas fa-building"></i> <?= htmlspecialchars($sede['nombre']) ?></a>
                <?php endforeach; ?>
                <div class="user-info">
                    <i class="fas fa-user-circle"></i> 
                    <span>Administrador</span>
                    <a href="logout.php" class="btn-nav logout-btn"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['error_validacion'])): ?>
    <div class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon error">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 class="modal-title"><?= $_SESSION['error_validacion']['titulo'] ?></h2>
            </div>
            
            <div class="modal-message error">
                <p><?= $_SESSION['error_validacion']['mensaje'] ?></p>
            </div>
            
            <div class="modal-details">
                <h3><i class="fas fa-info-circle"></i> Detalles del Error:</h3>
                <ul>
                    <?php foreach ($_SESSION['error_validacion']['detalles'] as $detalle): ?>
                    <li>
                        <i class="fas fa-chevron-right"></i>
                        <?= $detalle ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="modal-buttons">
                <form method="GET" action="inventario.php" style="margin: 0;">
                    <input type="hidden" name="sede_id" value="<?= $vista_actual ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Entendido
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['error_validacion']); endif; ?>

    <?php if (isset($_SESSION['advertencia_instalacion'])): ?>
    <div class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 class="modal-title"><?= $_SESSION['advertencia_instalacion']['titulo'] ?></h2>
            </div>
            
            <div class="modal-message warning">
                <p><?= $_SESSION['advertencia_instalacion']['mensaje'] ?></p>
            </div>
            
            <div class="modal-details">
                <h3><i class="fas fa-clipboard-list"></i> Detalles de la Advertencia:</h3>
                <ul>
                    <?php foreach ($_SESSION['advertencia_instalacion']['detalles'] as $detalle): ?>
                    <li>
                        <i class="fas fa-chevron-right"></i>
                        <?= $detalle ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="modal-buttons">
                <form method="POST" action="?sede_id=<?= $vista_actual ?>" style="margin: 0; display: inline;">
                    <input type="hidden" name="confirmar_movimiento" value="1">
                    <input type="hidden" name="producto_id" value="<?= $_SESSION['advertencia_instalacion']['datos']['producto_id'] ?>">
                    <input type="hidden" name="tipo" value="<?= $_SESSION['advertencia_instalacion']['datos']['tipo'] ?>">
                    <input type="hidden" name="tecnico_id" value="<?= $_SESSION['advertencia_instalacion']['datos']['tecnico_id'] ?>">
                    <input type="hidden" name="cantidad" value="<?= $_SESSION['advertencia_instalacion']['datos']['cantidad'] ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Continuar y Registrar
                    </button>
                </form>
                <form method="GET" action="inventario.php" style="margin: 0; display: inline;">
                    <input type="hidden" name="sede_id" value="<?= $vista_actual ?>">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['advertencia_instalacion']); endif; ?>

    <?php if (isset($_SESSION['confirmacion_movimiento'])): ?>
    <div class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon warning">
                    <i class="fas fa-question-circle"></i>
                </div>
                <h2 class="modal-title"><?= $_SESSION['confirmacion_movimiento']['titulo'] ?></h2>
            </div>
            
            <div class="modal-message">
                <p><?= $_SESSION['confirmacion_movimiento']['mensaje'] ?></p>
            </div>
            
            <div class="modal-details">
                <h3><i class="fas fa-clipboard-list"></i> Detalles del Movimiento:</h3>
                <ul>
                    <?php foreach ($_SESSION['confirmacion_movimiento']['detalles'] as $detalle): ?>
                    <li>
                        <i class="fas fa-chevron-right"></i>
                        <?= $detalle ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="modal-buttons">
                <form method="POST" action="?sede_id=<?= $vista_actual ?>" style="margin: 0; display: inline;">
                    <input type="hidden" name="confirmar_movimiento" value="1">
                    <input type="hidden" name="producto_id" value="<?= $_SESSION['confirmacion_movimiento']['datos']['producto_id'] ?>">
                    <input type="hidden" name="tipo" value="<?= $_SESSION['confirmacion_movimiento']['datos']['tipo'] ?>">
                    <input type="hidden" name="tecnico_id" value="<?= $_SESSION['confirmacion_movimiento']['datos']['tecnico_id'] ?>">
                    <input type="hidden" name="cantidad" value="<?= $_SESSION['confirmacion_movimiento']['datos']['cantidad'] ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Sí, Registrar
                    </button>
                </form>
                <form method="GET" action="inventario.php" style="margin: 0; display: inline;">
                    <input type="hidden" name="sede_id" value="<?= $vista_actual ?>">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['confirmacion_movimiento']); endif; ?>

    <?php if (isset($_SESSION['mensaje_exito'])): ?>
    <div class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="modal-title"><?= $_SESSION['mensaje_exito']['titulo'] ?></h2>
            </div>
            
            <div class="modal-message success">
                <p><?= $_SESSION['mensaje_exito']['mensaje'] ?></p>
            </div>
            
            <div class="modal-buttons">
                <form method="GET" action="inventario.php" style="margin: 0;">
                    <input type="hidden" name="sede_id" value="<?= $vista_actual ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Continuar
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['mensaje_exito']); endif; ?>

    <div class="container">
        <?php if ($vista_actual == 'vista_inventario_dashboard'): ?>
            <div class="stats-overview">
                <h2 class="stats-title"><i class="fas fa-chart-pie"></i> Resumen General</h2>
                <div class="stats-grid">
                    <div class="stat-card success"><i class="fas fa-exchange-alt"></i><h3><?= number_format($totales['movimientos_hoy']) ?></h3><p>Movimientos Hoy</p></div>
                    <div class="stat-card warning"><i class="fas fa-exclamation-triangle"></i><h3><?= number_format($totales['stock_bajo']) ?></h3><p>Productos con Stock Bajo</p></div>
                    <div class="stat-card danger"><i class="fas fa-times-circle"></i><h3><?= number_format($totales['sin_stock']) ?></h3><p>Productos Sin Stock</p></div>
                </div>
            </div>

            <div class="actions-section">
                <h2 class="stats-title"><i class="fas fa-cogs"></i> Acciones</h2>
                <div class="actions-grid">
                    <a href="reportes_2.php" class="btn btn-primary btn-action"><i class="fas fa-chart-line"></i> Ver Reportes Detallados</a>
                    <a href="tecnicos.php" class="btn btn-primary btn-action"><i class="fas fa-users-cog"></i> Gestión de Técnicos</a>
                    <?php foreach($sedes_config as $id => $sede): ?>
                    <a href="?sede_id=<?= $id ?>" class="btn btn-primary btn-action" style="background: <?= $sede['gradient'] ?>"><i class="fas fa-eye"></i> Ver Inventario <?= htmlspecialchars($sede['nombre']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="stats-grid">
                <div class="stat-card info"><i class="fas fa-cube"></i><h3><?= $total_productos ?></h3><p>Total Productos</p></div>
                <div class="stat-card success"><i class="fas fa-exchange-alt"></i><h3><?= $movimientos_hoy ?></h3><p>Movimientos Hoy</p></div>
                <div class="stat-card warning"><i class="fas fa-exclamation-triangle"></i><h3><?= $stock_bajo ?></h3><p>Stock Bajo</p></div>
                <div class="stat-card danger"><i class="fas fa-times-circle"></i><h3><?= $sin_stock ?></h3><p>Sin Stock</p></div>
            </div>
            
            <div class="content-grid">
                <div class="card">
                    <h3><i class="fas fa-people-carry"></i> Registrar Movimiento</h3>
                    <form method="POST" action="?sede_id=<?= $vista_actual ?>">
                        <div class="form-grid">
                            <div class="form-group tecnico-select full-width">
                                <label><i class="fas fa-user"></i> Técnico Responsable</label>
                                <select name="tecnico_id" id="tecnico_id">
                                    <option value="">Seleccionar técnico (opcional)...</option>
                                    <?php if (!empty($tecnicos)): ?>
                                        <?php foreach ($tecnicos as $tecnico): ?>
                                            <option value="<?= $tecnico['id'] ?>"><?= htmlspecialchars($tecnico['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay técnicos disponibles</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label>Producto</label>
                            <select name="producto_id" id="producto_id" required>
                                <option value="">Seleccione un producto...</option>
                                <?php foreach ($productos as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (Part Number: <?= htmlspecialchars($p['part_number']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Tipo de Movimiento</label>
                                <select name="tipo" id="tipoMovimiento" required>
                                    <option value="">Seleccionar tipo...</option>
                                    <optgroup label="Entradas (+)">
                                        <option value="Desinstalaciones">Desinstalaciones</option>
                                        <option value="Sobrantes">Sobrantes</option>
                                    </optgroup>
                                    <optgroup label="Salidas (-)">
                                        <option value="Preinstalaciones">Preinstalaciones</option>
                                        <option value="Instalaciones OK">Instalaciones OK</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Cantidad</label>
                                <input type="number" name="cantidad" id="cantidad" min="1" step="1" required>
                            </div>
                        </div>
                        <input type="hidden" name="accion" value="validar_movimiento">
                        <button type="submit" class="btn btn-primary" style="margin-top:1rem;"><i class="fas fa-check"></i> Registrar Movimiento</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <h3><i class="fas fa-list"></i> Lista de Productos - <?= htmlspecialchars($sedes_config[$vista_actual]['nombre']) ?></h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Tipo de Tecnología</th>
                                <th>Part Number</th>
                                <th>Stock</th>
                                <th>Mínimo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $p): ?>
                            <tr>
                                <td><?= $p['id'] ?></td>
                                <td><?= htmlspecialchars($p['nombre']) ?></td>
                                <td><?= htmlspecialchars($p['tipo_tecnologia_nombre'] ?? 'Sin asignar') ?></td>
                                <td><?= htmlspecialchars($p['part_number']) ?></td>
                                <td><strong><?= $p['stock_actual'] ?></strong></td>
                                <td><?= $p['stock_minimo'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <h3><i class="fas fa-history"></i> Historial de Movimientos Recientes</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Técnico</th>
                                <th>Fecha</th>
                                <th>Registrado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $tipos_entrada_historial = ['Desinstalaciones', 'Sobrantes'];
                            $tipos_salida_historial = ['Preinstalaciones'];
                            $tipos_neutral_historial = ['Instalaciones OK'];

                            foreach ($movimientos as $m): 
                                $tipo_movimiento = isset($m['tipo']) && !empty(trim($m['tipo'])) ? trim($m['tipo']) : 'Sin tipo';
                                
                                $badge_class = 'movement-neutral';
                                $icon_class = 'fa-info-circle';

                                if (in_array($tipo_movimiento, $tipos_entrada_historial)) {
                                    $badge_class = 'movement-in';
                                    $icon_class = 'fa-arrow-up';
                                } elseif (in_array($tipo_movimiento, $tipos_salida_historial)) {
                                    $badge_class = 'movement-out';
                                    $icon_class = 'fa-arrow-down';
                                } elseif (in_array($tipo_movimiento, $tipos_neutral_historial)) {
                                    $badge_class = 'movement-neutral';
                                    $icon_class = 'fa-check-circle';
                                }
                            ?>
                            <tr>
                                <td><?= $m['id'] ?></td>
                                <td><?= htmlspecialchars($m['nombre']) ?></td>
                                <td>
                                    <span class="movement-badge <?= $badge_class ?>">
                                        <i class="fas <?= $icon_class ?>"></i>
                                        <?= htmlspecialchars($tipo_movimiento) ?>
                                    </span>
                                </td>
                                <td><strong><?= $m['cantidad'] ?></strong></td>
                                <td>
                                    <?php if (!empty($m['tecnico_nombre'])): ?>
                                        <span class="tecnico-badge"><i class="fas fa-user"></i> <?= htmlspecialchars($m['tecnico_nombre']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #999; font-style: italic;">Sin asignar</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                                <td>
                                    <?php if (!empty($m['usuario_registro'])): ?>
                                        <span class="usuario-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($m['usuario_registro']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #999; font-style: italic;">No registrado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tipoSelect = document.getElementById('tipoMovimiento');
            const tecnicoLabel = document.querySelector('.form-group.tecnico-select label');
            const tecnicoSelect = document.getElementById('tecnico_id');
            const tecnicoGroup = document.querySelector('.form-group.tecnico-select');

            const tiposEntrada = ['Desinstalaciones', 'Sobrantes'];
            const tiposSalida = ['Preinstalaciones'];
            const tiposNeutral = ['Instalaciones OK'];

            function actualizarFormularioMovimiento() {
                if (!tipoSelect) return;
                const tipoMovimiento = tipoSelect.value;
                
                if (tecnicoGroup) {
                    tecnicoGroup.style.border = '';
                    tecnicoGroup.style.padding = '';
                    tecnicoGroup.style.background = '';
                    tecnicoGroup.style.borderRadius = '';
                }
                
                if (tecnicoSelect) {
                    tecnicoSelect.required = false;
                }
                
                if (tecnicoLabel) {
                    if (tiposEntrada.includes(tipoMovimiento)) {
                        tecnicoLabel.innerHTML = '<i class="fas fa-user"></i> Técnico que entrega material';
                        
                        if (tipoMovimiento === 'Sobrantes') {
                            tecnicoLabel.innerHTML = '<i class="fas fa-user"></i> Técnico que entrega material <span style="color: #e74c3c; font-weight: bold;">*OBLIGATORIO*</span>';
                            
                            if (tecnicoGroup) {
                                tecnicoGroup.style.border = '2px solid #e74c3c';
                                tecnicoGroup.style.padding = '15px';
                                tecnicoGroup.style.background = 'rgba(231, 76, 60, 0.05)';
                                tecnicoGroup.style.borderRadius = '10px';
                            }
                            
                            if (tecnicoSelect) {
                                tecnicoSelect.required = true;
                            }
                        }
                    } else if (tiposSalida.includes(tipoMovimiento)) {
                        tecnicoLabel.innerHTML = '<i class="fas fa-user"></i> Técnico que recibe material';
                    } else if (tiposNeutral.includes(tipoMovimiento)) {
                        tecnicoLabel.innerHTML = '<i class="fas fa-user"></i> Técnico que realiza la instalación';
                        
                        if (tipoMovimiento === 'Instalaciones OK') {
                            tecnicoLabel.innerHTML = '<i class="fas fa-user"></i> Técnico que realiza la instalación <span style="color: #e74c3c; font-weight: bold;">*OBLIGATORIO*</span>';
                            
                            if (tecnicoGroup) {
                                tecnicoGroup.style.border = '2px solid #e74c3c';
                                tecnicoGroup.style.padding = '15px';
                                tecnicoGroup.style.background = 'rgba(231, 76, 60, 0.05)';
                                tecnicoGroup.style.borderRadius = '10px';
                            }
                            
                            if (tecnicoSelect) {
                                tecnicoSelect.required = true;
                            }
                        }
                    } else {
                        tecnicoLabel.innerHTML = '<i class="fas fa-user"></i> Técnico Responsable';
                    }
                }
            }
            
            if (tipoSelect) {
                tipoSelect.addEventListener('change', actualizarFormularioMovimiento);
                actualizarFormularioMovimiento();
            }
        });
    </script>
</body>
</html>