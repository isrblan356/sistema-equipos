<?php
require_once 'config.php';
verificarLogin();

// Array de iconos para facilitar la selección al usuario
$iconos_sugeridos = ['fa-camera', 'fa-video', 'fa-microphone', 'fa-tablet-alt', 'fa-hdd', 'fa-server', 'fa-keyboard', 'fa-mouse', 'fa-projector', 'fa-network-wired', 'fa-drone', 'fa-satellite-dish'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creador de Vistas de Hardware</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary-color: #16a34a; --secondary-color: #22c55e; --text-color: #334155; --bg-color: #f1f5f9; --card-bg: white; --shadow: 0 10px 30px rgba(0,0,0,0.08); --border-radius: 15px; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .container { max-width: 1200px; margin: 2rem auto; }
        .card { background: var(--card-bg); border-radius: var(--border-radius); padding: 2.5rem; box-shadow: var(--shadow); margin-bottom: 2rem; }
        .page-header { text-align: center; margin-bottom: 2.5rem; }
        .page-header h1 { font-size: 2.8rem; font-weight: 700; }
        .page-header p { font-size: 1.1rem; color: #64748b; }
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; border: none; border-radius: 25px; font-weight: 600; padding: 12px 24px; }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { font-weight: 600; }
        .campo-dinamico { display: flex; gap: 1rem; align-items: center; background: #f8fafc; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
        .campo-dinamico input { background: white; }
        .form-check-label { font-weight: normal; }
        .output-code { background: #1e293b; color: #e2e8f0; font-family: 'Courier New', Courier, monospace; padding: 1.5rem; border-radius: 10px; white-space: pre-wrap; position: relative; }
        .copy-btn { position: absolute; top: 1rem; right: 1rem; background: #334155; border: none; color: white; padding: 5px 10px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-magic"></i> Generador de Vistas de Hardware</h1>
            <p>Define tu nuevo tipo de hardware y genera el código necesario para integrarlo en el sistema.</p>
            <a href="portatiles.php" class="btn btn-secondary mt-2"><i class="fas fa-arrow-left"></i> Volver al Inventario</a>
        </div>

        <div class="card">
            <form id="generadorForm">
                <h3>1. Define el Tipo de Hardware</h3>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="nombre_singular" class="form-label">Nombre en Singular *</label>
                            <input type="text" id="nombre_singular" class="form-control" placeholder="Ej: Proyector" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="nombre_plural" class="form-label">Nombre en Plural *</label>
                            <input type="text" id="nombre_plural" class="form-control" placeholder="Ej: Proyectores" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="icono" class="form-label">Icono (FontAwesome) *</label>
                            <input type="text" id="icono" list="icon-list" class="form-control" placeholder="Ej: fas fa-video" required>
                            <datalist id="icon-list">
                                <?php foreach($iconos_sugeridos as $icon) echo "<option value='fas $icon'></option>"; ?>
                            </datalist>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h3>2. Define los Campos Adicionales del Formulario</h3>
                <p class="text-muted">Los campos como `nombre_equipo`, `marca`, `modelo`, `usuario_asignado`, `numero_serie`, `estado` y `notas` se agregarán automáticamente.</p>
                <div id="campos-container">
                    <!-- Los campos dinámicos se insertarán aquí -->
                </div>
                <button type="button" id="add-campo" class="btn btn-outline-primary mt-2"><i class="fas fa-plus"></i> Agregar Campo Adicional</button>

                <hr class="my-4">

                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-cogs"></i> Generar Código</button>
                </div>
            </form>
        </div>

        <div id="resultado" class="d-none">
             <div class="card">
                <h3>3. Código Generado</h3>
                <p>¡Listo! Ahora solo sigue estos dos pasos para completar la integración:</p>
                
                <h4 class="mt-4">Paso 1: Crear la tabla en la Base de Datos</h4>
                <p>Copia el siguiente código SQL y ejecútalo en tu gestor de base de datos (por ejemplo, en la pestaña "SQL" de phpMyAdmin).</p>
                <div class="output-code">
                    <button class="copy-btn" onclick="copyToClipboard('sql-code')">Copiar</button>
                    <code id="sql-code"></code>
                </div>

                <h4 class="mt-4">Paso 2: Actualizar el archivo de configuración</h4>
                <p>Copia el siguiente bloque de código PHP y pégalo al final, dentro del array principal en tu archivo <strong><code>hardware_config.php</code></strong> (justo antes del último <code>];</code>).</p>
                <div class="output-code">
                     <button class="copy-btn" onclick="copyToClipboard('php-code')">Copiar</button>
                    <code id="php-code"></code>
                </div>
             </div>
        </div>
    </div>

    <script>
        document.getElementById('add-campo').addEventListener('click', function() {
            const container = document.getElementById('campos-container');
            const div = document.createElement('div');
            div.className = 'campo-dinamico';
            div.innerHTML = `
                <div class="flex-grow-1">
                    <input type="text" class="form-control" placeholder="Nombre del Campo (Ej: Lúmenes)" required>
                </div>
                <div>
                    <select class="form-select">
                        <option value="text">Texto</option>
                        <option value="number">Número</option>
                        <option value="date">Fecha</option>
                    </select>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input es-unico">
                    <label class="form-check-label">Es Único (UNIQUE)</label>
                </div>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">X</button>
            `;
            container.appendChild(div);
        });

        document.getElementById('generadorForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const singular = document.getElementById('nombre_singular').value;
            const plural = document.getElementById('nombre_plural').value;
            const icono = document.getElementById('icono').value;
            
            const tipo_key = singular.toLowerCase().replace(/ /g, '_').normalize("NFD").replace(/[\u0300-\u036f]/g, "");
            const tabla_nombre = `hardware_${tipo_key}`;

            let sqlFields = [
                'id INT AUTO_INCREMENT PRIMARY KEY',
                'nombre_equipo VARCHAR(100) NOT NULL',
                'usuario_asignado VARCHAR(100)',
                'marca VARCHAR(100)',
                'modelo VARCHAR(100)',
                'numero_serie VARCHAR(100) NOT NULL UNIQUE'
            ];
            
            let phpFields = [
                `'nombre_equipo' => ['label' => 'Nombre del Equipo', 'type' => 'text', 'required' => true]`,
                `'usuario_asignado' => ['label' => 'Usuario Asignado', 'type' => 'text']`,
                `'marca' => ['label' => 'Marca', 'type' => 'text']`,
                `'modelo' => ['label' => 'Modelo', 'type' => 'text']`,
                `'numero_serie' => ['label' => 'Número de Serie', 'type' => 'text', 'required' => true, 'unique' => true]`
            ];

            const campos = document.querySelectorAll('.campo-dinamico');
            campos.forEach(campo => {
                const input = campo.querySelector('input[type="text"]');
                const select = campo.querySelector('select');
                const esUnico = campo.querySelector('.es-unico').checked;

                const label = input.value.trim();
                const name = label.toLowerCase().replace(/ /g, '_').normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                
                if (label) {
                    let sqlType = '';
                    switch(select.value) {
                        case 'number': sqlType = 'INT'; break;
                        case 'date': sqlType = 'DATE'; break;
                        default: sqlType = 'VARCHAR(255)';
                    }
                    const unicoSql = esUnico ? ' UNIQUE' : '';
                    sqlFields.push(`${name} ${sqlType}${unicoSql}`);
                    
                    const unicoPhp = esUnico ? ", 'unique' => true" : "";
                    const reqPhp = esUnico ? ", 'required' => true" : ""; // Un campo único debería ser requerido
                    phpFields.push(`'${name}' => ['label' => '${label}', 'type' => '${select.value}'${reqPhp}${unicoPhp}]`);
                }
            });

            sqlFields.push(
                'estado VARCHAR(50) DEFAULT \'Operativo\'',
                'fecha_adquisicion DATE',
                'ultima_revision TIMESTAMP NULL',
                'notas TEXT',
                'INDEX(estado)',
                'INDEX(usuario_asignado)'
            );

            const sqlOutput = `CREATE TABLE ${tabla_nombre} (\n    ${sqlFields.join(',\n    ')}\n);`;
            
            const phpOutput = 
`'${tipo_key}' => [
    'singular' => '${singular}',
    'plural'   => '${plural}',
    'tabla'    => '${tabla_nombre}',
    'icono'    => '${icono}',
    'campos'   => [
        ${phpFields.join(',\n        ')}
    ]
],`;

            document.getElementById('sql-code').textContent = sqlOutput;
            document.getElementById('php-code').textContent = phpOutput;
            document.getElementById('resultado').classList.remove('d-none');
            window.scrollTo(0, document.body.scrollHeight);
        });

        function copyToClipboard(elementId) {
            const text = document.getElementById(elementId).innerText;
            navigator.clipboard.writeText(text).then(() => {
                const btn = event.target;
                const originalText = btn.innerHTML;
                btn.innerHTML = '¡Copiado!';
                setTimeout(() => { btn.innerHTML = originalText; }, 2000);
            });
        }
    </script>
</body>
</html>