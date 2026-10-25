<?php
function guardarImagenContenido(?array $archivo): ?string
{
    if ($archivo === null || !isset($archivo['error'])) {
        return null;
    }

    if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('La imagen supera el límite de carga configurado en el servidor.');
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK || !isset($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
        throw new RuntimeException('No se pudo recibir la imagen. Intentá nuevamente.');
    }

    if (!isset($archivo['size']) || (int) $archivo['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('La imagen no puede superar los 5 MB.');
    }

    if (!class_exists(finfo::class)) {
        throw new RuntimeException('El servidor no tiene habilitada la validación de imágenes.');
    }

    $informacionArchivo = new finfo(FILEINFO_MIME_TYPE);
    $tipoMime = $informacionArchivo->file($archivo['tmp_name']);
    $extensionesPermitidas = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $dimensiones = @getimagesize($archivo['tmp_name']);

    if (!isset($extensionesPermitidas[$tipoMime]) || $dimensiones === false || ($dimensiones['mime'] ?? '') !== $tipoMime) {
        throw new RuntimeException('El archivo debe ser una imagen JPG, PNG o WebP válida.');
    }

    $directorio = dirname(__DIR__, 2) . '/publico/imagenes/contenido';
    if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo preparar el almacenamiento de imágenes.');
    }

    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extensionesPermitidas[$tipoMime];
    $destino = $directorio . '/' . $nombreArchivo;
    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        throw new RuntimeException('No se pudo guardar la imagen. Intentá nuevamente.');
    }

    return '/publico/imagenes/contenido/' . $nombreArchivo;
}

function eliminarImagenContenido(?string $ruta): void
{
    if ($ruta === null || !preg_match('#\A/publico/imagenes/contenido/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $ruta)) {
        return;
    }

    $archivo = dirname(__DIR__, 2) . str_replace('/', DIRECTORY_SEPARATOR, $ruta);
    if (is_file($archivo) && !unlink($archivo)) {
        error_log('No se pudo eliminar la imagen de contenido: ' . $archivo);
    }
}
