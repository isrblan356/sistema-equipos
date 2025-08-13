<?php
require_once 'config.php';
verificarLogin(); // Asegurar que está logueado
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Denegado - Sistema Unificado</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; --text-color: #2c3e50; --bg-color: #f4f7f9; --card-bg: white; --shadow: 0 10px 30px rgba(0,0,0,0.08); --border-radius: 15px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { max-width: 500px; text-align: center; padding: 2rem; }
        .error-card { background: var(--card-bg); border-radius: var(--border-radius); padding: 3rem 2rem; box-shadow: var(--shadow); }
        .error-icon { font-size: 4rem; color: #d93749; margin-bottom: 1.5rem; }
        .error-title { font-size: 2rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-color); }
        .error-message { color: #666; font-size: 1.1rem; line-height: 1.6; margin-bottom: 2rem; }
        .btn { border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 12px 24px; font-size: 1rem; }
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-card">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1 class="error-title">Acceso Denegado</h1>
            <p class="error-message">
                No tienes permisos suficientes para acceder a esta página.<br>
                Solo los administradores pueden ver este contenido.
            </p>
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
    </div>
</body>
</html>