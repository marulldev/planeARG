<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/conexion.php';

if (!isset($_SESSION['codUsuario'])) {
    header('Location: /Paginas/autenticacion/iniciar_sesion.php');
    exit;
}

if (($_SESSION['tipoUsuario'] ?? '') !== 'usuario') {
    if (($_SESSION['tipoUsuario'] ?? '') === 'administrador') {
        header('Location: /Paginas/admin/menu_admin.php');
    } elseif (($_SESSION['tipoUsuario'] ?? '') === 'ceo de aerolinea') {
        header('Location: /Paginas/ceo/Menu%20ceo/panel_control.php');
    } else {
        header('Location: /');
    }
    exit;
}

$codUsuario = (int) $_SESSION['codUsuario'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $tokenEnviado = (string) ($_POST['csrf_token'] ?? '');
    $codReserva = filter_var($_POST['codReserva'] ?? null, FILTER_VALIDATE_INT);
    $mensaje = '';
    $tipoMensaje = 'error';
    $conexion = null;

    if (!hash_equals((string) $_SESSION['csrf_auth'], $tokenEnviado)) {
        $mensaje = 'La sesión venció. Recargá la página e intentá nuevamente.';
    } elseif ($codReserva === false || $codReserva === null || $codReserva < 1) {
        $mensaje = 'El código de reserva no es válido.';
    } else {
        try {
            $conexion = conectarBaseDeDatos();
            $conexion->begin_transaction();

            $consultaReserva = $conexion->prepare(
                'SELECT r.codVuelo, r.estadoReserva,
                        TIMESTAMPDIFF(
                            SECOND,
                            NOW(),
                            TIMESTAMP(v.fechaSalidaVuelo, v.horaSalidaVuelo)
                        ) AS segundosHastaSalida
                 FROM reservas AS r
                 INNER JOIN vuelos AS v ON v.codVuelo = r.codVuelo
                 WHERE r.codReserva = ? AND r.codUsuario = ?
                 FOR UPDATE'
            );
            $consultaReserva->bind_param('ii', $codReserva, $codUsuario);
            $consultaReserva->execute();
            $consultaReserva->bind_result($codVuelo, $estadoReserva, $segundosHastaSalida);
            $reservaEncontrada = $consultaReserva->fetch();
            $consultaReserva->close();

            if (!$reservaEncontrada) {
                throw new DomainException('No encontramos esa reserva asociada a tu cuenta.');
            }

            if (!in_array($estadoReserva, ['pendiente de pago', 'confirmada'], true)) {
                throw new DomainException('Solo se pueden cancelar reservas pendientes de pago o confirmadas.');
            }

            if ((int) $segundosHastaSalida < 72 * 60 * 60) {
                throw new DomainException('La reserva solo se puede cancelar hasta 72 horas antes de la salida del vuelo.');
            }

            $cancelarReserva = $conexion->prepare(
                "UPDATE reservas
                 SET estadoReserva = 'cancelada'
                 WHERE codReserva = ? AND codUsuario = ?
                   AND estadoReserva IN ('pendiente de pago', 'confirmada')"
            );
            $cancelarReserva->bind_param('ii', $codReserva, $codUsuario);
            $cancelarReserva->execute();
            $reservaCancelada = $cancelarReserva->affected_rows === 1;
            $cancelarReserva->close();

            if (!$reservaCancelada) {
                throw new DomainException('No se pudo cancelar la reserva. Actualizá la página e intentá nuevamente.');
            }

            $liberarAsiento = $conexion->prepare(
                'UPDATE vuelos SET asientosDisponibles = asientosDisponibles + 1 WHERE codVuelo = ?'
            );
            $liberarAsiento->bind_param('i', $codVuelo);
            $liberarAsiento->execute();
            $liberarAsiento->close();

            $conexion->commit();
            $tipoMensaje = 'exito';
            $mensaje = 'La reserva #' . $codReserva . ' fue cancelada y el asiento volvió a estar disponible.';
        } catch (DomainException $error) {
            if ($conexion instanceof mysqli) {
                $conexion->rollback();
            }
            $mensaje = $error->getMessage();
        } catch (mysqli_sql_exception $error) {
            if ($conexion instanceof mysqli) {
                $conexion->rollback();
            }
            error_log('Error al cancelar una reserva: ' . $error->getMessage());
            $mensaje = 'No se pudo procesar la cancelación. Intentá nuevamente más tarde.';
        } finally {
            if ($conexion instanceof mysqli) {
                $conexion->close();
            }
        }
    }

    $_SESSION['mensaje_gestion_reservas'] = [
        'tipo' => $tipoMensaje,
        'texto' => $mensaje,
    ];
    header('Location: /Paginas/usuario/gestion_reservas.php');
    exit;
}

$mensajeGestion = '';
$tipoMensajeGestion = 'exito';
if (isset($_SESSION['mensaje_gestion_reservas'])) {
    $mensajeGestion = (string) ($_SESSION['mensaje_gestion_reservas']['texto'] ?? '');
    $tipoMensajeGestion = (string) ($_SESSION['mensaje_gestion_reservas']['tipo'] ?? 'exito');
    unset($_SESSION['mensaje_gestion_reservas']);
}

$reservas = [];
$errorCarga = '';
$conexion = null;

try {
    $conexion = conectarBaseDeDatos();
    $consulta = $conexion->prepare(
        'SELECT r.codReserva, r.fechaReserva, r.estadoReserva,
                v.origenVuelo, v.destinoVuelo, v.fechaSalidaVuelo,
                v.horaSalidaVuelo, r.precioReserva, a.nombreAerolinea,
                TIMESTAMPDIFF(
                    SECOND,
                    NOW(),
                    TIMESTAMP(v.fechaSalidaVuelo, v.horaSalidaVuelo)
                ) AS segundosHastaSalida
         FROM reservas AS r
         INNER JOIN vuelos AS v ON v.codVuelo = r.codVuelo
         INNER JOIN aerolineas AS a ON a.codAerolinea = v.codAerolinea
         WHERE r.codUsuario = ?
         ORDER BY r.fechaReserva DESC, r.codReserva DESC'
    );
    $consulta->bind_param('i', $codUsuario);
    $consulta->execute();
    $consulta->bind_result(
        $codReserva,
        $fechaReserva,
        $estadoReserva,
        $origenVuelo,
        $destinoVuelo,
        $fechaSalidaVuelo,
        $horaSalidaVuelo,
        $precioReserva,
        $nombreAerolinea,
        $segundosHastaSalida
    );

    while ($consulta->fetch()) {
        $reservas[] = [
            'codReserva' => $codReserva,
            'fechaReserva' => $fechaReserva,
            'estadoReserva' => $estadoReserva,
            'origenVuelo' => $origenVuelo,
            'destinoVuelo' => $destinoVuelo,
            'fechaSalidaVuelo' => $fechaSalidaVuelo,
            'horaSalidaVuelo' => $horaSalidaVuelo,
            'precioVuelo' => $precioReserva,
            'nombreAerolinea' => $nombreAerolinea,
            'cancelable' => in_array($estadoReserva, ['pendiente de pago', 'confirmada'], true)
                && (int) $segundosHastaSalida >= 72 * 60 * 60,
        ];
    }
    $consulta->close();
} catch (mysqli_sql_exception $error) {
    error_log('Error al consultar las reservas del usuario: ' . $error->getMessage());
    $errorCarga = 'No se pudieron cargar tus reservas. Intentá nuevamente más tarde.';
} finally {
    if ($conexion instanceof mysqli) {
        $conexion->close();
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formatearFecha = static fn(string $fecha): string => date('d/m/Y', strtotime($fecha));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar reservas | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../plantilla/encabezado.php'; ?>

    <main class="contenido-principal gestion-reservas" aria-labelledby="titulo-gestion-reservas">
        <header class="gestion-reservas-encabezado">
            <p class="autenticacion-etiqueta">Tu cuenta</p>
            <h1 id="titulo-gestion-reservas">Gestionar reservas</h1>
            <p>Consultá el estado de tus reservas. Podés cancelar una reserva hasta 72 horas antes de la salida.</p>
        </header>

        <?php if ($mensajeGestion !== ''): ?>
            <p class="mensaje-reserva <?= $tipoMensajeGestion === 'error' ? 'mensaje-reserva-error' : 'mensaje-reserva-exito' ?>" role="<?= $tipoMensajeGestion === 'error' ? 'alert' : 'status' ?>">
                <?= $escapar($mensajeGestion) ?>
            </p>
        <?php endif; ?>

        <?php if ($errorCarga !== ''): ?>
            <p class="mensaje-autenticacion" role="alert"><?= $escapar($errorCarga) ?></p>
        <?php elseif ($reservas === []): ?>
            <section class="gestion-reservas-vacio" aria-live="polite">
                <h2>Todavía no tenés reservas</h2>
                <p>Cuando reserves un vuelo, vas a poder consultar y gestionar la reserva desde acá.</p>
                <a class="historial-compras-enlace" href="/Paginas/usuario/reservar_vuelo.php">Reservar un vuelo</a>
            </section>
        <?php else: ?>
            <div class="gestion-reservas-lista">
                <?php foreach ($reservas as $reserva): ?>
                    <article class="gestion-reserva">
                        <div class="gestion-reserva-detalle">
                            <p class="gestion-reserva-aerolinea"><?= $escapar((string) $reserva['nombreAerolinea']) ?></p>
                            <h2><?= $escapar((string) $reserva['origenVuelo']) ?> <span aria-hidden="true">→</span> <?= $escapar((string) $reserva['destinoVuelo']) ?></h2>
                            <p><strong>Código de reserva:</strong> #<?= (int) $reserva['codReserva'] ?></p>
                            <p><strong>Fecha de reserva:</strong> <?= $escapar($formatearFecha((string) $reserva['fechaReserva'])) ?></p>
                            <p><strong>Salida:</strong> <?= $escapar($formatearFecha((string) $reserva['fechaSalidaVuelo'])) ?> · <?= $escapar(substr((string) $reserva['horaSalidaVuelo'], 0, 5)) ?></p>
                            <p><strong>Precio:</strong> $ <?= number_format((float) $reserva['precioVuelo'], 2, ',', '.') ?></p>
                        </div>
                        <div class="gestion-reserva-estado-acciones">
                            <span class="gestion-reserva-estado estado-<?= $reserva['estadoReserva'] === 'confirmada' ? 'confirmada' : ($reserva['estadoReserva'] === 'cancelada' ? 'cancelada' : 'pendiente') ?>">
                                <?= $escapar((string) $reserva['estadoReserva']) ?>
                            </span>
                            <?php if ($reserva['cancelable']): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= $escapar((string) $_SESSION['csrf_auth']) ?>">
                                    <input type="hidden" name="codReserva" value="<?= (int) $reserva['codReserva'] ?>">
                                    <button class="boton-reserva boton-cancelar" type="submit">Cancelar reserva</button>
                                </form>
                                <p class="gestion-reserva-aviso">Cancelación disponible hasta 72 horas antes de la salida.</p>
                            <?php elseif (in_array($reserva['estadoReserva'], ['pendiente de pago', 'confirmada'], true)): ?>
                                <p class="gestion-reserva-no-cancelable">Ya no es posible cancelar: faltan menos de 72 horas para la salida.</p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../plantilla/pie_pagina.php'; ?>
</body>
</html>