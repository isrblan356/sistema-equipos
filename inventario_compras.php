<?php
/*
================================================================================
| MÓDULO PROFESIONAL DE GESTIÓN DE COMPRAS Y PRODUCTOS (TODO EN UNO)           |
| Versión: 9.5 - Final: Confirma suma a stock y ajusta variable de sesión.     |
| Descripción: Un sistema autocontenido para la gestión integral de compras,   |
|              proveedores, productos e inventario.                            |
================================================================================
*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir el archivo de configuración de base de datos y funciones
require_once 'config.php';

//==============================================================================
// PARTE 1: DEFINICIÓN DE TODAS LAS CLASES
//==============================================================================

class ProductManager
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM productos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getAll(): array {
        return $this->db->query("SELECT * FROM productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar(array $data, ?int $id = null): void {
        if (empty($data['nombre'])) { throw new Exception("El nombre del producto es obligatorio."); }
        $params = [
            'nombre' => trim($data['nombre']),
            'part_number' => empty($data['part_number']) ? null : trim($data['part_number']),
            'descripcion' => empty($data['descripcion']) ? null : trim($data['descripcion'])
        ];
        if ($id) {
            $params['id'] = $id;
            $sql = "UPDATE productos SET nombre = :nombre, part_number = :part_number, descripcion = :descripcion WHERE id = :id";
        } else {
            $stmt = $this->db->prepare("SELECT id FROM productos WHERE nombre = :nombre");
            $stmt->execute(['nombre' => $params['nombre']]);
            if ($stmt->fetch()) { throw new Exception("El producto '" . e($params['nombre']) . "' ya existe."); }
            $sql = "INSERT INTO productos (nombre, part_number, descripcion) VALUES (:nombre, :part_number, :descripcion)";
        }
        $this->db->prepare($sql)->execute($params);
    }

    public function eliminar(int $id): void {
        $this->db->prepare("DELETE FROM productos WHERE id = :id")->execute(['id' => $id]);
    }
    
    public function findOrCreateByName(string $nombre, ?string $part_number = null): int {
        $nombre = trim($nombre);
        if (empty($nombre)) return 0;
        $stmt = $this->db->prepare("SELECT * FROM productos WHERE nombre = :nombre");
        $stmt->execute(['nombre' => $nombre]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($producto) {
            if (!empty($part_number) && $producto['part_number'] !== $part_number) {
                $updateStmt = $this->db->prepare("UPDATE productos SET part_number = :num_parte WHERE id = :id");
                $updateStmt->execute(['num_parte' => $part_number, 'id' => $producto['id']]);
            }
            return (int)$producto['id'];
        } else {
            $sql = "INSERT INTO productos (nombre, part_number) VALUES (:nombre, :num_parte)";
            $insertStmt = $this->db->prepare($sql);
            $insertStmt->execute(['nombre' => $nombre, 'num_parte' => $part_number]);
            return (int)$this->db->lastInsertId();
        }
    }
}

class ProveedorManager
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM proveedores WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    public function getAll(): array {
        return $this->db->query("SELECT * FROM proveedores WHERE activo = 1 ORDER BY nombre")->fetchAll();
    }
    public function guardar(array $data, ?int $id = null): void {
        if (empty($data['nombre']) || empty($data['nit'])) { throw new Exception("Nombre y NIT son obligatorios para el proveedor."); }
        $params = ['nombre' => $data['nombre'], 'nit' => $data['nit'], 'contacto_nombre' => $data['contacto_nombre'] ?? null, 'contacto_email' => $data['contacto_email'] ?? null, 'contacto_telefono' => $data['contacto_telefono'] ?? null, 'direccion' => $data['direccion'] ?? null, 'website' => $data['website'] ?? null, 'notas' => $data['notas'] ?? null];
        if ($id) {
            $params['id'] = $id;
            $sql = "UPDATE proveedores SET nombre=:nombre, nit=:nit, contacto_nombre=:contacto_nombre, contacto_email=:contacto_email, contacto_telefono=:contacto_telefono, direccion=:direccion, website=:website, notas=:notas WHERE id=:id";
        } else {
            $sql = "INSERT INTO proveedores (nombre, nit, contacto_nombre, contacto_email, contacto_telefono, direccion, website, notas) VALUES (:nombre, :nit, :contacto_nombre, :contacto_email, :contacto_telefono, :direccion, :website, :notas)";
        }
        $this->db->prepare($sql)->execute($params);
    }
    public function eliminar(int $id): void {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM compras WHERE proveedor_id = :id");
        $stmt->execute(['id' => $id]);
        if ($stmt->fetchColumn() > 0) { throw new Exception("No se puede eliminar. El proveedor tiene compras asociadas."); }
        $this->db->prepare("DELETE FROM proveedores WHERE id = :id")->execute(['id' => $id]);
    }
}

class ComprasManager
{
    private PDO $db;
    private ProductManager $productManager;
    private string $uploadDir = 'uploads/';
    
    public function __construct(PDO $db, ProductManager $productManager) { 
        $this->db = $db; 
        $this->productManager = $productManager;
        if (!file_exists($this->uploadDir)) { mkdir($this->uploadDir, 0755, true); }
    }
    
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT c.*, p.nombre AS proveedor_nombre, p.nit AS proveedor_nit, p.contacto_nombre AS proveedor_contacto, p.direccion AS proveedor_direccion FROM compras c LEFT JOIN proveedores p ON c.proveedor_id = p.id WHERE c.id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    public function getAll(string $filter = null, ?string $startDate = null, ?string $endDate = null): array {
        $sql = "SELECT c.*, p.nombre AS proveedor_nombre FROM compras c LEFT JOIN proveedores p ON c.proveedor_id = p.id";
        $conditions = [];
        $params = [];

        if ($filter === 'pendientes') {
            $conditions[] = "(c.factura_recibida = 'no' OR c.producto_recibido = 'no')";
        }

        if ($startDate && $endDate) {
            $conditions[] = "c.fecha_compra BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY c.fecha_compra DESC, c.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getByProveedorId(int $proveedor_id): array {
        $stmt = $this->db->prepare("SELECT c.*, p.nombre AS proveedor_nombre FROM compras c LEFT JOIN proveedores p ON c.proveedor_id = p.id WHERE c.proveedor_id = :proveedor_id ORDER BY c.fecha_compra DESC");
        $stmt->execute(['proveedor_id' => $proveedor_id]);
        return $stmt->fetchAll();
    }
    
    private function registrarMovimientoInventario(string $productoNombre, string $partNumber, int $cantidad, string $usuarioRegistro): void {
        try {
            $stmt = $this->db->prepare("SELECT id FROM productos WHERE nombre = ? OR part_number = ? LIMIT 1");
            $stmt->execute([$productoNombre, $partNumber]);
            $producto = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$producto) {
                $codigo = 'COMP-' . strtoupper(substr(md5($productoNombre . $partNumber), 0, 8));
                $insertStmt = $this->db->prepare("INSERT INTO productos (nombre, codigo, part_number, stock_actual, stock_minimo) VALUES (?, ?, ?, 0, 1)");
                $insertStmt->execute([$productoNombre, $codigo, $partNumber]);
                $producto_id = (int)$this->db->lastInsertId();
            } else {
                $producto_id = (int)$producto['id'];
            }
            
            $tecnico_id = 4;
            
            $stmt = $this->db->prepare("INSERT INTO movimientos (producto_id, tipo, cantidad, tecnico_id, usuario_registro, fecha) VALUES (?, 'Compras', ?, ?, ?, NOW())");
            $stmt->execute([$producto_id, $cantidad, $tecnico_id, $usuarioRegistro]);
            
            // ESTA ES LA LÍNEA QUE SUMA LA CANTIDAD AL STOCK ACTUAL DEL PRODUCTO
            $updateStmt = $this->db->prepare("UPDATE productos SET stock_actual = stock_actual + ? WHERE id = ?");
            $updateStmt->execute([$cantidad, $producto_id]);
            
            error_log("Movimiento de inventario registrado por '{$usuarioRegistro}': Producto ID {$producto_id}, Cantidad {$cantidad}. Stock actualizado.");
            
        } catch (Exception $e) {
            error_log("Error al registrar movimiento en inventario: " . $e->getMessage());
        }
    }
        
    public function guardar(array $data, array $files, ?int $id = null): void {
        $this->validarDatos($data, $id);

        $totalSubtotal = 0; $totalIva = 0; $totalRetefuente = 0;

        if (!isset($data['tipo_producto']) || !is_array($data['tipo_producto'])) {
            throw new Exception("No se recibieron datos de productos válidos.");
        }
        
        foreach ($data['tipo_producto'] as $key => $nombreProducto) {
            if (empty(trim($nombreProducto))) continue;
            $cantidad = (float)($data['cantidad'][$key] ?? 0);
            $precio_sin_iva = (float)($data['precio_sin_iva'][$key] ?? 0);
            $iva_porcentaje = (float)($data['iva_porcentaje'][$key] ?? 19);
            $retefuente_linea = (float)($data['retefuente'][$key] ?? 0);
            $subtotal_linea = $cantidad * $precio_sin_iva;
            $iva_linea = $subtotal_linea * ($iva_porcentaje / 100);
            $totalSubtotal += $subtotal_linea;
            $totalIva += $iva_linea;
            $totalRetefuente += $retefuente_linea;
            $this->productManager->findOrCreateByName($nombreProducto, $data['part_number'][$key] ?? null);
        }
        
        $costo_envio = (float)($data['costo_envio'] ?? 0);
        $totalPagar = $totalSubtotal + $totalIva + $costo_envio - $totalRetefuente;
        
        $archivo_orden = null; $archivo_factura = null;
        
        if ($id) {
            $compra_actual = $this->getById($id);
            $archivo_orden = $this->procesarArchivo($files['archivo_orden'] ?? null, 'orden_') ?: ($compra_actual['archivo_orden'] ?? null);
            $archivo_factura = $this->procesarArchivo($files['archivo_factura'] ?? null, 'factura_') ?: ($compra_actual['archivo_factura'] ?? null);
            $producto_recibido_anterior = $compra_actual['producto_recibido'] ?? 'no';
        } else {
            $archivo_orden = $this->procesarArchivo($files['archivo_orden'] ?? null, 'orden_');
            $archivo_factura = $this->procesarArchivo($files['archivo_factura'] ?? null, 'factura_');
            $producto_recibido_anterior = 'no';
        }
        
        $params = [
            'proveedor_id' => $data['proveedor_id'], 'fecha_compra' => $data['fecha_compra'], 'orden_compra' => $data['orden_compra'], 
            'numero_cotizacion' => $data['numero_cotizacion'] ?? null, 'part_number' => $data['part_number'][0] ?? null, 
            'descripcion' => $data['descripcion'] ?? null, 'tipo_producto' => $data['tipo_producto'][0] ?? null, 
            'cantidad' => array_sum($data['cantidad']), 'precio_sin_iva' => $totalSubtotal, 'costo_envio' => $costo_envio, 
            'iva' => $totalIva, 'retefuente' => $totalRetefuente, 'total_pagar' => $totalPagar, 'terminos_pago' => $data['terminos_pago'] ?? 'Contado', 
            'fecha_pago' => empty($data['fecha_pago']) ? null : $data['fecha_pago'], 'valor_pagado' => empty($data['valor_pagado']) ? null : (float)$data['valor_pagado'], 
            'valor_factura' => empty($data['valor_factura']) ? null : (float)$data['valor_factura'], 'producto_recibido' => $data['producto_recibido'] ?? 'no', 
            'fecha_recibido' => empty($data['fecha_recibido']) ? null : $data['fecha_recibido'], 'factura_recibida' => $data['factura_recibida'] ?? 'no', 
            'pago_contraentrega' => isset($data['pago_contraentrega']) ? 1 : 0, 'valor_contra_entrega' => empty($data['valor_contra_entrega']) ? null : (float)$data['valor_contra_entrega'], 
            'direccion_envio' => $data['direccion_envio'] ?? null, 'estado' => $data['estado'] ?? 'Ordenada', 
            'notas' => $data['notas'] ?? null, 'novedades' => $data['novedades'] ?? null,
            'archivo_orden' => $archivo_orden, 'archivo_factura' => $archivo_factura
        ];

        if ($id) {
            $params['id'] = $id;
            $update_fields = [];
            foreach(array_keys($params) as $k) { if($k !== 'id') $update_fields[] = "$k = :$k"; }
            $sql = "UPDATE compras SET " . implode(', ', $update_fields) . " WHERE id = :id";
        } else {
            $sql = "INSERT INTO compras (" . implode(", ", array_keys($params)) . ") VALUES (:" . implode(", :", array_keys($params)) . ")";
        }
        
        $this->db->prepare($sql)->execute($params);
        
        $producto_recibido_actual = $data['producto_recibido'] ?? 'no';
        
        // =================================================================================================
        // ¡ACCIÓN REQUERIDA! - AJUSTA ESTA LÍNEA A TU VARIABLE DE SESIÓN CORRECTA
        // =================================================================================================
        // Aquí debes reemplazar 'usuario_nombre' por la clave correcta donde guardas el NOMBRE del usuario en la sesión.
        // Ejemplos comunes: $_SESSION['nombre'], $_SESSION['usuario'], $_SESSION['user']['name'], etc.
        $usuarioLogueado = $_SESSION['usuario_nombre'] ?? 'Sistema';
        // =================================================================================================

        if ($producto_recibido_actual === 'si' && (!$id || ($id && $producto_recibido_anterior === 'no'))) {
            foreach ($data['tipo_producto'] as $key => $productoNombre) {
                $partNumber = $data['part_number'][$key] ?? '';
                $cantidad = (int)($data['cantidad'][$key] ?? 0);
                
                if (!empty(trim($productoNombre)) && $cantidad > 0) {
                    $this->registrarMovimientoInventario($productoNombre, $partNumber, $cantidad, $usuarioLogueado);
                }
            }
        }
    }
    
    public function eliminar(int $id): void {
        $compra = $this->getById($id);
        if ($compra && !empty($compra['archivo_orden']) && file_exists($this->uploadDir . $compra['archivo_orden'])) { @unlink($this->uploadDir . $compra['archivo_orden']); }
        if ($compra && !empty($compra['archivo_factura']) && file_exists($this->uploadDir . $compra['archivo_factura'])) { @unlink($this->uploadDir . $compra['archivo_factura']); }
        $this->db->prepare("DELETE FROM compras WHERE id = :id")->execute(['id' => $id]);
    }
    
    public function getDashboardMetrics(): array {
        $metrics = [];
        $metrics['total_compras'] = $this->db->query("SELECT COUNT(id) FROM compras")->fetchColumn() ?? 0;
        $metrics['monto_total_comprometido'] = $this->db->query("SELECT SUM(total_pagar) FROM compras")->fetchColumn() ?? 0;
        $metrics['total_pagado'] = $this->db->query("SELECT SUM(COALESCE(valor_pagado, 0) + COALESCE(valor_contra_entrega, 0)) FROM compras")->fetchColumn() ?? 0;
        $metrics['compras_pendientes'] = $this->db->query("SELECT COUNT(id) FROM compras WHERE producto_recibido = 'no' OR factura_recibida = 'no'")->fetchColumn() ?? 0;
        $metrics['total_pago_envio'] = $this->db->query("SELECT SUM(COALESCE(costo_envio, 0) + COALESCE(valor_contra_entrega, 0)) FROM compras")->fetchColumn() ?? 0;
        $metrics['saldo_a_favor'] = $this->db->query("SELECT SUM(total_pagar - valor_factura) FROM compras WHERE valor_factura IS NOT NULL AND valor_factura < total_pagar")->fetchColumn() ?? 0;
        return $metrics;
    }

    public function getInventorySummary(): array {
        $sql = "SELECT tipo_producto, SUM(cantidad) as total_cantidad, COUNT(id) as numero_compras
                FROM compras WHERE tipo_producto IS NOT NULL AND tipo_producto != '' 
                GROUP BY tipo_producto ORDER BY total_cantidad DESC";
        return $this->db->query($sql)->fetchAll();
    }
    
    public function getPurchaseHistoryByProduct(string $productName): array {
        $sql = "SELECT c.fecha_compra, c.cantidad, c.orden_compra, p.nombre as proveedor_nombre, c.id
                FROM compras c LEFT JOIN proveedores p ON c.proveedor_id = p.id
                WHERE c.tipo_producto = :product_name ORDER BY c.fecha_compra DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['product_name' => $productName]);
        return $stmt->fetchAll();
    }
    
    public function getTopProveedores(): array {
        $sql = "SELECT p.nombre, SUM(c.total_pagar) as total_gastado, COUNT(c.id) as num_compras
                FROM compras c JOIN proveedores p ON c.proveedor_id = p.id
                GROUP BY p.id, p.nombre ORDER BY total_gastado DESC LIMIT 5";
        return $this->db->query($sql)->fetchAll();
    }
    
    private function validarDatos(array $data, ?int $id = null): void {
        foreach(['proveedor_id' => 'Proveedor', 'fecha_compra' => 'Fecha de Compra', 'orden_compra' => 'Orden de Compra'] as $campo => $nombre) {
            if(!isset($data[$campo]) || $data[$campo] === '') { throw new Exception("El campo '{$nombre}' es obligatorio."); }
        }
        if (!isset($data['cantidad']) || !is_array($data['cantidad']) || count($data['cantidad']) === 0) {
            throw new Exception("Debe agregar al menos un producto con cantidad.");
        }
        
        $sql = "SELECT COUNT(*) FROM compras WHERE orden_compra = :orden"; $params = ['orden' => $data['orden_compra']];
        if ($id) { $sql .= " AND id != :id"; $params['id'] = $id; }
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        if ($stmt->fetchColumn() > 0) { throw new Exception("Ya existe una compra con la orden de compra: " . e($data['orden_compra'])); }
    }
    
    private function procesarArchivo(?array $file, string $prefijo): ?string {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'xml', 'txt'])) { throw new Exception("Tipo de archivo no permitido: {$ext}."); }
        if ($file['size'] > 10 * 1024 * 1024) { throw new Exception("El archivo es demasiado grande. Máximo 10MB."); }
        $nombre = $prefijo . date('Ymd_His_') . uniqid() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $this->uploadDir . $nombre)) { throw new Exception("No se pudo guardar el archivo."); }
        return $nombre;
    }
}


//==============================================================================
// PARTE 2: FUNCIONES DE RENDERIZADO DE LA VISTA
//==============================================================================

function e(?string $string): string { return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8'); }
function format_cop(float $number): string { return '$ ' . number_format($number, 0, ',', '.'); }

function render_header(string $titulo): void {
    echo <<<HTML
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{$titulo}</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><style>:root{--color-primary:#4f46e5;--color-primary-light:#6366f1;--color-secondary:#10b981;--color-danger:#ef4444;--color-warning:#f59e0b;--color-info:#3b82f6;--color-bg:#f8fafc;--color-bg-card:#fff;--color-text:#111827;--color-text-muted:#6b7280;--color-border:#e2e8f0;--shadow:0 4px 6px -1px rgb(0 0 0 / .1), 0 2px 4px -2px rgb(0 0 0 / .1);--shadow-lg:0 10px 15px -3px rgb(0 0 0 / .1), 0 4px 6px -4px rgb(0 0 0 / .1);--border-radius:.75rem}*,::before,::after{box-sizing:border-box}body{margin:0;font-family:'Inter',sans-serif;background-color:var(--color-bg);color:var(--color-text);font-size:16px;line-height:1.6}.main-wrapper{display:flex;min-height:100vh}.sidebar{width:280px;background-color:var(--color-bg-card);padding:2rem 1.5rem;box-shadow:var(--shadow-lg);position:sticky;top:0;height:100vh;overflow-y:auto}.sidebar h2{font-size:1.5rem;text-align:center;margin:0 0 2.5rem;color:var(--color-primary);font-weight:700}.sidebar h2 .fa-cogs{margin-right:.5rem}.nav-menu a{display:flex;align-items:center;gap:.75rem;padding:1rem 1.25rem;border-radius:.75rem;color:var(--color-text-muted);text-decoration:none;font-weight:600;margin-bottom:.5rem;transition:all .3s ease}.nav-menu a:hover{background-color:var(--color-bg);color:var(--color-text);transform:translateX(4px)}.nav-menu a.active{background-color:var(--color-primary);color:#fff;box-shadow:var(--shadow)}.nav-menu .nav-section-title{padding:1.5rem 1.25rem .5rem;color:#9ca3af;font-weight:600;font-size:.8rem;text-transform:uppercase}.content-wrapper{flex-grow:1;padding:2rem;max-width:calc(100% - 280px)}h1,h2{font-weight:700;line-height:1.2}h1{font-size:2.5rem;color:var(--color-primary)}.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem}h2{font-size:1.75rem;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--color-border);display:flex;align-items:center;gap:.75rem}.card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--color-border)}.card-header h2{border:none;padding:0;margin:0;font-size:1.75rem}.toggle-btn{background:none;border:2px solid var(--color-border);color:var(--color-text-muted);cursor:pointer;width:32px;height:32px;border-radius:50%;font-size:1rem;transition:all .3s ease}.toggle-btn:hover{background-color:var(--color-bg);color:var(--color-primary)}.alert{padding:1.25rem 1.5rem;margin-bottom:2rem;border-radius:var(--border-radius);display:flex;align-items:center;gap:.75rem;font-weight:500;box-shadow:var(--shadow)}.alert-success{background-color:#d1fae5;color:#065f46;border-left:4px solid var(--color-secondary)}.alert-error{background-color:#fee2e2;color:#991b1b;border-left:4px solid var(--color-danger)}.card{background-color:var(--color-bg-card);border-radius:var(--border-radius);box-shadow:var(--shadow);padding:2.5rem;margin-bottom:2rem;border:1px solid var(--color-border)}.collapsible-content{transition:all .4s ease-out;overflow:hidden;max-height:5000px}.collapsible-content.hidden{max-height:0;padding-top:0;padding-bottom:0;margin-top:0;opacity:0}.inventory-stats{display:flex;gap:2rem;justify-content:space-around;padding:1.5rem;background-color:var(--color-bg);border-radius:var(--border-radius);margin-bottom:2rem;text-align:center}.stat-item .stat-value{font-size:1.5rem;font-weight:700;color:var(--color-primary)}.stat-item .stat-label{font-size:0.9rem;color:var(--color-text-muted);text-transform:uppercase;margin-top:.25rem}fieldset{border:2px solid var(--color-border);border-radius:var(--border-radius);padding:2rem;margin-bottom:2rem}legend{font-weight:600;font-size:1.1rem;padding:0 1rem;color:var(--color-primary)}.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;margin-bottom:1.5rem}.form-group{display:flex;flex-direction:column}.form-label{font-weight:600;margin-bottom:.5rem;font-size:.95rem;color:var(--color-text)}.form-label .required{color:var(--color-danger)}.form-input,.form-select,.form-textarea{width:100%;padding:.875rem 1rem;border:2px solid var(--color-border);border-radius:.5rem;font-size:1rem;font-family:inherit;transition:all .3s ease;background-color:var(--color-bg-card)}.form-input:focus,.form-select:focus,.form-textarea:focus{outline:0;border-color:var(--color-primary);box-shadow:0 0 0 3px rgba(79,70,229,.1)}.form-input[readonly]{background-color:var(--color-bg);color:var(--color-text-muted)}.form-input[disabled]{background-color:var(--color-bg);color:var(--color-text-muted);cursor:not-allowed}.form-textarea{min-height:100px;resize:vertical}.option-group{display:flex;align-items:center;gap:2rem;margin-top:.5rem}.option-item{display:flex;align-items:center;gap:.5rem}.option-item input[type=radio],.option-item input[type=checkbox]{accent-color:var(--color-primary);transform:scale(1.1)}.summary{background:linear-gradient(135deg,var(--color-bg) 0%,#e2e8f0 100%);border-radius:var(--border-radius);padding:2rem;margin-top:2rem;border:2px solid var(--color-border)}.summary h3{margin-top:0;color:var(--color-primary);font-size:1.25rem}.summary-row{display:flex;justify-content:space-between;margin-bottom:.75rem;padding:.5rem 0}.summary-row.total{font-weight:700;font-size:1.3rem;border-top:3px solid var(--color-primary);padding-top:1rem;margin-top:1rem;color:var(--color-primary)}.summary-value{font-weight:600}.btn-container{display:flex;justify-content:flex-end;gap:1rem;margin-top:2.5rem;padding-top:2rem;border-top:2px solid var(--color-border)}.btn{padding:.875rem 2rem;border:none;border-radius:.75rem;font-weight:600;font-size:1rem;cursor:pointer;transition:all .3s ease;display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;line-height:1.5;box-shadow:var(--shadow)}.btn-primary{background-color:var(--color-primary);color:#fff}.btn-primary:hover{background-color:var(--color-primary-light);transform:translateY(-2px);box-shadow:var(--shadow-lg)}.btn-secondary{background-color:var(--color-text-muted);color:#fff}.btn-secondary:hover{background-color:#4b5563;transform:translateY(-2px)}.btn-info{background-color:var(--color-info);color:#fff}.btn-info:hover{background-color:#2563eb;transform:translateY(-2px)}.btn-danger{background-color:var(--color-danger);color:#fff}.btn-danger:hover{background-color:#dc2626;transform:translateY(-2px)}.btn-warning{background-color:var(--color-warning);color:#fff}.btn-warning:hover{background-color:#d97706;transform:translateY(-2px)}.table-responsive{overflow-x:auto;border-radius:var(--border-radius);box-shadow:var(--shadow)}.data-table{width:100%;border-collapse:collapse;margin-top:1rem;background-color:var(--color-bg-card)}.data-table th,.data-table td{padding:1rem 1.25rem;text-align:left;border-bottom:1px solid var(--color-border);vertical-align:middle}.data-table th{background-color:var(--color-bg);font-weight:700;color:var(--color-text);text-transform:uppercase;font-size:.875rem;letter-spacing:.05em}.data-table tbody tr:hover{background-color:#f1f5f9}.status-badge{padding:.375rem 1rem;border-radius:9999px;font-size:.8rem;font-weight:600;text-transform:uppercase;white-space:nowrap;letter-spacing:.05em}.status-Ordenada{background-color:#dbeafe;color:#1e40af}.status-Enviada{background-color:#fef3c7;color:#92400e}.status-Completada{background-color:#d1fae5;color:#065f46}.status-Cancelada{background-color:#fee2e2;color:#991b1b}.dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:2rem;margin-bottom:3rem}.metric-card{display:flex;align-items:center;gap:1.5rem;background:linear-gradient(135deg,var(--color-bg-card) 0%,#f8fafc 100%);padding:2rem;border-radius:var(--border-radius);box-shadow:var(--shadow);border:2px solid var(--color-border);transition:transform .3s ease}.metric-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg)}.metric-card .icon{font-size:2.5rem;width:70px;height:70px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--color-primary),var(--color-primary-light));border-radius:50%;flex-shrink:0;color:#fff;box-shadow:var(--shadow)}.metric-card .content{flex:1;min-width:0}.metric-card .content .value{font-size:2rem;font-weight:700;line-height:1;margin-bottom:.5rem;color:var(--color-text);overflow-wrap:break-word}.metric-card .content .label{font-size:1rem;color:var(--color-text-muted);font-weight:500}.actions-cell{white-space:nowrap}.actions-cell form{display:inline-block;margin:0 .25rem}.actions-cell .btn{padding:.5rem .75rem;font-size:.875rem}.file-info{margin-top:.5rem;padding:.5rem;background-color:var(--color-bg);border-radius:.375rem;font-size:.875rem;color:var(--color-text-muted)}.file-link{color:var(--color-primary);text-decoration:none;font-weight:500}.file-link:hover{text-decoration:underline}.provider-cell{display:flex;align-items:center;gap:1rem;font-weight:600}.provider-cell a{color:var(--color-text);text-decoration:none}.provider-cell a:hover{color:var(--color-primary)}.provider-avatar{width:40px;height:40px;border-radius:50%;color:#fff;font-weight:600;font-size:1rem;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;text-transform:uppercase}
#loader{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(0,0,0,.7);z-index:1000;backdrop-filter:blur(4px)}.spinner{position:absolute;top:50%;left:50%;width:60px;height:60px;border:6px solid rgba(255,255,255,.3);border-top:6px solid var(--color-primary);border-radius:50%;animation:spin 1s linear infinite}@keyframes spin{0%{transform:translate(-50%,-50%) rotate(0)}100%{transform:translate(-50%,-50%) rotate(360deg)}}@media (max-width:1024px){.main-wrapper{flex-direction:column}.sidebar{width:100%;height:auto;position:static}.content-wrapper{max-width:100%;padding:1.5rem}.form-grid,.dashboard-grid{grid-template-columns:1fr}}@media (max-width:768px){.btn-container{flex-direction:column}.btn{justify-content:center}}
@media print{body *{visibility:hidden}.sidebar,.page-header,.nav-menu,.btn-container,.print-hide{display:none!important}#invoice-container,#invoice-container *{visibility:visible}#invoice-container{position:absolute;left:0;top:0;width:100%;padding:1rem;margin:0;border:none;box-shadow:none;background-color:#fff!important;}.print-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2rem;padding-bottom:1.5rem;border-bottom:3px solid var(--color-primary)}.print-header .company-logo{font-size:2rem;font-weight:800;color:var(--color-primary)}.print-header .quote-info{text-align:right}.print-header .quote-title{font-size:1.8rem;font-weight:700;margin-bottom:.5rem}.print-parties{display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-bottom:2rem}.print-party-box .section-title{font-size:1.1rem;font-weight:600;color:var(--color-primary);margin-bottom:1rem;text-transform:uppercase;border-bottom:2px solid var(--color-border);padding-bottom:.5rem}.print-party-box p{margin:0.3rem 0;font-size:0.95rem}.print-item-title{display:block;margin-top:2rem;font-size:1.2rem;font-weight:600;color:var(--color-primary);text-transform:uppercase;padding-bottom:.5rem;border-bottom:2px solid var(--color-border)}.data-table{margin-top:1rem!important}.data-table th{background-color:#f8fafc!important}.print-totals{display:flex;justify-content:flex-end;margin-top:1.5rem}.print-totals table{width:40%}.print-totals td{padding:.5rem 1rem}.print-totals .total-row{font-weight:700;font-size:1.1rem;border-top:3px solid var(--color-primary)}.print-notes{border-top:2px solid var(--color-border);margin-top:2rem;padding-top:1.5rem}}
</style></head><body><div id="loader"><div class="spinner"></div></div>
HTML;
}

function render_navigation(string $active_view): void {
    $views = [
        'dashboard'   => ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard'],
        'proveedores' => ['icon' => 'fa-truck', 'label' => 'Gestión de Proveedores'],
    ];
    echo '<aside class="sidebar"><h2><i class="fas fa-cogs"></i> Sistema de Compras</h2><nav class="nav-menu">';
    
    foreach ($views as $view => $details) {
        $class = ($active_view === $view && basename($_SERVER['PHP_SELF']) === 'inventario_compras.php') ? 'active' : '';
        echo "<a href='inventario_compras.php?view={$view}' class='{$class}'><i class='fas {$details['icon']}'></i><span>{$details['label']}</span></a>";
    }

    $nueva_compra_class = $active_view === 'compras_nueva' ? 'active' : '';
    $historico_class = $active_view === 'compras_historico' ? 'active' : '';
    echo "<div class='nav-section-title'>Compras</div>";
    echo "<a href='?view=compras_nueva' class='{$nueva_compra_class}'><i class='fas fa-plus-circle'></i><span>Nueva Compra</span></a>";
    echo "<a href='?view=compras_historico' class='{$historico_class}'><i class='fas fa-history'></i><span>Histórico de Compras</span></a>";

    echo "<div class='nav-section-title'>Módulos Adicionales</div>";
    $script_name = basename($_SERVER['PHP_SELF']);
    $productos_class = ($script_name === 'productos.php') ? 'active' : '';
    echo "<a href='productos.php' class='{$productos_class}'><i class='fas fa-box-open'></i><span>Productos</span></a>";

    $cotizaciones_class = ($script_name === 'cotizaciones.php') ? 'active' : '';
    echo "<a href='cotizaciones.php' class='{$cotizaciones_class}'><i class='fas fa-calculator'></i><span>Cotizaciones</span></a>";

    echo '</nav></aside><main class="content-wrapper">';
}

function render_mensaje(?array $mensaje): void {
    if (!$mensaje) return;
    $tipo = e($mensaje['tipo']); $texto = e($mensaje['texto']); $icono = $tipo === 'success' ? 'check-circle' : 'exclamation-triangle';
    echo "<div class='alert alert-{$tipo}'><i class='fas fa-{$icono}'></i> {$texto}</div>";
}

function render_dashboard_view(array $metrics, array $inventory, array $topProveedores): void {
    $total_compras = e($metrics['total_compras']);
    $monto_comprometido = e(format_cop($metrics['monto_total_comprometido']));
    $total_pagado = e(format_cop($metrics['total_pagado']));
    $compras_pendientes = e($metrics['compras_pendientes']);
    $total_pago_envio = e(format_cop($metrics['total_pago_envio']));
    $saldo_a_favor = e(format_cop($metrics['saldo_a_favor']));

    echo <<<HTML
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Dashboard Financiero y Operativo</h1>
        <a href="dashboard.php" class="btn btn-primary"><i class="fa-arrow-left"></i> Volver a Menu Principal</a>
    </div>
    <div class="dashboard-grid">
        <a href="?view=compras_historico" style="text-decoration: none; color: inherit;">
            <div class="metric-card"><div class="icon"><i class="fas fa-receipt"></i></div><div class="content"><div class="value">{$total_compras}</div><div class="label">Total Compras</div></div></div>
        </a>
        <div class="metric-card"><div class="icon" style="background: linear-gradient(135deg, var(--color-info), #60a5fa);"><i class="fas fa-file-invoice-dollar"></i></div><div class="content"><div class="value">{$monto_comprometido}</div><div class="label">Monto Comprometido</div></div></div>
        <div class="metric-card"><div class="icon" style="background: linear-gradient(135deg, #10b981, #34d399);"><i class="fas fa-check-double"></i></div><div class="content"><div class="value">{$total_pagado}</div><div class="label">Total Pagado</div></div></div>
        <a href="?view=compras_historico&filter=pendientes" style="text-decoration: none; color: inherit;">
            <div class="metric-card"><div class="icon" style="background: linear-gradient(135deg, #ef4444, #f87171);"><i class="fas fa-hourglass-half"></i></div><div class="content"><div class="value">{$compras_pendientes}</div><div class="label">Compras Pendientes</div></div></div>
        </a>
        <div class="metric-card"><div class="icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);"><i class="fas fa-truck-loading"></i></div><div class="content"><div class="value">{$total_pago_envio}</div><div class="label">Pagos de Envío/Contraentrega</div></div></div>
        <div class="metric-card"><div class="icon" style="background: linear-gradient(135deg, #22c55e, #4ade80);"><i class="fas fa-tags"></i></div><div class="content"><div class="value">{$saldo_a_favor}</div><div class="label">Descuentos Obtenidos (S. a Favor)</div></div></div>
    </div>
    <div class="form-grid" style="align-items: flex-start;">
    <div><div class="card">
    <div class="card-header"><h2><i class="fas fa-boxes"></i> Resumen de Inventario</h2><button class="toggle-btn" data-target="inventory-content"><i class="fas fa-minus"></i></button></div>
    <div id="inventory-content" class="collapsible-content">
HTML;
    if (empty($inventory)) {
        echo '<p style="text-align:center; padding: 2rem; color: var(--color-text-muted);">No hay productos registrados en compras.</p>';
    } else {
        $total_unidades = array_sum(array_column($inventory, 'total_cantidad'));
        $productos_unicos = count($inventory);
        $producto_top = $inventory[0]['tipo_producto'];
        echo '<div class="inventory-stats">';
        echo '<div class="stat-item"><div class="stat-value">'.$productos_unicos.'</div><div class="stat-label">Productos Únicos</div></div>';
        echo '<div class="stat-item"><div class="stat-value">'.number_format($total_unidades, 0).'</div><div class="stat-label">Unidades Totales</div></div>';
        echo '<div class="stat-item"><div class="stat-value" style="font-size:1.2rem;">'.e($producto_top).'</div><div class="stat-label">Producto Top</div></div>';
        echo '</div>';
        echo '<div class="table-responsive"><table class="data-table"><thead><tr><th>Producto</th><th>Cantidad Total</th><th>Acciones</th></tr></thead><tbody>';
        foreach ($inventory as $item) {
            echo '<tr><td><strong>' . e($item['tipo_producto']) . '</strong></td><td>' . e(number_format($item['total_cantidad'], 0)) . ' unidades</td><td class="actions-cell"><a href="?view=inventario_detalle&producto=' . urlencode(e($item['tipo_producto'])) . '" class="btn btn-info" title="Ver Historial"><i class="fas fa-history"></i></a></td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div></div></div>';
    echo '<div><div class="card">';
    echo '<div class="card-header"><h2><i class="fas fa-trophy"></i> Top 5 Proveedores</h2><button class="toggle-btn" data-target="proveedores-content"><i class="fas fa-minus"></i></button></div>';
    echo '<div id="proveedores-content" class="collapsible-content">';
    echo '<div class="table-responsive"><table class="data-table"><thead><tr><th>Proveedor</th><th>Total Comprado</th><th># Compras</th></tr></thead><tbody>';
    if (empty($topProveedores)) {
        echo '<tr><td colspan="3" style="text-align:center; padding: 2rem; color: var(--color-text-muted);">No hay datos de proveedores.</td></tr>';
    } else {
        foreach ($topProveedores as $proveedor) {
            echo '<tr><td><strong>' . e($proveedor['nombre']) . '</strong></td><td>' . e(format_cop($proveedor['total_gastado'])) . '</td><td>' . e($proveedor['num_compras']) . '</td></tr>';
        }
    }
    echo '</tbody></table></div>';
    echo '</div></div></div>';
    echo '</div>';
}

function render_inventario_detalle_view(string $producto, array $historial): void {
    echo '<div class="page-header"><h1><i class="fas fa-history"></i> Historial de Compra: ' . e($producto) . '</h1><a href="?view=dashboard" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a></div>';
    echo '<div class="card"><div class="table-responsive"><table class="data-table"><thead><tr><th>Fecha de Compra</th><th>Cantidad</th><th>Proveedor</th><th>Orden de Compra</th><th>Acciones</th></tr></thead><tbody>';
    if (empty($historial)) {
        echo '<tr><td colspan="5" style="text-align:center; padding: 2rem; color: var(--color-text-muted);">No hay historial para este producto.</td></tr>';
    } else {
        foreach ($historial as $item) {
            echo '<tr>';
            echo '<td>' . date('d/m/Y', strtotime($item['fecha_compra'])) . '</td>';
            echo '<td><strong>' . e(number_format($item['cantidad'], 0)) . '</strong></td>';
            echo '<td>' . e($item['proveedor_nombre']) . '</td>';
            echo '<td>' . e($item['orden_compra']) . '</td>';
            echo '<td class="actions-cell"><a href="?view=compra_detalle&id=' . e($item['id']) . '" class="btn btn-info" title="Ver Detalle de Compra"><i class="fas fa-eye"></i></a></td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div></div>';
}

function render_proveedores_view(array $proveedores, ?array $proveedor_a_editar): void {
    $is_edit = $proveedor_a_editar !== null; $form_title = $is_edit ? '<i class="fas fa-edit"></i> Editar Proveedor' : '<i class="fas fa-plus-circle"></i> Crear Nuevo Proveedor'; $button_text = $is_edit ? 'Actualizar Proveedor' : 'Guardar Proveedor';
    $colors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#d946ef'];
    
    echo '<div class="page-header"><h1><i class="fas fa-truck"></i> Gestión de Proveedores</h1><a href="?view=dashboard" class="btn btn-secondary"><i class="fas fa-tachometer-alt"></i> Ir al Dashboard</a></div>';
    echo '<div class="card">';
    echo "<h2>{$form_title}</h2>";
    echo "<form method='POST'><input type='hidden' name='action' value='guardar_proveedor'>";
    if ($is_edit) { echo "<input type='hidden' name='id' value='" . e($proveedor_a_editar['id']) . "'>"; }
    echo '<div class="form-grid"><div class="form-group"><label for="nombre" class="form-label">Nombre del Proveedor <span class="required">*</span></label><input type="text" name="nombre" id="nombre" class="form-input" value="'.e($proveedor_a_editar['nombre'] ?? '').'" required></div><div class="form-group"><label for="nit" class="form-label">NIT <span class="required">*</span></label><input type="text" name="nit" id="nit" class="form-input" value="'.e($proveedor_a_editar['nit'] ?? '').'" required></div><div class="form-group"><label for="contacto_nombre" class="form-label">Nombre de Contacto</label><input type="text" name="contacto_nombre" id="contacto_nombre" class="form-input" value="'.e($proveedor_a_editar['contacto_nombre'] ?? '').'"></div><div class="form-group"><label for="contacto_email" class="form-label">Email de Contacto</label><input type="email" name="contacto_email" id="contacto_email" class="form-input" value="'.e($proveedor_a_editar['contacto_email'] ?? '').'"></div><div class="form-group"><label for="contacto_telefono" class="form-label">Teléfono de Contacto</label><input type="tel" name="contacto_telefono" id="contacto_telefono" class="form-input" value="'.e($proveedor_a_editar['contacto_telefono'] ?? '').'"></div><div class="form-group"><label for="website" class="form-label">Sitio Web</label><input type="url" name="website" id="website" class="form-input" value="'.e($proveedor_a_editar['website'] ?? '').'"></div></div>';
    echo '<div class="form-grid" style="grid-template-columns:1fr"><div class="form-group"><label for="direccion" class="form-label">Dirección</label><textarea name="direccion" id="direccion" class="form-textarea">'.e($proveedor_a_editar['direccion'] ?? '').'</textarea></div></div>';
    echo '<div class="form-grid" style="grid-template-columns:1fr"><div class="form-group"><label for="notas" class="form-label">Notas Internas</label><textarea name="notas" id="notas" class="form-textarea">'.e($proveedor_a_editar['notas'] ?? '').'</textarea></div></div>';
    echo "<div class='btn-container'><button type='submit' class='btn btn-primary'><i class='fas fa-save'></i> {$button_text}</button>";
    if ($is_edit) { echo "<a href='?view=proveedores' class='btn btn-secondary'><i class='fas fa-times'></i> Cancelar Edición</a>"; }
    echo '</div></form></div>';
    echo '<div class="card"><h2><i class="fas fa-list"></i> Lista de Proveedores</h2><div class="table-responsive"><table class="data-table"><thead><tr><th>Nombre</th><th>NIT</th><th>Contacto</th><th>Email</th><th>Teléfono</th><th class="actions-cell">Acciones</th></tr></thead><tbody>';
    if(empty($proveedores)) {
        echo '<tr><td colspan="6" style="text-align:center; padding: 2rem; color: var(--color-text-muted);">No hay proveedores registrados.</td></tr>';
    } else { foreach($proveedores as $p) {
        $color = $colors[$p['id'] % count($colors)];
        $inicial = strtoupper(substr($p['nombre'], 0, 1));
        echo '<tr>';
        echo '<td><div class="provider-cell"><span class="provider-avatar" style="background-color:'.$color.';">'.$inicial.'</span><a href="?view=proveedor_detalle&id='.e($p['id']).'" title="Ver detalle y compras">'.e($p['nombre']).'</a></div></td>';
        echo '<td>'.e($p['nit']).'</td><td>'.e($p['contacto_nombre'] ?? 'N/A').'</td><td>'.e($p['contacto_email'] ?? 'N/A').'</td><td>'.e($p['contacto_telefono'] ?? 'N/A').'</td><td class="actions-cell"><a href="?view=proveedores&edit_id='.e($p['id']).'" class="btn btn-warning" title="Editar"><i class="fas fa-edit"></i></a><form method="POST" style="display:inline;" onsubmit="return confirm(\'¿Estás seguro de eliminar este proveedor?\');"><input type="hidden" name="action" value="eliminar_proveedor"><input type="hidden" name="id" value="'.e($p['id']).'"><button type="submit" class="btn btn-danger" title="Eliminar"><i class="fas fa-trash"></i></button></form></td></tr>';
    } }
    echo '</tbody></table></div></div>';
}

function render_proveedor_detalle_view(?array $proveedor, array $compras): void {
    if (!$proveedor) { echo '<h1>Proveedor no encontrado</h1><p>El proveedor que buscas no existe o ha sido eliminado.</p><a href="?view=proveedores" class="btn btn-primary">Volver a la lista</a>'; return; }
    echo '<div class="page-header"><h1><i class="fas fa-truck-loading"></i> Detalle del Proveedor</h1><a href="?view=proveedores" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver a la Lista</a></div>';
    echo '<div class="card"><div class="form-grid" style="gap: 1rem 2rem;">';
    echo '<div><strong>Nombre:</strong><p>'.e($proveedor['nombre']).'</p></div>'; echo '<div><strong>NIT:</strong><p>'.e($proveedor['nit']).'</p></div>'; echo '<div><strong>Contacto:</strong><p>'.e($proveedor['contacto_nombre'] ?? 'N/A').'</p></div>'; echo '<div><strong>Email:</strong><p>'.e($proveedor['contacto_email'] ?? 'N/A').'</p></div>'; echo '<div><strong>Teléfono:</strong><p>'.e($proveedor['contacto_telefono'] ?? 'N/A').'</p></div>'; echo '<div><strong>Sitio Web:</strong><p>'.( $proveedor['website'] ? '<a href="'.e($proveedor['website']).'" target="_blank">'.e($proveedor['website']).'</a>' : 'N/A' ).'</p></div>'; echo '<div style="grid-column: 1 / -1;"><strong>Dirección:</strong><p>'.e($proveedor['direccion'] ?? 'N/A').'</p></div>'; echo '<div style="grid-column: 1 / -1;"><strong>Notas:</strong><p>'.nl2br(e($proveedor['notas'] ?? 'N/A')).'</p></div>';
    echo '</div></div>';
    echo '<div class="card"><h2><i class="fas fa-history"></i> Histórico de Compras</h2><div class="table-responsive"><table class="data-table"><thead><tr><th>Orden</th><th>Fecha</th><th>Total</th><th>Estado</th><th>Producto</th><th>Factura</th><th class="actions-cell">Acciones</th></tr></thead><tbody>';
    if (empty($compras)) {
        echo '<tr><td colspan="7" style="text-align:center; padding: 2rem; color: var(--color-text-muted);">No hay compras registradas para este proveedor.</td></tr>';
    } else {
        foreach ($compras as $c) {
            echo '<tr><td><strong>'.e($c['orden_compra']).'</strong></td><td>'.date('d/m/Y', strtotime($c['fecha_compra'])).'</td><td><strong>'.format_cop($c['total_pagar']).'</strong></td><td><span class="status-badge status-'.e($c['estado']).'">'.e($c['estado']).'</span></td>';
            echo '<td>'; if ($c['producto_recibido'] === 'si') { echo '<i class="fas fa-check-circle" style="color: var(--color-secondary);"></i> Recibido'; } else { echo '<i class="fas fa-clock" style="color: var(--color-warning);"></i> Pendiente'; } echo '</td>';
            echo '<td>'; if ($c['factura_recibida'] === 'si') { echo '<i class="fas fa-file-invoice" style="color: var(--color-secondary);"></i> Recibida'; } else { echo '<i class="fas fa-file-invoice" style="color: var(--color-text-muted);"></i> Pendiente'; } echo '</td>';
            echo '<td class="actions-cell"><a href="?view=compra_detalle&id='.e($c['id']).'" class="btn btn-info" title="Ver Detalle"><i class="fas fa-eye"></i></a> <a href="?view=compras_nueva&edit_id='.e($c['id']).'" class="btn btn-warning" title="Editar Compra"><i class="fas fa-edit"></i></a></td></tr>';
        }
    }
    echo '</tbody></table></div></div>';
}

function render_compra_form_view(array $proveedores, ?array $compra_a_editar, array $productos): void {
    $is_edit = $compra_a_editar !== null;
    $form_title = $is_edit ? '<i class="fas fa-edit"></i> Editar Compra' : '<i class="fas fa-plus-circle"></i> Registrar Nueva Compra';
    $button_text = $is_edit ? 'Actualizar Compra' : 'Guardar Compra';

    echo '<div class="page-header"><h1><i class="fas fa-shopping-cart"></i> Gestión de Compras</h1></div>';
    echo '<div class="card">';
    echo "<h2>{$form_title}</h2>";
    echo "<form id='compra-form' method='POST' enctype='multipart/form-data'><input type='hidden' name='action' value='guardar_compra'>";
    if($is_edit) { echo "<input type='hidden' name='id' value='".e($compra_a_editar['id'])."'>"; }

    // SECCIÓN 1: ENCABEZADO
    echo '<fieldset><legend><i class="fas fa-file-alt"></i> Encabezado de la Compra</legend><div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">';
    
    echo '<div class="form-group"><label for="proveedor_id" class="form-label">Proveedor <span class="required">*</span></label><select name="proveedor_id" id="proveedor_id" class="form-select" required><option value="">Seleccionar proveedor...</option>';
    foreach($proveedores as $prov) { $selected = ($is_edit && $compra_a_editar['proveedor_id'] == $prov['id']) ? 'selected' : ''; echo "<option value='".e($prov['id'])."' {$selected}>".e($prov['nombre'])."</option>"; }
    echo '</select></div>';
    echo '<div class="form-group"><label for="fecha_compra" class="form-label">Fecha de Compra <span class="required">*</span></label><input type="date" name="fecha_compra" id="fecha_compra" class="form-input" value="'.e($compra_a_editar['fecha_compra'] ?? date('Y-m-d')).'" required></div>';
    echo '<div class="form-group"><label for="orden_compra" class="form-label">Orden de Compra <span class="required">*</span></label><input type="text" name="orden_compra" id="orden_compra" class="form-input" value="'.e($compra_a_editar['orden_compra'] ?? '').'" required></div>';
    echo '<div class="form-group"><label for="numero_cotizacion" class="form-label">Nº Cotización</label><input type="text" name="numero_cotizacion" id="numero_cotizacion" class="form-input" placeholder="Ej: 8465" value="'.e($compra_a_editar['numero_cotizacion'] ?? '').'"></div>';
    echo '<div class="form-group"><label for="estado" class="form-label">Estado</label><select name="estado" id="estado" class="form-select">';
    $estados = ['Ordenada', 'Enviada', 'Completada', 'Cancelada'];
    foreach($estados as $estado) { $selected = ($is_edit && $compra_a_editar['estado'] == $estado) ? 'selected' : ''; echo "<option value='".e($estado)."' {$selected}>".e($estado)."</option>"; }
    echo '</select></div>';
    echo '<div class="form-group"><label for="terminos_pago" class="form-label">Términos de Pago</label><select name="terminos_pago" id="terminos_pago" class="form-select">';
    $terminos_options = ['Contado', 'Crédito 15 días', 'Crédito 30 días', 'Crédito 45 días', 'Crédito 60 días', 'Crédito 90 días'];
    foreach($terminos_options as $termino) { $selected = ($is_edit && $compra_a_editar['terminos_pago'] == $termino) ? 'selected' : ''; echo "<option value='".e($termino)."' {$selected}>".e($termino)."</option>"; }
    echo '</select></div>';
    echo '<div class="form-group"><label for="costo_envio" class="form-label">Costo de Envío</label><input type="number" name="costo_envio" id="costo_envio" class="form-input calc-field" step="1" min="0" value="'.e($compra_a_editar['costo_envio'] ?? '0').'"></div>';
    echo '<div class="form-group"><label for="archivo_orden" class="form-label">Adjuntar Orden de Compra</label><input type="file" name="archivo_orden" id="archivo_orden" class="form-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.xml,.txt">';
    if ($is_edit && !empty($compra_a_editar['archivo_orden'])) { echo '<div class="file-info">Archivo actual: <a href="uploads/'.e($compra_a_editar['archivo_orden']).'" target="_blank" class="file-link">'.e($compra_a_editar['archivo_orden']).'</a></div>'; }
    echo '</div>';
    echo '</div>'; // Fin form-grid
    echo '<div class="form-grid" style="grid-template-columns:1fr"><div class="form-group"><label for="direccion_envio" class="form-label">Dirección de Envío</label><textarea name="direccion_envio" id="direccion_envio" class="form-textarea" placeholder="Dirección completa de envío...">'.e($compra_a_editar['direccion_envio'] ?? '').'</textarea></div></div>';
    echo '<div class="form-grid"><div class="form-group"><label for="notas" class="form-label">Notas Internas</label><textarea name="notas" id="notas" class="form-textarea" placeholder="Notas internas sobre la compra...">'.e($compra_a_editar['notas'] ?? '').'</textarea></div><div class="form-group"><label for="novedades" class="form-label">Novedades</label><textarea name="novedades" id="novedades" class="form-textarea" placeholder="Novedades y seguimiento de la compra...">'.e($compra_a_editar['novedades'] ?? '').'</textarea></div></div>';
    echo '<div class="form-group"><label class="form-label">¿Pago Contra Entrega?</label><div class="option-group"><div class="option-item">';
    $pago_contraentrega_checked = ($is_edit && !empty($compra_a_editar['pago_contraentrega'])) ? 'checked' : '';
    echo "<input type='checkbox' name='pago_contraentrega' id='pago_contraentrega' {$pago_contraentrega_checked}><label for='pago_contraentrega'>Sí, es pago contra entrega</label></div></div></div>";
    echo '<div class="form-group" id="valor-contra-entrega-group" style="display:none;"><label for="valor_contra_entrega" class="form-label">Valor Contra Entrega</label><input type="number" name="valor_contra_entrega" id="valor_contra_entrega" class="form-input" step="1" min="0" value="'.e($compra_a_editar['valor_contra_entrega'] ?? '').'"></div></fieldset>';

    // SECCIÓN 2: DETALLE DE COMPRA
    echo '<fieldset><legend><i class="fas fa-dolly"></i> Detalle de Compra</legend>';
    echo '<!-- Contenedor para las líneas de producto -->';
    echo '<div id="product-lines-container">';

    echo '<div class="product-line" style="background-color: var(--color-bg); padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1rem;">';
    echo '<div class="form-grid" style="grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr 1fr 1fr 0.5fr; align-items: end; gap: 1rem;">';
    
    echo '<div class="form-group"><label class="form-label">Producto</label>';
    echo '<input list="productos-lista" name="tipo_producto[]" class="form-input producto-nombre" placeholder="Seleccione o escriba..." value="'.e($compra_a_editar['tipo_producto'] ?? '').'" required>';
    echo '<datalist id="productos-lista">';
    foreach ($productos as $producto) {
        $part_number = e($producto['part_number'] ?? '');
        $product_name = e($producto['nombre']);
        echo "<option value='{$product_name}' data-part-number='{$part_number}'>";
    }
    echo '</datalist></div>';

    echo '<div class="form-group"><label class="form-label">Nº de Parte</label><input type="text" name="part_number[]" class="form-input part-number-input" placeholder="Autocompletado..." value="'.e($compra_a_editar['part_number'] ?? '').'"></div>';
    echo '<div class="form-group"><label class="form-label">Cantidad <span class="required">*</span></label><input type="number" name="cantidad[]" class="form-input calc-field" step="1" min="1" value="'.e($compra_a_editar['cantidad'] ?? '1').'" required></div>';
    echo '<div class="form-group"><label class="form-label">Precio sin IVA <span class="required">*</span></label><input type="number" name="precio_sin_iva[]" class="form-input calc-field" step="1" min="0" value="'.e($compra_a_editar['precio_sin_iva'] ?? '').'" required></div>';
    echo '<div class="form-group"><label class="form-label">IVA %</label><input type="number" name="iva_porcentaje[]" class="form-input calc-field" step="0.01" min="0" value="19"></div>';
    echo '<div class="form-group"><label class="form-label">Valor IVA</label><input type="number" name="iva[]" class="form-input" step="1" min="0" value="'.e($compra_a_editar['iva'] ?? '0').'" readonly></div>';
    echo '<div class="form-group"><label class="form-label">Retención</label><input type="number" name="retefuente[]" class="form-input calc-field" step="1" min="0" value="'.e($compra_a_editar['retefuente'] ?? '0').'"></div>';
    
    echo '<div class="form-group"><button type="button" class="btn btn-danger remove-product-line" style="display:none;"><i class="fas fa-trash"></i></button></div>';

    echo '</div></div>';
    
    echo '</div>';
    
    if (!$is_edit) {
        echo '<div class="btn-container" style="justify-content: flex-start; border-top: none; padding-top: 0; margin-top: 1rem;"><button type="button" id="add-product-btn" class="btn btn-info"><i class="fas fa-plus"></i> Agregar Producto</button></div>';
    }
    
    echo '<div class="form-grid" style="grid-template-columns:1fr"><div class="form-group"><label for="descripcion" class="form-label">Descripción General de la Compra</label><textarea name="descripcion" id="descripcion" class="form-textarea" placeholder="Descripción detallada de la compra en general...">'.e($compra_a_editar['descripcion'] ?? '').'</textarea></div></div>';
    echo '</fieldset>';


    // SECCIÓN 3: RESUMEN FINANCIERO
    echo '<fieldset><legend><i class="fas fa-calculator"></i> Resumen Financiero</legend>';
    echo '<div class="summary" style="margin-top:0;"><h3><i class="fas fa-chart-line"></i> Resumen del Cálculo</h3><div class="summary-row"><span>Subtotal (Cantidad × Precio):</span><span class="summary-value" id="summary-subtotal">$0</span></div><div class="summary-row"><span>Costo de Envío:</span><span class="summary-value" id="summary-envio">$0</span></div><div class="summary-row"><span>IVA:</span><span class="summary-value" id="summary-iva">$0</span></div><div class="summary-row"><span>Retención en la Fuente:</span><span class="summary-value" id="summary-retefuente">-$0</span></div><div class="summary-row total"><span>Total a Pagar:</span><span class="summary-value" id="summary-total">$0</span></div></div></fieldset>';
    
    // SECCIÓN 4: FINALIZACIÓN Y PAGO
    echo '<fieldset><legend><i class="fas fa-check-circle"></i> Finalización y Pago</legend><div class="form-grid">';
    echo '<div class="form-group"><label for="fecha_pago" class="form-label">Fecha de Pago</label><input type="date" name="fecha_pago" id="fecha_pago" class="form-input" value="'.e($compra_a_editar['fecha_pago'] ?? '').'"></div>';
    echo '<div class="form-group"><label for="valor_pagado" class="form-label">Valor Pagado</label><input type="number" name="valor_pagado" id="valor_pagado" class="form-input diff-field" step="1" min="0" value="'.e($compra_a_editar['valor_pagado'] ?? '').'"></div>';
    echo '<div class="form-group"><label for="valor_factura" class="form-label">Valor de la Factura</label><input type="number" name="valor_factura" id="valor_factura" class="form-input diff-field" step="1" min="0" value="'.e($compra_a_editar['valor_factura'] ?? '').'"></div>';
    echo '<div class="form-group"><label for="diferencia" class="form-label">Diferencia (Factura - Pagado)</label><input type="text" id="diferencia" class="form-input" readonly></div>';
    echo '</div>';
    
    echo '<div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">';
    echo '<div class="form-group"><label class="form-label">¿Producto Recibido?</label><div class="option-group">';
    $producto_si_checked = ($is_edit && $compra_a_editar['producto_recibido'] === 'si') ? 'checked' : ''; $producto_no_checked = (!$is_edit || $compra_a_editar['producto_recibido'] === 'no') ? 'checked' : '';
    echo "<div class='option-item'><input type='radio' name='producto_recibido' id='producto_si' value='si' {$producto_si_checked}><label for='producto_si'>Sí</label></div><div class='option-item'><input type='radio' name='producto_recibido' id='producto_no' value='no' {$producto_no_checked}><label for='producto_no'>No</label></div></div></div>";
    echo '<div class="form-group"><label for="fecha_recibido" class="form-label">Fecha Recibido</label><input type="date" name="fecha_recibido" id="fecha_recibido" class="form-input" value="'.e($compra_a_editar['fecha_recibido'] ?? '').'"></div>';
    echo '<div class="form-group"><label class="form-label">¿Factura Recibida?</label><div class="option-group">';
    $factura_si_checked = ($is_edit && $compra_a_editar['factura_recibida'] === 'si') ? 'checked' : ''; $factura_no_checked = (!$is_edit || $compra_a_editar['factura_recibida'] === 'no') ? 'checked' : '';
    echo "<div class='option-item'><input type='radio' name='factura_recibida' id='factura_si' value='si' {$factura_si_checked}><label for='factura_si'>Sí</label></div><div class='option-item'><input type='radio' name='factura_recibida' id='factura_no' value='no' {$factura_no_checked}><label for='factura_no'>No</label></div></div></div>";
    echo '<div class="form-group"><label for="archivo_factura" class="form-label">Adjuntar Factura</label><input type="file" name="archivo_factura" id="archivo_factura" class="form-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.xml,.txt">';
    if ($is_edit && !empty($compra_a_editar['archivo_factura'])) { echo '<div class="file-info">Archivo actual: <a href="uploads/'.e($compra_a_editar['archivo_factura']).'" target="_blank" class="file-link">'.e($compra_a_editar['archivo_factura']).'</a></div>'; }
    echo '</div></fieldset>';
    
    echo "<div class='btn-container'><button type='submit' class='btn btn-primary'><i class='fas fa-save'></i> {$button_text}</button>";
    if ($is_edit) { echo "<a href='?view=compras_historico' class='btn btn-secondary'><i class='fas fa-times'></i> Cancelar Edición</a>"; }
    echo '</div></form></div>';
}

function render_compras_historico_view(array $compras, ?string $startDate, ?string $endDate): void {
    echo '<div class="page-header"><h1><i class="fas fa-history"></i> Histórico de Compras</h1><a href="?view=compras_nueva" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Nueva Compra</a></div>';
    
    echo '<div class="card"><form method="GET"><input type="hidden" name="view" value="compras_historico"><h2><i class="fas fa-filter"></i> Filtrar por Fecha</h2><div class="form-grid" style="grid-template-columns: 1fr 1fr auto; align-items: flex-end;">';
    echo '<div class="form-group"><label for="start_date" class="form-label">Fecha de Inicio</label><input type="date" name="start_date" id="start_date" class="form-input" value="'.e($startDate ?? '').'"></div>';
    echo '<div class="form-group"><label for="end_date" class="form-label">Fecha de Fin</label><input type="date" name="end_date" id="end_date" class="form-input" value="'.e($endDate ?? '').'"></div>';
    echo '<div class="form-group"><button type="submit" class="btn btn-info"><i class="fas fa-search"></i> Aplicar Filtro</button></div>';
    echo '</div></form></div>';

    echo '<div class="card"><div class="table-responsive"><table class="data-table"><thead><tr><th>Orden</th><th>Proveedor</th><th>Fecha</th><th>Total</th><th>Estado</th><th>Producto</th><th>Factura</th><th class="actions-cell">Acciones</th></tr></thead><tbody>';
    if(empty($compras)) {
        echo '<tr><td colspan="8" style="text-align:center; padding: 2rem; color: var(--color-text-muted);">No hay compras registradas que coincidan con los filtros.</td></tr>';
    } else {
        foreach($compras as $c) {
            echo '<tr><td><strong>'.e($c['orden_compra']).'</strong></td><td>'.e($c['proveedor_nombre'] ?? 'N/A').'</td><td>'.date('d/m/Y', strtotime($c['fecha_compra'])).'</td><td><strong>'.format_cop($c['total_pagar']).'</strong></td><td><span class="status-badge status-'.e($c['estado']).'">'.e($c['estado']).'</span></td>';
            echo '<td>'; if ($c['producto_recibido'] === 'si') { echo '<i class="fas fa-check-circle" style="color: var(--color-secondary);"></i> Recibido'; } else { echo '<i class="fas fa-clock" style="color: var(--color-warning);"></i> Pendiente'; } echo '</td>';
            echo '<td>'; if ($c['factura_recibida'] === 'si') { echo '<i class="fas fa-file-invoice" style="color: var(--color-secondary);"></i> Recibida'; } else { echo '<i class="fas fa-file-invoice" style="color: var(--color-text-muted);"></i> Pendiente'; } echo '</td>';
            echo '<td class="actions-cell"><a href="?view=compra_detalle&id='.e($c['id']).'" class="btn btn-info" title="Ver Detalle"><i class="fas fa-eye"></i></a> <a href="?view=compras_nueva&edit_id='.e($c['id']).'" class="btn btn-warning" title="Editar"><i class="fas fa-edit"></i></a><form method="POST" style="display:inline;" onsubmit="return confirm(\'¿Estás seguro de eliminar esta compra?\');"><input type="hidden" name="action" value="eliminar_compra"><input type="hidden" name="id" value="'.e($c['id']).'"><button type="submit" class="btn btn-danger" title="Eliminar"><i class="fas fa-trash"></i></button></form></td></tr>';
        }
    }
    echo '</tbody></table></div></div>';
}

function render_compra_detalle_view(?array $compra): void {
    if (!$compra) { echo '<h1>Compra no encontrada</h1><p>La compra que buscas no existe o ha sido eliminada.</p><a href="?view=compras_historico" class="btn btn-primary">Volver al Histórico</a>'; return; }
    
    echo '<div class="page-header print-hide"><h1><i class="fas fa-file-invoice-dollar"></i> Detalle de Compra</h1><div><button onclick="window.print()" class="btn btn-info"><i class="fas fa-print"></i> Imprimir</button> <a href="?view=compras_nueva&edit_id='.e($compra['id']).'" class="btn btn-warning"><i class="fas fa-edit"></i> Editar</a> <a href="?view=compras_historico" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a></div></div>';
    echo '<div class="card" id="invoice-container">';
    echo '<!-- Encabezado para Impresión -->';
    echo '<div class="print-header" style="display: none;" aria-hidden="true">';
    echo '  <div class="company-logo">RINKU SOLUCIONES S.A.S</div>';
    echo '  <div class="quote-info">';
    echo '      <div class="quote-title">ORDEN DE COMPRA</div>';
    echo '      <p><strong># '.e($compra['orden_compra']).'</strong></p>';
    echo '      <p>Fecha: '.date('d/m/Y', strtotime($compra['fecha_compra'])).'</p>';
    echo '  </div>';
    echo '</div>';
    echo '<!-- Sección de Proveedor/Comprador para Impresión -->';
    echo '<div class="print-parties" style="display: none;" aria-hidden="true">';
    echo '  <div class="print-party-box">';
    echo '      <div class="section-title">PROVEEDOR</div>';
    echo '      <p><strong>Nombre:</strong> '.e($compra['proveedor_nombre']).'</p>';
    echo '      <p><strong>NIT:</strong> '.e($compra['proveedor_nit'] ?? 'N/A').'</p>';
    echo '      <p><strong>Contacto:</strong> '.e($compra['proveedor_contacto'] ?? 'N/A').'</p>';
    echo '      <p><strong>Dirección:</strong> '.e($compra['proveedor_direccion'] ?? 'N/A').'</p>';
    echo '  </div>';
    echo '  <div class="print-party-box">';
    echo '      <div class="section-title">ENVIAR A</div>';
    echo '      <p><strong>Dirección:</strong> '.e($compra['direccion_envio'] ?? 'N/A').'</p>';
    echo '      <p><strong>Términos de Pago:</strong> '.e($compra['terminos_pago']).'</p>';
    echo '  </div>';
    echo '</div>';
    echo '<div class="print-show">';
    echo '<div style="display: flex; justify-content: space-between; padding-bottom: 2rem; border-bottom: 2px solid var(--color-border); margin-bottom: 2rem;">';
    echo '<div style="text-align: left;"><h3 style="margin:0; color: var(--color-text-muted);">PROVEEDOR</h3><p style="font-size: 1.2rem; font-weight: 600; margin: 0.5rem 0;">'.e($compra['proveedor_nombre']).'</p><p style="margin: 0;"><strong>NIT:</strong> '.e($compra['proveedor_nit'] ?? 'N/A').'</p><p style="margin: 0;"><strong>Contacto:</strong> '.e($compra['proveedor_contacto'] ?? 'N/A').'</p><p style="margin: 0;">'.e($compra['proveedor_direccion'] ?? '').'</p></div>';
    echo '<div style="text-align: right;"><h2 style="margin:0; color: var(--color-primary);">ORDEN DE COMPRA</h2><p style="font-size: 1.2rem; font-weight: 600; margin: 0.5rem 0;">#'.e($compra['orden_compra']).'</p><p style="margin: 0;"><strong>Fecha:</strong> '.date('d/m/Y', strtotime($compra['fecha_compra'])).'</p><p style="margin: 0;"><strong>Estado:</strong> <span class="status-badge status-'.e($compra['estado']).'" style="display:inline-block; margin-top: 5px;">'.e($compra['estado']).'</span></p></div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="table-responsive"><table class="data-table"><thead><tr><th>Nº Cotización</th><th>Nº Parte</th><th>Descripción</th><th>Cantidad Total</th><th>Subtotal</th></tr></thead><tbody>';
    $subtotal = $compra['precio_sin_iva'];
    echo '<tr><td>'.e($compra['numero_cotizacion'] ?? 'N/A').'</td><td>'.e($compra['part_number'] ?? 'N/A').' (y otros)</td><td>'.nl2br(e($compra['descripcion'] ?? 'Múltiples productos en esta orden.')).'</td><td>'.e(number_format($compra['cantidad'], 0, ',', '.')).'</td><td>'.e(format_cop($subtotal)).'</td></tr>';
    echo '</tbody></table></div>';
    echo '<div class="print-totals" style="display: none" aria-hidden="true"><table>';
    echo '<tr><td>Subtotal:</td><td style="text-align:right;">'.e(format_cop($subtotal)).'</td></tr>';
    echo '<tr><td>Costo de Envío:</td><td style="text-align:right;">'.e(format_cop($compra['costo_envio'])).'</td></tr>';
    echo '<tr><td>IVA:</td><td style="text-align:right;">'.e(format_cop($compra['iva'])).'</td></tr>';
    echo '<tr><td>Retención:</td><td style="text-align:right;">-'.e(format_cop($compra['retefuente'])).'</td></tr>';
    echo '<tr class="total-row"><td>TOTAL A PAGAR:</td><td style="text-align:right;">'.e(format_cop($compra['total_pagar'])).'</td></tr>';
    echo '</table></div>';
    echo '<div class="print-show" style="display: flex; justify-content: flex-end; margin-top: 2rem;"><div style="width: 400px;">';
    echo '<div class="summary-row"><span>Subtotal:</span><span class="summary-value">'.e(format_cop($subtotal)).'</span></div>';
    echo '<div class="summary-row"><span>Costo de Envío:</span><span class="summary-value">'.e(format_cop($compra['costo_envio'])).'</span></div>';
    echo '<div class="summary-row"><span>IVA:</span><span class="summary-value">'.e(format_cop($compra['iva'])).'</span></div>';
    echo '<div class="summary-row"><span>Retención en la Fuente:</span><span class="summary-value">-'.e(format_cop($compra['retefuente'])).'</span></div>';
    echo '<div class="summary-row total"><span>TOTAL A PAGAR:</span><span class="summary-value">'.e(format_cop($compra['total_pagar'])).'</span></div>';
    echo '</div></div>';
    echo '<div class="print-notes" style="display:none" aria-hidden="true"><h3>Notas Adicionales</h3><p>'.nl2br(e($compra['notas'] ?? 'N/A')).'</p></div>';
    echo '<div class="print-show" style="border-top: 2px solid var(--color-border); margin-top: 2.5rem; padding-top: 2rem;"><h2><i class="fas fa-info-circle"></i> Información Adicional</h2><div class="form-grid">';
    echo '<div><strong>Términos de Pago:</strong><p>'.e($compra['terminos_pago']).'</p></div>';
    echo '<div><strong>Producto Recibido:</strong><p>'.e(ucfirst($compra['producto_recibido'])).' ('.($compra['fecha_recibido'] ? date('d/m/Y', strtotime($compra['fecha_recibido'])) : 'Pendiente').')</p></div>';
    echo '<div><strong>Factura Recibida:</strong><p>'.e(ucfirst($compra['factura_recibida'])).'</p></div>';
    echo '<div><strong>Archivos Adjuntos:</strong>';
    if (!empty($compra['archivo_orden'])) { echo '<p><a href="uploads/'.e($compra['archivo_orden']).'" target="_blank" class="file-link"><i class="fas fa-file-alt"></i> Ver Orden de Compra</a></p>'; }
    if (!empty($compra['archivo_factura'])) { echo '<p><a href="uploads/'.e($compra['archivo_factura']).'" target="_blank" class="file-link"><i class="fas fa-file-invoice"></i> Ver Factura</a></p>'; }
    if (empty($compra['archivo_orden']) && empty($compra['archivo_factura'])) { echo '<p>N/A</p>'; }
    echo '</div>';
    echo '<div style="grid-column: 1 / -1;"><strong>Dirección de Envío:</strong><p>'.e($compra['direccion_envio'] ?? 'N/A').'</p></div>';
    echo '<div style="grid-column: 1 / -1;"><strong>Notas Internas:</strong><p>'.nl2br(e($compra['notas'] ?? 'N/A')).'</p></div>';
    echo '<div style="grid-column: 1 / -1;"><strong>Novedades:</strong><p>'.nl2br(e($compra['novedades'] ?? 'N/A')).'</p></div>';
    echo '</div></div>';
    echo '</div>';
}

function render_productos_view(array $productos, ?array $producto_a_editar): void {
    $is_edit = $producto_a_editar !== null;
    $form_title = $is_edit ? '<i class="fas fa-edit"></i> Editar Producto' : '<i class="fas fa-plus-circle"></i> Crear Nuevo Producto';
    $button_text = $is_edit ? 'Actualizar Producto' : 'Guardar Producto';

    echo '<div class="page-header"><h1><i class="fas fa-box-open"></i> Gestión de Productos</h1><a href="?view=dashboard" class="btn btn-secondary"><i class="fas fa-tachometer-alt"></i> Ir al Dashboard</a></div>';
    
    echo '<div class="card">';
    echo "<h2>{$form_title}</h2>";
    echo "<form method='POST' action='?view=productos'><input type='hidden' name='action' value='guardar_producto'>";
    if ($is_edit) { echo "<input type='hidden' name='id' value='" . e($producto_a_editar['id']) . "'>"; }
    
    echo '<div class="form-grid">';
    echo '<div class="form-group"><label for="nombre" class="form-label">Nombre del Producto <span class="required">*</span></label><input type="text" name="nombre" id="nombre" class="form-input" value="'.e($producto_a_editar['nombre'] ?? '').'" required></div>';
    echo '<div class="form-group"><label for="part_number" class="form-label">Nº de Parte Predeterminado</label><input type="text" name="part_number" id="part_number" class="form-input" value="'.e($producto_a_editar['part_number'] ?? '').'"></div>';
    echo '</div>';
    echo '<div class="form-grid" style="grid-template-columns:1fr"><div class="form-group"><label for="descripcion" class="form-label">Descripción</label><textarea name="descripcion" id="descripcion" class="form-textarea">'.e($producto_a_editar['descripcion'] ?? '').'</textarea></div></div>';

    echo "<div class='btn-container'><button type='submit' class='btn btn-primary'><i class='fas fa-save'></i> {$button_text}</button>";
    if ($is_edit) { echo "<a href='?view=productos' class='btn btn-secondary'><i class='fas fa-times'></i> Cancelar Edición</a>"; }
    echo '</div></form></div>';

    echo '<div class="card"><h2><i class="fas fa-list"></i> Lista de Productos</h2><div class="table-responsive"><table class="data-table"><thead><tr><th>Nombre</th><th>Nº de Parte Pred.</th><th>Descripción</th><th class="actions-cell">Acciones</th></tr></thead><tbody>';
    if(empty($productos)) {
        echo '<tr><td colspan="4" style="text-align:center; padding: 2rem; color: var(--color-text-muted);">No hay productos registrados.</td></tr>';
    } else {
        foreach($productos as $p) {
            echo '<tr>';
            echo '<td><strong>'.e($p['nombre']).'</strong></td>';
            echo '<td>'.e($p['part_number'] ?? 'N/A').'</td>';
            echo '<td>'.e($p['descripcion'] ?? 'N/A').'</td>';
            echo '<td class="actions-cell">';
            echo '<a href="?view=productos&edit_id='.e($p['id']).'" class="btn btn-warning" title="Editar"><i class="fas fa-edit"></i></a>';
            echo '<form method="POST" action="?view=productos" style="display:inline;" onsubmit="return confirm(\'¿Estás seguro de eliminar este producto?\');"><input type="hidden" name="action" value="eliminar_producto"><input type="hidden" name="id" value="'.e($p['id']).'"><button type="submit" class="btn btn-danger" title="Eliminar"><i class="fas fa-trash"></i></button></form>';
            echo '</td></tr>';
        }
    }
    echo '</tbody></table></div></div>';
}

function render_footer(): void {
    echo <<<HTML
    </main></div>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
        const invoiceContainer = document.getElementById('invoice-container');
        if (invoiceContainer) {
            const beforePrint = () => {
                invoiceContainer.querySelectorAll('.print-header, .print-parties, .print-totals, .print-notes').forEach(el => el.style.display = el.matches('.print-parties') ? 'grid' : 'block');
                invoiceContainer.querySelectorAll('.print-show').forEach(el => el.style.display = 'none');
            };
            const afterPrint = () => {
                invoiceContainer.querySelectorAll('.print-header, .print-parties, .print-totals, .print-notes').forEach(el => el.style.display = 'none');
                invoiceContainer.querySelectorAll('.print-show').forEach(el => el.style.display = 'block');
                const flexHeader = invoiceContainer.querySelector('.print-show > div[style*="display: flex"]');
                if (flexHeader) flexHeader.style.display = 'flex';
            };
            window.addEventListener('beforeprint', beforePrint);
            window.addEventListener('afterprint', afterPrint);
        }

        document.querySelectorAll('.toggle-btn').forEach(button => {
            button.addEventListener('click', function() {
                const content = document.getElementById(this.getAttribute('data-target'));
                const icon = this.querySelector('i');
                content.classList.toggle('hidden');
                icon.className = content.classList.contains('hidden') ? 'fas fa-plus' : 'fas fa-minus';
            });
        });

        const compraForm = document.getElementById("compra-form");
        if(compraForm) {
            const productLinesContainer = document.getElementById('product-lines-container');
            const addProductBtn = document.getElementById('add-product-btn');
            const productosDatalist = document.getElementById("productos-lista");

            const inicializarListeners = (context) => {
                const productoInput = context.querySelector(".producto-nombre");
                const numeroParteInput = context.querySelector(".part-number-input");
                if (productoInput && numeroParteInput && productosDatalist) {
                    productoInput.addEventListener('input', function(event) {
                        const option = Array.from(productosDatalist.options).find(opt => opt.value === event.target.value);
                        numeroParteInput.value = option ? (option.dataset.partNumber || '') : '';
                    });
                }
                
                const removeBtn = context.querySelector('.remove-product-line');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function() {
                        this.closest('.product-line').remove();
                        calcularTotales();
                    });
                }
            };
            
            if (addProductBtn) {
                addProductBtn.addEventListener('click', () => {
                    const firstLine = productLinesContainer.querySelector('.product-line');
                    if (!firstLine) return;
                    const newLine = firstLine.cloneNode(true);
                    newLine.querySelectorAll('input:not([type=radio]):not([type=checkbox])').forEach(input => input.value = '');
                    newLine.querySelector('input[name="iva_porcentaje[]"]').value = '19';
                    newLine.querySelector('input[name="cantidad[]"]').value = '1';
                    newLine.querySelector('.remove-product-line').style.display = 'inline-flex';
                    productLinesContainer.appendChild(newLine);
                    inicializarListeners(newLine);
                    calcularTotales();
                });
            }

            const formateadorCOP = val => new Intl.NumberFormat("es-CO",{style:"currency",currency:"COP",minimumFractionDigits:0,maximumFractionDigits:0}).format(val);
            
            const calcularTotales = () => {
                let subtotalTotal = 0, ivaTotal = 0, retencionTotal = 0;
                document.querySelectorAll('.product-line').forEach(line => {
                    const cantidad = parseFloat(line.querySelector('input[name="cantidad[]"]').value) || 0;
                    const precio = parseFloat(line.querySelector('input[name="precio_sin_iva[]"]').value) || 0;
                    const ivaPorc = parseFloat(line.querySelector('input[name="iva_porcentaje[]"]').value) || 0;
                    const retencion = parseFloat(line.querySelector('input[name="retefuente[]"]').value) || 0;
                    const subtotalLinea = cantidad * precio;
                    const ivaLinea = subtotalLinea * ivaPorc / 100;
                    subtotalTotal += subtotalLinea;
                    ivaTotal += ivaLinea;
                    retencionTotal += retencion;
                    line.querySelector('input[name="iva[]"]').value = ivaLinea.toFixed(0);
                });
                const costoEnvio = parseFloat(compraForm.querySelector("#costo_envio").value) || 0;
                const totalPagar = subtotalTotal + ivaTotal + costoEnvio - retencionTotal;
                compraForm.querySelector("#summary-subtotal").textContent = formateadorCOP(subtotalTotal);
                compraForm.querySelector("#summary-envio").textContent = formateadorCOP(costoEnvio);
                compraForm.querySelector("#summary-iva").textContent = formateadorCOP(ivaTotal);
                compraForm.querySelector("#summary-retefuente").textContent = formateadorCOP(-retencionTotal);
                compraForm.querySelector("#summary-total").textContent = formateadorCOP(totalPagar);
            };

            const calcularDiferencia = () => {
                const valorFactura = parseFloat(compraForm.querySelector("#valor_factura").value) || 0;
                const valorPagado = parseFloat(compraForm.querySelector("#valor_pagado").value) || 0;
                const diferencia = valorFactura - valorPagado;
                const diffInput = compraForm.querySelector("#diferencia");
                diffInput.value = formateadorCOP(diferencia);
                diffInput.style.fontWeight = "600";
                diffInput.style.color = diferencia > 0 ? "var(--color-danger)" : (diferencia < 0 ? "var(--color-warning)" : "var(--color-secondary)");
            };
            
            const toggleContraentrega = () => {
                const check = compraForm.querySelector("#pago_contraentrega");
                const group = compraForm.querySelector("#valor-contra-entrega-group");
                if (check && group) {
                    group.style.display = check.checked ? 'block' : 'none';
                    if(!check.checked) compraForm.querySelector("#valor_contra_entrega").value = "";
                }
            };

            const actualizarEstadoRecepcion = () => {
                const checkNo = compraForm.querySelector("#producto_no");
                const fechaInput = compraForm.querySelector("#fecha_recibido");
                if(checkNo && fechaInput) {
                    fechaInput.disabled = checkNo.checked;
                    if(checkNo.checked) fechaInput.value = '';
                }
            };
            
            compraForm.addEventListener('input', e => {
                if (e.target.matches('.calc-field')) calcularTotales();
                if (e.target.matches('.diff-field')) calcularDiferencia();
            });
            compraForm.addEventListener('change', e => {
                if (e.target.matches('#pago_contraentrega')) toggleContraentrega();
                if (e.target.matches('input[name="producto_recibido"]')) actualizarEstadoRecepcion();
            });

            document.querySelectorAll('.product-line').forEach(inicializarListeners);
            calcularTotales();
            calcularDiferencia();
            toggleContraentrega();
            actualizarEstadoRecepcion();
            
            compraForm.addEventListener("submit", () => {
                const loader=document.getElementById("loader");
                if(loader) loader.style.display="block";
            });
        }
        
        const cards=document.querySelectorAll(".metric-card");
        cards.forEach((e,n)=>{
            e.style.opacity="0"; e.style.transform="translateY(20px)";
            setTimeout(() => { e.style.transition="all 0.6s ease"; e.style.opacity="1"; e.style.transform="translateY(0)"; }, 100 * n)
        });
    });
    </script>
    </body></html>
HTML;
}

//==============================================================================
// PARTE 3: CONTROLADOR PRINCIPAL Y ENRUTADOR
//==============================================================================

try {
    $db = conectarDB();
    
    $productManager   = new ProductManager($db);
    $proveedorManager = new ProveedorManager($db);
    $comprasManager   = new ComprasManager($db, $productManager); 

    $action = $_POST['action'] ?? null;
    $view = $_GET['view'] ?? 'dashboard';
    $redirect_view = $view;

    if ($action) {
        try {
            $db->beginTransaction();
            switch ($action) {
                case 'guardar_compra':
                    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
                    $comprasManager->guardar($_POST, $_FILES, $id);
                    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => $id ? 'Compra actualizada exitosamente.' : 'Compra registrada exitosamente.'];
                    $redirect_view = 'compras_historico';
                    break;
                case 'eliminar_compra':
                    $comprasManager->eliminar((int)$_POST['id']);
                    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Compra eliminada exitosamente.'];
                    $redirect_view = 'compras_historico';
                    break;
                case 'guardar_proveedor':
                    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
                    $proveedorManager->guardar($_POST, $id);
                    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => $id ? 'Proveedor actualizado exitosamente.' : 'Proveedor registrado exitosamente.'];
                    $redirect_view = 'proveedores';
                    break;
                case 'eliminar_proveedor':
                    $proveedorManager->eliminar((int)$_POST['id']);
                    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Proveedor eliminado exitosamente.'];
                    $redirect_view = 'proveedores';
                    break;
                case 'guardar_producto':
                    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
                    $productManager->guardar($_POST, $id);
                    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => $id ? 'Producto actualizado.' : 'Producto registrado.'];
                    $redirect_view = 'productos';
                    break;
                case 'eliminar_producto':
                    $productManager->eliminar((int)$_POST['id']);
                    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Producto eliminado.'];
                    $redirect_view = 'productos';
                    break;
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => $e->getMessage()];
        }
        header("Location: ?view=" . $redirect_view);
        exit;
    }

    $mensaje = $_SESSION['mensaje'] ?? null;
    unset($_SESSION['mensaje']);
    
    render_header('Sistema de Gestión');
    echo '<div class="main-wrapper">';
    render_navigation($view);
    render_mensaje($mensaje);
    
    switch ($view) {
        case 'compras_nueva':
            $edit_id = (int)($_GET['edit_id'] ?? 0);
            $compra_a_editar = $edit_id ? $comprasManager->getById($edit_id) : null;
            $proveedores = $proveedorManager->getAll();
            $productos = $productManager->getAll();
            render_compra_form_view($proveedores, $compra_a_editar, $productos);
            break;
        case 'compras_historico':
            $filter = $_GET['filter'] ?? null;
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            $compras = $comprasManager->getAll($filter, $startDate, $endDate);
            render_compras_historico_view($compras, $startDate, $endDate);
            break;
        case 'proveedores':
            $edit_id = (int)($_GET['edit_id'] ?? 0);
            $proveedor_a_editar = $edit_id ? $proveedorManager->getById($edit_id) : null;
            $proveedores = $proveedorManager->getAll();
            render_proveedores_view($proveedores, $proveedor_a_editar);
            break;
        case 'productos':
            $edit_id = (int)($_GET['edit_id'] ?? 0);
            $producto_a_editar = $edit_id ? $productManager->getById($edit_id) : null;
            $productos = $productManager->getAll();
            render_productos_view($productos, $producto_a_editar);
            break;
        case 'proveedor_detalle':
            $id = (int)($_GET['id'] ?? 0);
            $proveedor = $id ? $proveedorManager->getById($id) : null;
            $compras_proveedor = $id ? $comprasManager->getByProveedorId($id) : [];
            render_proveedor_detalle_view($proveedor, $compras_proveedor);
            break;
        case 'compra_detalle':
            $id = (int)($_GET['id'] ?? 0);
            $compra = $id ? $comprasManager->getById($id) : null;
            render_compra_detalle_view($compra);
            break;
        case 'inventario_detalle':
            $producto = (string)($_GET['producto'] ?? '');
            if (empty($producto)) {
                header("Location: ?view=dashboard");
                exit;
            }
            $historial = $comprasManager->getPurchaseHistoryByProduct($producto);
            render_inventario_detalle_view($producto, $historial);
            break;
        case 'dashboard':
        default:
            $metrics = $comprasManager->getDashboardMetrics();
            $inventory = $comprasManager->getInventorySummary();
            $topProveedores = $comprasManager->getTopProveedores();
            render_dashboard_view($metrics, $inventory, $topProveedores);
            break;
    }
    render_footer();
} catch (Exception $e) {
    error_log("Error crítico en el módulo de compras: " . $e->getMessage());
    die("<div style='padding: 2rem; text-align: center; font-family: Arial, sans-serif; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; margin: 2rem;'><h1 style='color: #dc2626;'>Error del Sistema</h1><p style='color: #6b7280; font-size: 1.1rem;'>Ha ocurrido un error inesperado.</p><p style='background-color: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 0.5rem; margin-top: 1.5rem; text-align: left; font-family: monospace;'><strong>Detalle:</strong> ".e($e->getMessage())."</p><p style='font-size: 0.875rem; color: #9ca3af; margin-top: 2rem;'>Contacte al administrador. Código de error: COMP_".date('YmdHis')."</p></div>");
}
?>