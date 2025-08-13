<?php
// config_helper.php - Funciones auxiliares para manejo de configuraciones

/**
 * Obtiene una configuración del sistema
 */
function obtenerConfiguracion($clave, $valorPorDefecto = '') {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT valor FROM configuraciones WHERE clave = ?");
        $stmt->execute([$clave]);
        $resultado = $stmt->fetchColumn();
        return $resultado !== false ? $resultado : $valorPorDefecto;
    } catch (Exception $e) {
        return $valorPorDefecto;
    }
}

/**
 * Establece una configuración del sistema
 */
function establecerConfiguracion($clave, $valor, $descripcion = '') {
    try {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("
            INSERT INTO configuraciones (clave, valor, descripcion) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            valor = VALUES(valor), 
            descripcion = COALESCE(NULLIF(VALUES(descripcion), ''), descripcion)
        ");
        return $stmt->execute([$clave, $valor, $descripcion]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Valida archivo de imagen subido
 */
function validarImagenSubida($archivo) {
    $errores = [];
    
    // Verificar que se subió correctamente
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Error en la subida del archivo.";
        return $errores;
    }
    
    // Validar tipo de archivo
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($archivo['type'], $tiposPermitidos)) {
        $errores[] = "Solo se permiten archivos de imagen (JPG, PNG, GIF, WEBP).";
    }
    
    // Validar tamaño (máximo 5MB)
    if ($archivo['size'] > 5 * 1024 * 1024) {
        $errores[] = "El archivo es demasiado grande. Máximo 5MB.";
    }
    
    // Validar dimensiones mínimas
    $infoImagen = getimagesize($archivo['tmp_name']);
    if ($infoImagen) {
        $ancho = $infoImagen[0];
        $alto = $infoImagen[1];
        
        if ($ancho < 50 || $alto < 50) {
            $errores[] = "La imagen debe tener al menos 50x50 píxeles.";
        }
    } else {
        $errores[] = "El archivo no es una imagen válida.";
    }
    
    return $errores;
}

/**
 * Procesa subida de logo de empresa
 */
function procesarSubidaLogo($archivo) {
    // Validar archivo
    $errores = validarImagenSubida($archivo);
    if (!empty($errores)) {
        throw new Exception(implode(' ', $errores));
    }
    
    // Crear directorio si no existe
    $directorioLogos = 'uploads/logos/';
    if (!is_dir($directorioLogos)) {
        if (!mkdir($directorioLogos, 0755, true)) {
            throw new Exception("No se pudo crear el directorio de logos.");
        }
        
        // Crear archivo .htaccess para seguridad
        file_put_contents($directorioLogos . '.htaccess', "Options -Indexes\n");
    }
    
    // Generar nombre único para el archivo
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $nombreArchivo = 'logo_empresa_' . time() . '_' . uniqid() . '.' . $extension;
    $rutaCompleta = $directorioLogos . $nombreArchivo;
    
    // Mover archivo subido
    if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        throw new Exception("Error al guardar el archivo.");
    }
    
    return $rutaCompleta;
}

/**
 * Elimina logo anterior del sistema de archivos
 */
function eliminarLogoAnterior($ruta) {
    if (!empty($ruta) && file_exists($ruta) && strpos($ruta, 'uploads/logos/') === 0) {
        return unlink($ruta);
    }
    return true;
}
?>