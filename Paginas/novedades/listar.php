<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/conexion.php';

$zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
$fechaActual = (new DateTimeImmutable('today', $zonaHoraria))->format('Y-m-d');
$novedades = [];
$errorCarga = '';
$conexion = null;

try {
    $conexion = conectarBaseDeDatos();
    $consulta = $conexion->prepare(
        'SELECT codNovedad, textoNovedad, fechaPublicacionNovedad, fechaExpiracionNovedad
         FROM novedades
         WHERE fechaPublicacionNovedad <= ?
           AND (fechaExpiracionNovedad IS NULL OR fechaExpiracionNovedad >= ?)
         ORDER BY fechaPublicacionNovedad DESC, codNovedad DESC'
    );
    $consulta->bind_param('ss', $fechaActual, $fechaActual);
    $consulta->execute();
    $consulta->bind_result(
        $codNovedad,
        $textoNovedad,
        $fechaPublicacion,
        $fechaExpiracion
    );

    while ($consulta->fetch()) {
        $novedades[] = [
            'codNovedad' => $codNovedad,
            'textoNovedad' => $textoNovedad,
            'fechaPublicacionNovedad' => $fechaPublicacion,
            'fechaExpiracionNovedad' => $fechaExpiracion,
        ];
    }
    $consulta->close();
} catch (mysqli_sql_exception $error) {
    error_log('Error al consultar las novedades publicadas: ' . $error->getMessage());
    $errorCarga = 'No se pudieron cargar las novedades. Intentá nuevamente más tarde.';
} finally {
    if ($conexion instanceof mysqli) {
        $conexion->close();
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formatearFecha = static fn(string $fecha): string => (
    new DateTimeImmutable($fecha, $zonaHoraria)
)->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novedades | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../plantilla/encabezado.php'; ?>

    <main class="contenido-principal novedades-publicas" aria-labelledby="titulo-novedades">
        <header class="novedades-publicas-encabezado">
            <p class="autenticacion-etiqueta">PlaneARG</p>
            <h1 id="titulo-novedades">Novedades</h1>
            <p>Informate sobre las últimas novedades y anuncios.</p>
        </header>

        <?php if ($errorCarga !== ''): ?>
            <p class="mensaje-autenticacion" role="alert"><?= $escapar($errorCarga) ?></p>
        <?php elseif ($novedades === []): ?>
            <section class="novedades-publicas-vacio" aria-live="polite">
                <h2>Por ahora no hay novedades publicadas</h2>
                <p>Cuando haya novedades vigentes, las vas a encontrar en esta sección.</p>
            </section>
        <?php else: ?>
            <section class="novedades-publicas-lista" aria-label="Novedades vigentes">
                <?php foreach ($novedades as $novedad): ?>
                    <article class="novedad-publica">
                        <div class="novedad-publica-fechas">
                            <p>
                                Publicada el
                                <time datetime="<?= $escapar((string) $novedad['fechaPublicacionNovedad']) ?>">
                                    <?= $escapar($formatearFecha((string) $novedad['fechaPublicacionNovedad'])) ?>
                                </time>
                            </p>
                            <?php if ($novedad['fechaExpiracionNovedad'] !== null): ?>
                                <p>
                                    Vigente hasta el
                                    <time datetime="<?= $escapar((string) $novedad['fechaExpiracionNovedad']) ?>">
                                        <?= $escapar($formatearFecha((string) $novedad['fechaExpiracionNovedad'])) ?>
                                    </time>
                                </p>
                            <?php else: ?>
                                <p>Sin fecha de expiración</p>
                            <?php endif; ?>
                        </div>
                        <p class="novedad-publica-texto"><?= nl2br($escapar((string) $novedad['textoNovedad'])) ?></p>
                        <span class="novedad-publica-codigo">Novedad #<?= (int) $novedad['codNovedad'] ?></span>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if (($_SESSION['tipoUsuario'] ?? '') === 'administrador'): ?>
            <p class="novedades-publicas-admin">
                <a href="/Paginas/admin/novedades/gestionar_novedades.php">Gestionar novedades</a>
            </p>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../plantilla/pie_pagina.php'; ?>
</body>
</html>