<?php
/*
================================================================================
| MÓDULO PROFESIONAL DE GESTIÓN DE COTIZACIONES                               |
| Versión: 2.1 - Integración con Base de Datos y Firma Digitalizada          |
| Descripción: Sistema para generar cotizaciones profesionales, obteniendo   |
|              la lista de proveedores e incluyendo una firma de imagen.     |
================================================================================
*/

// Iniciar sesión para manejar datos temporales
session_start();

// Incluir la configuración de la base de datos para acceder a los proveedores
require_once 'config.php';

//==============================================================================
// CLASES Y FUNCIONES DE UTILIDAD
//==============================================================================

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
}

function e(?string $string): string { 
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8'); 
}

function format_cop(float $number): string {
    return '$ ' . number_format($number, 0, ',', '.');
}

function generar_numero_cotizacion(): string {
    return 'COT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

//==============================================================================
// FUNCIONES DE RENDERIZADO
//==============================================================================

function render_header(string $titulo): void {
    echo <<<HTML
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{$titulo}</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><style>:root{--color-primary:#4f46e5;--color-primary-light:#6366f1;--color-secondary:#10b981;--color-danger:#ef4444;--color-warning:#f59e0b;--color-info:#3b82f6;--color-bg:#f8fafc;--color-bg-card:#fff;--color-text:#111827;--color-text-muted:#6b7280;--color-border:#e2e8f0;--shadow:0 4px 6px -1px rgb(0 0 0 / .1), 0 2px 4px -2px rgb(0 0 0 / .1);--shadow-lg:0 10px 15px -3px rgb(0 0 0 / .1), 0 4px 6px -4px rgb(0 0 0 / .1);--border-radius:.75rem}*,::before,::after{box-sizing:border-box}body{margin:0;font-family:'Inter',sans-serif;background-color:var(--color-bg);color:var(--color-text);font-size:16px;line-height:1.6}.main-wrapper{display:flex;min-height:100vh}.sidebar{width:280px;background-color:var(--color-bg-card);padding:2rem 1.5rem;box-shadow:var(--shadow-lg);position:sticky;top:0;height:100vh;overflow-y:auto}.sidebar h2{font-size:1.5rem;text-align:center;margin:0 0 2.5rem;color:var(--color-primary);font-weight:700}.sidebar h2 .fa-file-invoice{margin-right:.5rem}.nav-menu a{display:flex;align-items:center;gap:.75rem;padding:1rem 1.25rem;border-radius:.75rem;color:var(--color-text-muted);text-decoration:none;font-weight:600;margin-bottom:.5rem;transition:all .3s ease}.nav-menu a:hover{background-color:var(--color-bg);color:var(--color-text);transform:translateX(4px)}.nav-menu a.active{background-color:var(--color-primary);color:#fff;box-shadow:var(--shadow)}.content-wrapper{flex-grow:1;padding:2rem;max-width:calc(100% - 280px)}h1,h2{font-weight:700;line-height:1.2}h1{font-size:2.5rem;color:var(--color-primary)}.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem}h2{font-size:1.75rem;margin-bottom:1.5rem;padding-bottom:.75rem;border-bottom:2px solid var(--color-border);display:flex;align-items:center;gap:.75rem}.alert{padding:1.25rem 1.5rem;margin-bottom:2rem;border-radius:var(--border-radius);display:flex;align-items:center;gap:.75rem;font-weight:500;box-shadow:var(--shadow)}.alert-success{background-color:#d1fae5;color:#065f46;border-left:4px solid var(--color-secondary)}.alert-error{background-color:#fee2e2;color:#991b1b;border-left:4px solid var(--color-danger)}.card{background-color:var(--color-bg-card);border-radius:var(--border-radius);box-shadow:var(--shadow);padding:2.5rem;margin-bottom:2rem;border:1px solid var(--color-border)}fieldset{border:2px solid var(--color-border);border-radius:var(--border-radius);padding:2rem;margin-bottom:2rem}legend{font-weight:600;font-size:1.1rem;padding:0 1rem;color:var(--color-primary)}.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem;margin-bottom:1.5rem}.form-group{display:flex;flex-direction:column}.form-label{font-weight:600;margin-bottom:.5rem;font-size:.95rem;color:var(--color-text)}.form-label .required{color:var(--color-danger)}.form-input,.form-select,.form-textarea{width:100%;padding:.875rem 1rem;border:2px solid var(--color-border);border-radius:.5rem;font-size:1rem;font-family:inherit;transition:all .3s ease;background-color:var(--color-bg-card)}.form-input:focus,.form-select:focus,.form-textarea:focus{outline:0;border-color:var(--color-primary);box-shadow:0 0 0 3px rgba(79,70,229,.1)}.form-input[readonly]{background-color:var(--color-bg);color:var(--color-text-muted)}.form-textarea{min-height:100px;resize:vertical}.btn-container{display:flex;justify-content:flex-end;gap:1rem;margin-top:2.5rem;padding-top:2rem;border-top:2px solid var(--color-border)}.btn{padding:.875rem 2rem;border:none;border-radius:.75rem;font-weight:600;font-size:1rem;cursor:pointer;transition:all .3s ease;display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;line-height:1.5;box-shadow:var(--shadow)}.btn-primary{background-color:var(--color-primary);color:#fff}.btn-primary:hover{background-color:var(--color-primary-light);transform:translateY(-2px);box-shadow:var(--shadow-lg)}.btn-secondary{background-color:var(--color-text-muted);color:#fff}.btn-secondary:hover{background-color:#4b5563;transform:translateY(-2px)}.btn-info{background-color:var(--color-info);color:#fff}.btn-info:hover{background-color:#2563eb;transform:translateY(-2px)}.btn-success{background-color:var(--color-secondary);color:#fff}.btn-success:hover{background-color:#059669;transform:translateY(-2px)}.table-responsive{overflow-x:auto;border-radius:var(--border-radius);box-shadow:var(--shadow)}.data-table{width:100%;border-collapse:collapse;margin-top:1rem;background-color:var(--color-bg-card)}.data-table th,.data-table td{padding:1rem 1.25rem;text-align:left;border-bottom:1px solid var(--color-border);vertical-align:middle}.data-table th{background-color:var(--color-bg);font-weight:700;color:var(--color-text);text-transform:uppercase;font-size:.875rem;letter-spacing:.05em}.data-table tbody tr:hover{background-color:#f1f5f9}.summary{background:linear-gradient(135deg,var(--color-bg) 0%,#e2e8f0 100%);border-radius:var(--border-radius);padding:2rem;margin-top:2rem;border:2px solid var(--color-border)}.summary h3{margin-top:0;color:var(--color-primary);font-size:1.25rem}.summary-row{display:flex;justify-content:space-between;margin-bottom:.75rem;padding:.5rem 0}.summary-row.total{font-weight:700;font-size:1.3rem;border-top:3px solid var(--color-primary);padding-top:1rem;margin-top:1rem;color:var(--color-primary)}.summary-value{font-weight:600}.invoice-container{background:#fff;padding:3rem;margin:2rem 0;border:1px solid var(--color-border);border-radius:var(--border-radius)}.invoice-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:3rem;border-bottom:3px solid var(--color-primary);padding-bottom:2rem}.company-info{flex:1}.company-logo{font-size:2.5rem;font-weight:800;color:var(--color-primary);margin-bottom:1rem}.quote-info{text-align:right;flex:1}.quote-title{font-size:2rem;font-weight:700;color:var(--color-text);margin-bottom:1rem}.quote-details{font-size:1.1rem;color:var(--color-text-muted)}.client-supplier-section{display:grid;grid-template-columns:1fr 1fr;gap:3rem;margin-bottom:3rem}.section-title{font-size:1.2rem;font-weight:600;color:var(--color-primary);margin-bottom:1rem;text-transform:uppercase;border-bottom:2px solid var(--color-border);padding-bottom:.5rem}.items-table{width:100%;border-collapse:collapse;margin:2rem 0}.items-table th{background-color:#f8fafc;padding:1rem;text-align:center;font-weight:600;border:2px solid var(--color-border);text-transform:uppercase;font-size:.9rem}.items-table td{padding:1rem;text-align:center;border:2px solid var(--color-border);vertical-align:middle}.items-table .desc-cell{text-align:left;font-weight:500}.totals-section{display:flex;justify-content:flex-end;margin-top:2rem}.totals-table{width:400px}.totals-table td{padding:.75rem 1rem;border-bottom:1px solid var(--color-border)}.totals-table .total-row{font-size:1.2rem;font-weight:700;background-color:#f8fafc;border-top:3px solid var(--color-primary)}.footer-section{margin-top:3rem;padding-top:2rem;border-top:2px solid var(--color-border)}.company-footer{text-align:center;margin-top:2rem;font-size:.9rem;color:var(--color-text-muted)}@media print{body *{visibility:hidden}.invoice-container,.invoice-container *{visibility:visible}.sidebar,.page-header,.btn-container{display:none!important}.invoice-container{position:absolute;left:0;top:0;width:100%;margin:0;padding:1rem;border:none;box-shadow:none}}@media (max-width:1024px){.main-wrapper{flex-direction:column}.sidebar{width:100%;height:auto;position:static}.content-wrapper{max-width:100%;padding:1.5rem}.client-supplier-section{grid-template-columns:1fr}}@media (max-width:768px){.invoice-header{flex-direction:column;gap:2rem}.totals-section{justify-content:center}}.item-row-template{display:none}</style></head><body>
HTML;
}

function render_navigation(): void {
    echo <<<HTML
    <aside class="sidebar">
        <h2><i class="fas fa-file-invoice"></i> Sistema de Cotizaciones</h2>
        <nav class="nav-menu">
            <a href="?view=nueva" class="active">
                <i class="fas fa-plus-circle"></i>
                <span>Nueva Cotización</span>
            </a>
            <a href="inventario_compras.php">
                <i class="fas fa-arrow-left"></i>
                <span>Volver al Sistema Principal</span>
            </a>
        </nav>
    </aside>
    <main class="content-wrapper">
HTML;
}

function render_mensaje(?array $mensaje): void {
    if (!$mensaje) return;
    $tipo = e($mensaje['tipo']);
    $texto = e($mensaje['texto']);
    $icono = $tipo === 'success' ? 'check-circle' : 'exclamation-triangle';
    echo "<div class='alert alert-{$tipo}'><i class='fas fa-{$icono}'></i> {$texto}</div>";
}

function render_formulario_cotizacion(array $proveedores): void {
    echo <<<HTML
    <div class="page-header">
        <h1><i class="fas fa-file-invoice"></i> Generar Cotización</h1>
    </div>
    <div class="card">
        <h2><i class="fas fa-edit"></i> Datos de la Cotización</h2>
        <form id="cotizacion-form" method="POST">
            <input type="hidden" name="action" value="generar_cotizacion">
            <fieldset>
                <legend><i class="fas fa-building"></i> Información de la Empresa</legend>
                <div class="form-grid">
                    <div class="form-group"><label for="empresa_nombre" class="form-label">Nombre de la Empresa <span class="required">*</span></label><input type="text" name="empresa_nombre" id="empresa_nombre" class="form-input" value="Rinku Soluciones S.A.S" required></div>
                    <div class="form-group"><label for="empresa_nit" class="form-label">NIT <span class="required">*</span></label><input type="text" name="empresa_nit" id="empresa_nit" class="form-input" value="900.644.423-0" required></div>
                    <div class="form-group"><label for="empresa_telefono" class="form-label">Teléfono</label><input type="text" name="empresa_telefono" id="empresa_telefono" class="form-input" value="+57 310 377 50 13"></div>
                    <div class="form-group"><label for="empresa_email" class="form-label">Email de Facturación</label><input type="email" name="empresa_email" id="empresa_email" class="form-input" value="factura@rinkusoluciones.com"></div>
                </div>
                <div class="form-grid" style="grid-template-columns: 1fr;"><div class="form-group"><label for="empresa_direccion" class="form-label">Dirección</label><textarea name="empresa_direccion" id="empresa_direccion" class="form-textarea">Calle 10 #52A-18 Bodega 111, Medellín</textarea></div></div>
            </fieldset>
            <fieldset>
                <legend><i class="fas fa-user-tie"></i> Información del Cliente/Proveedor</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="proveedor_nombre" class="form-label">Nombre del Proveedor <span class="required">*</span></label>
                        <select name="proveedor_nombre" id="proveedor_nombre" class="form-select" required><option value="">-- Seleccione un Proveedor --</option>
HTML;
    foreach ($proveedores as $proveedor) {
        echo '<option value="' . e($proveedor['nombre']) . '">' . e($proveedor['nombre']) . '</option>';
    }
    echo <<<HTML
                        </select>
                    </div>
                    <div class="form-group"><label for="proveedor_contacto" class="form-label">Contacto</label><input type="text" name="proveedor_contacto" id="proveedor_contacto" class="form-input" placeholder="Ej: César León"></div>
                    <div class="form-group"><label for="fecha_despacho" class="form-label">Fecha de Despacho</label><input type="date" name="fecha_despacho" id="fecha_despacho" class="form-input" value=""></div>
                    <div class="form-group"><label for="forma_pago" class="form-label">Forma de Pago</label><select name="forma_pago" id="forma_pago" class="form-select"><option value="Contado">Contado</option><option value="Contado, Transf Bancaria" selected>Contado, Transf Bancaria</option><option value="Crédito 15 días">Crédito 15 días</option><option value="Crédito 30 días">Crédito 30 días</option></select></div>
                </div>
                <div class="form-grid" style="grid-template-columns: 1fr;"><div class="form-group"><label for="direccion_envio" class="form-label">Dirección de Envío <span class="required">*</span></label><input type="text" name="direccion_envio" id="direccion_envio" class="form-input" value="Calle 10 #52A-18 Bodega 111, Medellín" required></div></div>
            </fieldset>
            <fieldset>
                <legend><i class="fas fa-boxes"></i> Productos/Servicios</legend>
                <div id="items-container">
                    <div class="item-row" data-item="1"><div class="form-grid" style="grid-template-columns: 80px 120px 2fr 120px 120px 150px 150px 80px; gap: 1rem; align-items: end; margin-bottom: 1rem; padding: 1rem; border: 1px solid var(--color-border); border-radius: 0.5rem;"><div class="form-group"><label class="form-label">Cotización</label><input type="text" name="cotizacion[]" class="form-input" placeholder="8465"></div><div class="form-group"><label class="form-label">Parte</label><input type="text" name="parte[]" class="form-input" placeholder="14300029"></div><div class="form-group"><label class="form-label">Descripción</label><input type="text" name="descripcion[]" class="form-input" placeholder="TENSOR DE ABONADO FIGURA 8..."></div><div class="form-group"><label class="form-label">Cantidad</label><input type="number" name="cantidad[]" class="form-input calc-field" min="1" value="1"></div><div class="form-group"><label class="form-label">Unitario</label><input type="number" name="unitario[]" class="form-input calc-field" step="1" min="0" placeholder="400"></div><div class="form-group"><label class="form-label">Total antes IVA</label><input type="number" name="total_antes_iva[]" class="form-input total-antes-iva" step="1" min="0" readonly></div><div class="form-group"><label class="form-label">Moneda</label><select name="moneda[]" class="form-select"><option value="COP" selected>COP</option><option value="USD">USD</option><option value="EUR">EUR</option></select></div><div class="form-group"><button type="button" class="btn btn-danger btn-sm remove-item" onclick="removeItem(this)" style="padding: 0.5rem;"><i class="fas fa-trash"></i></button></div></div></div>
                </div>
                <div style="margin-bottom: 2rem;"><button type="button" id="add-item" class="btn btn-secondary"><i class="fas fa-plus"></i> Agregar Producto</button></div>
                <div class="summary">
                    <h3><i class="fas fa-calculator"></i> Resumen de Totales</h3>
                    <div class="summary-row"><span>Subtotal:</span><span class="summary-value" id="summary-subtotal">$ 0</span></div>
                    <div class="summary-row total"><span>Total antes de IVA:</span><span class="summary-value" id="summary-total">$ 0</span></div>
                </div>
            </fieldset>
            <fieldset>
                <legend><i class="fas fa-user-edit"></i> Información del Representante</legend>
                <div class="form-grid">
                    <div class="form-group"><label for="representante_nombre" class="form-label">Nombre del Representante <span class="required">*</span></label><input type="text" name="representante_nombre" id="representante_nombre" class="form-input" value="Raiger Murillo" required></div>
                    <div class="form-group"><label for="representante_cc" class="form-label">C.C.</label><input type="text" name="representante_cc" id="representante_cc" class="form-input" value="71.748.834"></div>
                    <div class="form-group"><label for="representante_cargo" class="form-label">Cargo</label><input type="text" name="representante_cargo" id="representante_cargo" class="form-input" value="Representante Legal"></div>
                    <div class="form-group"><label for="representante_telefono" class="form-label">Teléfono</label><input type="text" name="representante_telefono" id="representante_telefono" class="form-input" value="+57 320 211 1075"></div>
                </div>
            </fieldset>
            <fieldset>
                <legend><i class="fas fa-info-circle"></i> Información Adicional</legend>
                <div class="form-group"><label for="horario_recepcion" class="form-label">Horario de Recepción</label><input type="text" name="horario_recepcion" id="horario_recepcion" class="form-input" value="Lunes a viernes 7AM a 5PM."></div>
                <div class="form-group"><label for="notas_adicionales" class="form-label">Notas Adicionales</label><textarea name="notas_adicionales" id="notas_adicionales" class="form-textarea" placeholder="Información adicional sobre la cotización..."></textarea></div>
            </fieldset>
            <div class="btn-container">
                <button type="reset" class="btn btn-secondary"><i class="fas fa-undo"></i> Limpiar Formulario</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-file-invoice"></i> Generar Cotización</button>
            </div>
        </form>
    </div>
HTML;
}

// ✅ MODIFICADO: Esta es la única función que ha cambiado en este paso.
function render_cotizacion_generada(array $data): void {
    $numero_cotizacion = generar_numero_cotizacion();
    $fecha_actual = date('d-M-Y');
    
    echo <<<HTML
    <div class="page-header">
        <h1><i class="fas fa-file-invoice"></i> Cotización Generada</h1>
        <div><button onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> Imprimir</button><a href="?" class="btn btn-secondary"><i class="fas fa-plus"></i> Nueva Cotización</a></div>
    </div>
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-info"><div class="company-logo">RINKU SOLUCIONES</div></div>
            <div class="quote-info"><div class="quote-title">ORDEN DE COMPRA</div></div>
        </div>
        <div class="client-supplier-section">
            <div>
                <div class="section-title">PROVEEDOR</div>
                <p><strong>Orden de Compra:</strong> {$numero_cotizacion}</p>
                <p><strong>Fecha:</strong> {$fecha_actual}</p>
                <p><strong>Proveedor:</strong> {$data['proveedor_nombre']}</p>
HTML;
    if (!empty($data['proveedor_contacto'])) {
        echo "<p><strong>Contacto:</strong> {$data['proveedor_contacto']}</p>";
    }
    echo <<<HTML
                <p><strong>Forma de Pago:</strong> {$data['forma_pago']}</p>
            </div>
            <div>
                <div class="section-title">COMPRADOR</div>
                <p><strong>{$data['empresa_nombre']}</strong></p>
                <p><strong>NIT:</strong> {$data['empresa_nit']}</p>
                <p><strong>Tel:</strong> {$data['empresa_telefono']}</p>
                <p style="color: #ef4444; font-weight: 600;"><strong>Dirección Envío:</strong> {$data['direccion_envio']}</p>
HTML;
    if (!empty($data['fecha_despacho'])) {
        echo "<p><strong>Fecha Despacho:</strong> " . date('d-M-Y', strtotime($data['fecha_despacho'])) . "</p>";
    }
    echo <<<HTML
                <p><strong>Facturación:</strong> {$data['empresa_email']}</p>
            </div>
        </div>
        <table class="items-table">
            <thead><tr><th>Cotización</th><th>Parte/Numero</th><th>Descripción</th><th>Cantidad</th><th>Unitario</th><th>Total antes IVA</th><th>Moneda</th></tr></thead>
            <tbody>
HTML;
    $subtotal = 0;
    for ($i = 0; $i < count($data['descripcion']); $i++) {
        $cantidad = (float)($data['cantidad'][$i] ?? 0);
        $unitario = (float)($data['unitario'][$i] ?? 0);
        $total_item = $cantidad * $unitario;
        $subtotal += $total_item;
        echo "<tr><td>" . e($data['cotizacion'][$i] ?? '') . "</td><td>" . e($data['parte'][$i] ?? '') . "</td><td class='desc-cell'>" . e($data['descripcion'][$i] ?? '') . "</td><td>" . number_format($cantidad, 0) . "</td><td>" . format_cop($unitario) . "</td><td>" . format_cop($total_item) . "</td><td>" . e($data['moneda'][$i] ?? 'COP') . "</td></tr>";
    }
    $filas_minimas = 2;
    if (count($data['descripcion']) < $filas_minimas) {
        for ($j = count($data['descripcion']); $j < $filas_minimas; $j++) {
             echo "<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
        }
    }
    echo <<<HTML
            </tbody>
        </table>
        <div class="totals-section">
            <table class="totals-table">
                <tr><td>SUBTOTAL</td><td>{$subtotal}</td></tr>
                <tr class="total-row"><td>TOTAL ANTES DE IVA</td><td>{$subtotal}</td></tr>
            </table>
        </div>
        <div class="footer-section">
            <p><strong>Cordialmente,</strong></p>
            
            <!-- ✅ INICIO DEL CAMBIO: SECCIÓN DE LA FIRMA -->
            <div style="margin-top: 1rem; width: 250px;">
                <img src="firma.png" alt="Firma del Representante" style="width: 100%; height: auto; display: block;">
                <div style="border-top: 2px solid var(--color-text); margin-top: 5px;"></div>
            </div>
            <!-- ✅ FIN DEL CAMBIO -->

            <p style="margin-top: 1rem;"><strong>{$data['representante_nombre']}</strong></p>
            <p><strong>C.C. {$data['representante_cc']}</strong></p>
            <p><strong>{$data['representante_cargo']}</strong></p>
            <p><strong>Cel:</strong> {$data['representante_telefono']}</strong></p>
            <p style="margin-top: 2rem;">{$data['horario_recepcion']}</p>
        </div>
        <div class="company-footer">
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-primary);">RINKU SOLUCIONES</div>
            <p>{$data['empresa_direccion']}</p>
            <p>www.rinkusoluciones.com - info@rinkusoluciones.com</p>
        </div>
    </div>
HTML;
}

function render_footer(): void {
    echo <<<HTML
    </main></div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('cotizacion-form');
        if (!form) return;
        let itemCounter = 1;
        function calcularTotales() {
            let subtotal = 0;
            const itemRows = document.querySelectorAll('.item-row');
            itemRows.forEach(row => {
                const cantidad = parseFloat(row.querySelector('input[name="cantidad[]"]').value) || 0;
                const unitario = parseFloat(row.querySelector('input[name="unitario[]"]').value) || 0;
                const total = cantidad * unitario;
                row.querySelector('.total-antes-iva').value = total.toFixed(0);
                subtotal += total;
            });
            document.getElementById('summary-subtotal').textContent = formatCOP(subtotal);
            document.getElementById('summary-total').textContent = formatCOP(subtotal);
        }
        function formatCOP(number) {
            return '$ ' + new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(number);
        }
        function attachCalculationEvents(row) {
            const calcFields = row.querySelectorAll('.calc-field');
            calcFields.forEach(field => { field.addEventListener('input', calcularTotales); });
        }
        function addItemRow() {
            itemCounter++;
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.setAttribute('data-item', itemCounter);
            newRow.innerHTML = `<div class="form-grid" style="grid-template-columns: 80px 120px 2fr 120px 120px 150px 150px 80px; gap: 1rem; align-items: end; margin-bottom: 1rem; padding: 1rem; border: 1px solid var(--color-border); border-radius: 0.5rem;"><div class="form-group"><label class="form-label">Cotización</label><input type="text" name="cotizacion[]" class="form-input" placeholder=""></div><div class="form-group"><label class="form-label">Parte</label><input type="text" name="parte[]" class="form-input" placeholder=""></div><div class="form-group"><label class="form-label">Descripción</label><input type="text" name="descripcion[]" class="form-input" placeholder="Descripción del producto..."></div><div class="form-group"><label class="form-label">Cantidad</label><input type="number" name="cantidad[]" class="form-input calc-field" min="1" value="1"></div><div class="form-group"><label class="form-label">Unitario</label><input type="number" name="unitario[]" class="form-input calc-field" step="1" min="0" placeholder="0"></div><div class="form-group"><label class="form-label">Total antes IVA</label><input type="number" name="total_antes_iva[]" class="form-input total-antes-iva" step="1" min="0" readonly></div><div class="form-group"><label class="form-label">Moneda</label><select name="moneda[]" class="form-select"><option value="COP" selected>COP</option><option value="USD">USD</option><option value="EUR">EUR</option></select></div><div class="form-group"><button type="button" class="btn btn-danger btn-sm remove-item" onclick="removeItem(this)" style="padding: 0.5rem;"><i class="fas fa-trash"></i></button></div></div>`;
            container.appendChild(newRow);
            attachCalculationEvents(newRow);
        }
        window.removeItem = function(button) {
            const itemRows = document.querySelectorAll('.item-row');
            if (itemRows.length > 1) {
                button.closest('.item-row').remove();
                calcularTotales();
            } else {
                alert('Debe mantener al menos un producto en la cotización.');
            }
        }
        document.getElementById('add-item').addEventListener('click', addItemRow);
        attachCalculationEvents(document.querySelector('.item-row'));
        calcularTotales();
        const fechaDespacho = document.getElementById('fecha_despacho');
        if (fechaDespacho) {
            const today = new Date();
            today.setDate(today.getDate() + 3);
            fechaDespacho.value = today.toISOString().split('T')[0];
        }
    });
    </script>
    </body></html>
HTML;
}

//==============================================================================
// CONTROLADOR PRINCIPAL
//==============================================================================

$action = $_POST['action'] ?? null;
$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);

if ($action === 'generar_cotizacion') {
    if (empty($_POST['empresa_nombre']) || empty($_POST['proveedor_nombre']) || empty($_POST['direccion_envio'])) {
        $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'Por favor complete todos los campos obligatorios.'];
        header('Location: ?');
        exit;
    }
    if (empty($_POST['descripcion']) || !is_array($_POST['descripcion'])) {
        $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'Debe agregar al menos un producto a la cotización.'];
        header('Location: ?');
        exit;
    }
    $productos_limpios = [];
    for ($i = 0; $i < count($_POST['descripcion']); $i++) {
        if (!empty($_POST['descripcion'][$i]) && isset($_POST['cantidad'][$i])) {
            $productos_limpios[] = $i;
        }
    }
    if (empty($productos_limpios)) {
        $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'Debe agregar al menos un producto válido con descripción y cantidad.'];
        header('Location: ?');
        exit;
    }
    $data = [];
    foreach ($_POST as $key => $value) {
        if (is_array($value)) {
            $data[$key] = array_values(array_intersect_key($value, array_flip($productos_limpios)));
        } else {
            $data[$key] = $value;
        }
    }
    render_header('Cotización Generada - Sistema de Cotizaciones');
    echo '<div class="main-wrapper">';
    render_navigation();
    render_mensaje($mensaje);
    render_cotizacion_generada($data);
    render_footer();
} else {
    $proveedores = [];
    try {
        $db = conectarDB();
        $proveedorManager = new ProveedorManager($db);
        $proveedores = $proveedorManager->getAll();
    } catch (Exception $e) {
        $mensaje = ['tipo' => 'error', 'texto' => 'No se pudieron cargar los proveedores. Error: ' . $e->getMessage()];
    }
    render_header('Nueva Cotización - Sistema de Cotizaciones');
    echo '<div class="main-wrapper">';
    render_navigation();
    render_mensaje($mensaje);
    render_formulario_cotizacion($proveedores);
    render_footer();
}
?>