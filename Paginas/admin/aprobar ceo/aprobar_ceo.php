<?php
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/conexion.php';

if (!isset($_SESSION['codUsuario'])) {
    header('Location: /Paginas/autenticacion/iniciar_sesion.php');
    exit;
}

if (($_SESSION['tipoUsuario'] ?? '') !== 'administrador') {
    if (($_SESSION['tipoUsuario'] ?? '') === 'ceo de aerolinea') {
        header('Location: /Paginas/ceo/Menu%20ceo/panel_control.php');
    } elseif (($_SESSION['tipoUsuario'] ?? '') === 'usuario') {
        header('Location: /Paginas/usuario/menu_usuario.php');
    } else {
        header('Location: /');
    }
    exit;
}

$solicitudesCeo = [];
$errorCarga = '';
$conexion = null;

try {
    $conexion = conectarBaseDeDatos();
    $consulta = $conexion->prepare(
        "SELECT u.codUsuario, u.nombreUsuario, u.emailUsuario, u.telefonoUsuario,
                a.codAerolinea, a.nombreAerolinea, a.codigoIATA
         FROM usuarios AS u
         INNER JOIN aerolineas AS a ON a.codAerolinea = u.codAerolinea
         WHERE u.tipoUsuario = 'ceo pendiente'
         ORDER BY u.codUsuario ASC"
    );
    $consulta->execute();
    $consulta->bind_result(
        $codUsuario,
        $nombreUsuario,
        $emailUsuario,
        $telefonoUsuario,
        $codAerolinea,
        $nombreAerolinea,
        $codigoIATA
    );

    while ($consulta->fetch()) {
        $solicitudesCeo[] = [
            'codUsuario' => $codUsuario,
            'nombreUsuario' => $nombreUsuario,
            'emailUsuario' => $emailUsuario,
            'telefonoUsuario' => $telefonoUsuario,
            'codAerolinea' => $codAerolinea,
            'nombreAerolinea' => $nombreAerolinea,
            'codigoIATA' => $codigoIATA,
        ];
    }
    $consulta->close();
} catch (mysqli_sql_exception $error) {
    error_log('Error al cargar solicitudes para ser CEO: ' . $error->getMessage());
    $errorCarga = 'No se pudieron cargar las solicitudes de CEO. Verificá la conexión y la relación de usuarios con aerolíneas.';
} finally {
    if ($conexion instanceof mysqli) {
        $conexion->close();
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprobar solicitudes de CEO | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../plantilla/encabezado.php'; ?>

    <main class="contenido-principal solicitudes-ceo" aria-labelledby="titulo-solicitudes-ceo">
        <header class="solicitudes-ceo-encabezado">
            <p class="autenticacion-etiqueta">Administración</p>
            <h1 id="titulo-solicitudes-ceo">Solicitudes para ser CEO</h1>
            <p>Revisá quién solicita administrar una aerolínea y cuál eligió en el registro.</p>
        </header>

        <?php if ($errorCarga !== ''): ?>
            <p class="mensaje-autenticacion" role="alert"><?= $escapar($errorCarga) ?></p>
        <?php elseif ($solicitudesCeo === []): ?>
            <section class="solicitudes-ceo-vacio" aria-live="polite">
                <h2>No hay solicitudes pendientes</h2>
                <p>Las solicitudes para ser CEO aparecerán acá cuando alguien se registre eligiendo una aerolínea.</p>
            </section>
        <?php else: ?>
            <p class="solicitudes-ceo-total"><?= count($solicitudesCeo) ?> <?= count($solicitudesCeo) === 1 ? 'solicitud pendiente' : 'solicitudes pendientes' ?></p>
            <section class="solicitudes-ceo-listado" aria-label="Solicitudes pendientes de CEO">
                <?php foreach ($solicitudesCeo as $solicitud): ?>
                    <article class="solicitud-ceo">
                        <div class="solicitud-ceo-persona">
                            <p class="solicitud-ceo-codigo">Usuario #<?= (int) $solicitud['codUsuario'] ?></p>
                            <h2><?= $escapar((string) $solicitud['nombreUsuario']) ?></h2>
                            <p><?= $escapar((string) $solicitud['emailUsuario']) ?></p>
                            <p>Teléfono: <?= $escapar((string) ($solicitud['telefonoUsuario'] ?? 'No informado')) ?></p>
                        </div>
                        <div class="solicitud-ceo-aerolinea">
                            <p class="solicitud-ceo-etiqueta">Solicita ser CEO de</p>
                            <h3><?= $escapar((string) $solicitud['nombreAerolinea']) ?></h3>
                            <p>Aerolínea #<?= (int) $solicitud['codAerolinea'] ?> · IATA <?= $escapar((string) $solicitud['codigoIATA']) ?></p>
                        </div>
                        <span class="solicitud-ceo-estado">Pendiente</span>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../../plantilla/pie_pagina.php'; ?>
</body>
</html>