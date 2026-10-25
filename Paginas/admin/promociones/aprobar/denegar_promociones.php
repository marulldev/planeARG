<?php
require_once __DIR__ . '/../../../includes/sesion.php';
require_once __DIR__ . '/../../../includes/conexion.php';

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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');
    $codPromocion = filter_var($_POST['codPromocion'] ?? null, FILTER_VALIDATE_INT);
    $mensaje = '';
    $tipoMensaje = 'error';
    $conexion = null;

    if (!hash_equals((string) $_SESSION['csrf_auth'], (string) ($_POST['csrf_token'] ?? ''))) {
        $mensaje = 'La sesión venció. Recargá la página e intentá nuevamente.';
    } elseif (!in_array($accion, ['aprobar', 'denegar'], true)) {
        $mensaje = 'La acción solicitada no es válida.';
    } elseif ($codPromocion === false || $codPromocion === null || $codPromocion < 1) {
        $mensaje = 'El código de promoción no es válido.';
    } else {
        try {
            $conexion = conectarBaseDeDatos();
            $estadoNuevo = $accion === 'aprobar' ? 'aprobada' : 'denegada';
            $consulta = $conexion->prepare(
                "UPDATE promociones
                 SET estadoPromocion = ?
                 WHERE codPromocion = ? AND estadoPromocion = 'pendiente'"
            );
            $consulta->bind_param('si', $estadoNuevo, $codPromocion);
            $consulta->execute();
            $promocionActualizada = $consulta->affected_rows === 1;
            $consulta->close();

            if (!$promocionActualizada) {
                $mensaje = 'La promoción no existe o ya fue revisada.';
            } else {
                $mensaje = 'La promoción #' . $codPromocion . ' fue ' . $estadoNuevo . '.';
                $tipoMensaje = 'exito';
            }
        } catch (mysqli_sql_exception $error) {
            error_log('Error al revisar una promoción: ' . $error->getMessage());
            $mensaje = 'No se pudo actualizar la promoción. Intentá nuevamente más tarde.';
        } finally {
            if ($conexion instanceof mysqli) {
                $conexion->close();
            }
        }
    }

    $_SESSION['mensaje_revision_promocion'] = [
        'tipo' => $tipoMensaje,
        'texto' => $mensaje,
    ];
    header('Location: /Paginas/admin/promociones/aprobar/denegar_promociones.php');
    exit;
}

$mensajeRevision = '';
$tipoMensajeRevision = 'exito';
if (isset($_SESSION['mensaje_revision_promocion'])) {
    $mensajeRevision = (string) ($_SESSION['mensaje_revision_promocion']['texto'] ?? '');
    $tipoMensajeRevision = (string) ($_SESSION['mensaje_revision_promocion']['tipo'] ?? 'exito');
    unset($_SESSION['mensaje_revision_promocion']);
}

$promocionesPendientes = [];
$errorCarga = '';
$conexion = null;

try {
    $conexion = conectarBaseDeDatos();
    $consulta = $conexion->prepare(
        "SELECT p.codPromocion, p.descripcionPromocion, p.descuentoPromocion,
                a.codAerolinea, a.nombreAerolinea
         FROM promociones AS p
         INNER JOIN aerolineas AS a ON a.codAerolinea = p.codAerolinea
         WHERE p.estadoPromocion = 'pendiente'
         ORDER BY p.codPromocion ASC"
    );
    $consulta->execute();
    $consulta->bind_result(
        $codPromocion,
        $descripcionPromocion,
        $descuentoPromocion,
        $codAerolinea,
        $nombreAerolinea
    );

    while ($consulta->fetch()) {
        $promocionesPendientes[] = [
            'codPromocion' => $codPromocion,
            'descripcionPromocion' => $descripcionPromocion,
            'descuentoPromocion' => $descuentoPromocion,
            'codAerolinea' => $codAerolinea,
            'nombreAerolinea' => $nombreAerolinea,
        ];
    }
    $consulta->close();
} catch (mysqli_sql_exception $error) {
    error_log('Error al cargar promociones pendientes: ' . $error->getMessage());
    $errorCarga = 'No se pudieron cargar las promociones pendientes. Intentá nuevamente más tarde.';
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
    <title>Aprobar promociones | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../../plantilla/encabezado.php'; ?>

    <main class="contenido-principal revision-promociones" aria-labelledby="titulo-revision-promociones">
        <header class="revision-promociones-encabezado">
            <p class="autenticacion-etiqueta">Administración</p>
            <h1 id="titulo-revision-promociones">Aprobar o denegar promociones</h1>
            <p>Las promociones de los CEO permanecen pendientes hasta que un administrador las revise.</p>
        </header>

        <?php if ($mensajeRevision !== ''): ?>
            <p class="mensaje-reserva <?= $tipoMensajeRevision === 'error' ? 'mensaje-reserva-error' : 'mensaje-reserva-exito' ?>" role="<?= $tipoMensajeRevision === 'error' ? 'alert' : 'status' ?>">
                <?= $escapar($mensajeRevision) ?>
            </p>
        <?php endif; ?>

        <?php if ($errorCarga !== ''): ?>
            <p class="mensaje-autenticacion" role="alert"><?= $escapar($errorCarga) ?></p>
        <?php elseif ($promocionesPendientes === []): ?>
            <section class="gestion-promociones-vacio" aria-live="polite">
                <h2>No hay promociones pendientes</h2>
                <p>Las nuevas promociones enviadas por los CEO aparecerán acá para su revisión.</p>
            </section>
        <?php else: ?>
            <div class="lista-promociones-elementos">
                <?php foreach ($promocionesPendientes as $promocion): ?>
                    <article class="promocion-elemento promocion-pendiente">
                        <div>
                            <p class="promocion-aerolinea"><?= $escapar((string) $promocion['nombreAerolinea']) ?> · Aerolínea #<?= (int) $promocion['codAerolinea'] ?></p>
                            <h2><?= $escapar((string) $promocion['descripcionPromocion']) ?></h2>
                            <p>Promoción #<?= (int) $promocion['codPromocion'] ?> · Descuento: <?= number_format((float) $promocion['descuentoPromocion'], 2, ',', '.') ?>%</p>
                        </div>
                        <div class="acciones-revision-promocion">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= $escapar((string) $_SESSION['csrf_auth']) ?>">
                                <input type="hidden" name="codPromocion" value="<?= (int) $promocion['codPromocion'] ?>">
                                <input type="hidden" name="accion" value="aprobar">
                                <button class="boton-reserva boton-pagar" type="submit">Aprobar</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= $escapar((string) $_SESSION['csrf_auth']) ?>">
                                <input type="hidden" name="codPromocion" value="<?= (int) $promocion['codPromocion'] ?>">
                                <input type="hidden" name="accion" value="denegar">
                                <button class="boton-reserva boton-cancelar" type="submit">Denegar</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../../../plantilla/pie_pagina.php'; ?>
</body>
</html>