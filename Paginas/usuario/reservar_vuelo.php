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
$mensajeReserva = '';
$tipoMensajeReserva = 'exito';

if (isset($_SESSION['mensaje_reserva'])) {
    $mensajeReserva = (string) ($_SESSION['mensaje_reserva']['texto'] ?? '');
    $tipoMensajeReserva = (string) ($_SESSION['mensaje_reserva']['tipo'] ?? 'exito');
    unset($_SESSION['mensaje_reserva']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $tokenEnviado = (string) ($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $conexion = null;

    if (!hash_equals((string) $_SESSION['csrf_auth'], $tokenEnviado)) {
        $_SESSION['mensaje_reserva'] = [
            'tipo' => 'error',
            'texto' => 'La sesión venció. Recargá la página e intentá nuevamente.',
        ];
    } elseif (!in_array($accion, ['reservar', 'pagar'], true)) {
        $_SESSION['mensaje_reserva'] = [
            'tipo' => 'error',
            'texto' => 'La acción solicitada no es válida.',
        ];
    } else {
        try {
            $conexion = conectarBaseDeDatos();
            $conexion->begin_transaction();

            if ($accion === 'reservar') {
                $codVuelo = filter_var($_POST['codVuelo'] ?? null, FILTER_VALIDATE_INT);
                if ($codVuelo === false || $codVuelo === null || $codVuelo < 1) {
                    throw new DomainException('Seleccioná un vuelo válido.');
                }

                $consultaVuelo = $conexion->prepare(
                    "SELECT v.fechaSalidaVuelo, v.horaSalidaVuelo, v.asientosDisponibles,
                            ROUND(v.precioVuelo * (100 - COALESCE(p.descuentoPromocion, 0)) / 100, 2) AS precioFinal
                     FROM vuelos AS v
                     LEFT JOIN promociones AS p
                       ON p.codPromocion = (
                           SELECT MAX(p2.codPromocion)
                           FROM promociones AS p2
                           WHERE p2.codAerolinea = v.codAerolinea
                             AND p2.estadoPromocion = 'aprobada'
                             AND (p2.fechaExpiracionPromocion IS NULL OR p2.fechaExpiracionPromocion >= CURRENT_DATE)
                       )
                     WHERE v.codVuelo = ?
                     FOR UPDATE"
                );
                $consultaVuelo->bind_param('i', $codVuelo);
                $consultaVuelo->execute();
                $consultaVuelo->bind_result($fechaSalida, $horaSalida, $asientosDisponibles, $precioReserva);
                $vueloEncontrado = $consultaVuelo->fetch();
                $consultaVuelo->close();

                if (!$vueloEncontrado) {
                    throw new DomainException('El vuelo seleccionado ya no está disponible.');
                }

                if (strtotime($fechaSalida . ' ' . $horaSalida) <= time()) {
                    throw new DomainException('No se pueden reservar vuelos que ya salieron.');
                }

                if ((int) $asientosDisponibles < 1) {
                    throw new DomainException('No quedan asientos disponibles para ese vuelo.');
                }

                $actualizarAsientos = $conexion->prepare(
                    'UPDATE vuelos
                     SET asientosDisponibles = asientosDisponibles - 1
                     WHERE codVuelo = ? AND asientosDisponibles > 0'
                );
                $actualizarAsientos->bind_param('i', $codVuelo);
                $actualizarAsientos->execute();
                $asientoReservado = $actualizarAsientos->affected_rows === 1;
                $actualizarAsientos->close();

                if (!$asientoReservado) {
                    throw new DomainException('No quedan asientos disponibles para ese vuelo.');
                }

                $crearReserva = $conexion->prepare(
                    "INSERT INTO reservas (codUsuario, codVuelo, fechaReserva, precioReserva, estadoReserva)
                     VALUES (?, ?, CURRENT_DATE, ?, 'pendiente de pago')"
                );
                $precioReserva = (float) $precioReserva;
                $crearReserva->bind_param('iid', $codUsuario, $codVuelo, $precioReserva);
                $crearReserva->execute();
                $crearReserva->close();

                $conexion->commit();
                $_SESSION['mensaje_reserva'] = [
                    'tipo' => 'exito',
                    'texto' => 'La reserva quedó pendiente de pago. El asiento se mantendrá reservado hasta que confirmes el pago o canceles la reserva.',
                ];
            } else {
                $codReserva = filter_var($_POST['codReserva'] ?? null, FILTER_VALIDATE_INT);
                if ($codReserva === false || $codReserva === null || $codReserva < 1) {
                    throw new DomainException('La reserva seleccionada no es válida.');
                }

                $actualizarReserva = $conexion->prepare(
                    "UPDATE reservas
                     SET estadoReserva = 'confirmada'
                     WHERE codReserva = ? AND codUsuario = ? AND estadoReserva = 'pendiente de pago'"
                );
                $actualizarReserva->bind_param('ii', $codReserva, $codUsuario);
                $actualizarReserva->execute();
                $reservaActualizada = $actualizarReserva->affected_rows === 1;
                $actualizarReserva->close();

                if (!$reservaActualizada) {
                    throw new DomainException('La reserva no existe, no te pertenece o ya no está pendiente de pago.');
                }

                $conexion->commit();
                $_SESSION['mensaje_reserva'] = [
                    'tipo' => 'exito',
                    'texto' => 'Pago de demostración realizado. La reserva quedó confirmada.',
                ];
            }
        } catch (DomainException $error) {
            if ($conexion instanceof mysqli) {
                $conexion->rollback();
            }
            $_SESSION['mensaje_reserva'] = [
                'tipo' => 'error',
                'texto' => $error->getMessage(),
            ];
        } catch (mysqli_sql_exception $error) {
            if ($conexion instanceof mysqli) {
                $conexion->rollback();
            }
            error_log('Error al gestionar una reserva: ' . $error->getMessage());
            $_SESSION['mensaje_reserva'] = [
                'tipo' => 'error',
                'texto' => 'No se pudo procesar la reserva. Intentá nuevamente más tarde.',
            ];
        } finally {
            if ($conexion instanceof mysqli) {
                $conexion->close();
            }
        }
    }

    header('Location: /Paginas/usuario/reservar_vuelo.php');
    exit;
}

$vuelosDisponibles = [];
$reservasPendientes = [];
$errorCarga = '';
$conexion = null;

try {
    $conexion = conectarBaseDeDatos();

    $consultaVuelos = $conexion->prepare(
        'SELECT v.codVuelo, v.origenVuelo, v.destinoVuelo, v.fechaSalidaVuelo,
                v.horaSalidaVuelo, v.precioVuelo, p.descuentoPromocion,
                ROUND(v.precioVuelo * (100 - COALESCE(p.descuentoPromocion, 0)) / 100, 2) AS precioFinal,
                v.asientosDisponibles, a.nombreAerolinea
         FROM vuelos AS v
         INNER JOIN aerolineas AS a ON a.codAerolinea = v.codAerolinea
         LEFT JOIN promociones AS p
           ON p.codPromocion = (
               SELECT MAX(p2.codPromocion)
               FROM promociones AS p2
               WHERE p2.codAerolinea = v.codAerolinea
                 AND p2.estadoPromocion = \'aprobada\'
                 AND (p2.fechaExpiracionPromocion IS NULL OR p2.fechaExpiracionPromocion >= CURRENT_DATE)
           )
         WHERE TIMESTAMP(v.fechaSalidaVuelo, v.horaSalidaVuelo) > NOW()
           AND v.asientosDisponibles > 0
         ORDER BY v.fechaSalidaVuelo, v.horaSalidaVuelo'
    );
    $consultaVuelos->execute();
    $consultaVuelos->bind_result(
        $codVuelo,
        $origenVuelo,
        $destinoVuelo,
        $fechaSalidaVuelo,
        $horaSalidaVuelo,
        $precioOriginal,
        $descuentoPromocion,
        $precioVuelo,
        $asientosDisponibles,
        $nombreAerolinea
    );

    while ($consultaVuelos->fetch()) {
        $vuelosDisponibles[] = [
            'codVuelo' => $codVuelo,
            'origenVuelo' => $origenVuelo,
            'destinoVuelo' => $destinoVuelo,
            'fechaSalidaVuelo' => $fechaSalidaVuelo,
            'horaSalidaVuelo' => $horaSalidaVuelo,
            'precioVuelo' => $precioVuelo,
            'precioOriginal' => $precioOriginal,
            'descuentoPromocion' => $descuentoPromocion,
            'asientosDisponibles' => $asientosDisponibles,
            'nombreAerolinea' => $nombreAerolinea,
        ];
    }
    $consultaVuelos->close();

    $consultaReservas = $conexion->prepare(
        "SELECT r.codReserva, r.fechaReserva, v.origenVuelo, v.destinoVuelo,
                v.fechaSalidaVuelo, v.horaSalidaVuelo, r.precioReserva, a.nombreAerolinea
         FROM reservas AS r
         INNER JOIN vuelos AS v ON v.codVuelo = r.codVuelo
         INNER JOIN aerolineas AS a ON a.codAerolinea = v.codAerolinea
         WHERE r.codUsuario = ? AND r.estadoReserva = 'pendiente de pago'
         ORDER BY r.fechaReserva DESC, r.codReserva DESC"
    );
    $consultaReservas->bind_param('i', $codUsuario);
    $consultaReservas->execute();
    $consultaReservas->bind_result(
        $codReserva,
        $fechaReserva,
        $origenReservado,
        $destinoReservado,
        $fechaSalidaReservada,
        $horaSalidaReservada,
        $precioReservado,
        $aerolineaReservada
    );

    while ($consultaReservas->fetch()) {
        $reservasPendientes[] = [
            'codReserva' => $codReserva,
            'fechaReserva' => $fechaReserva,
            'origenVuelo' => $origenReservado,
            'destinoVuelo' => $destinoReservado,
            'fechaSalidaVuelo' => $fechaSalidaReservada,
            'horaSalidaVuelo' => $horaSalidaReservada,
            'precioVuelo' => $precioReservado,
            'nombreAerolinea' => $aerolineaReservada,
        ];
    }
    $consultaReservas->close();
} catch (mysqli_sql_exception $error) {
    error_log('Error al cargar vuelos y reservas pendientes: ' . $error->getMessage());
    $errorCarga = 'No se pudieron cargar los vuelos y las reservas. Intentá nuevamente más tarde.';
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
    <title>Reservar un vuelo | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../plantilla/encabezado.php'; ?>

    <main class="contenido-principal reservar-vuelo" aria-labelledby="titulo-reservar-vuelo">
        <header class="reservar-vuelo-encabezado">
            <p class="autenticacion-etiqueta">Planeá tu viaje</p>
            <h1 id="titulo-reservar-vuelo">Reservar un vuelo</h1>
            <p>La reserva comienza pendiente de pago. Al pagar, pasa a ser una compra confirmada.</p>
        </header>

        <?php if ($mensajeReserva !== ''): ?>
            <p class="mensaje-reserva <?= $tipoMensajeReserva === 'error' ? 'mensaje-reserva-error' : 'mensaje-reserva-exito' ?>" role="<?= $tipoMensajeReserva === 'error' ? 'alert' : 'status' ?>">
                <?= $escapar($mensajeReserva) ?>
            </p>
        <?php endif; ?>

        <?php if ($errorCarga !== ''): ?>
            <p class="mensaje-autenticacion" role="alert"><?= $escapar($errorCarga) ?></p>
        <?php else: ?>
            <?php if ($reservasPendientes !== []): ?>
                <section class="reservas-pendientes" aria-labelledby="titulo-pendientes">
                    <h2 id="titulo-pendientes">Tus reservas pendientes de pago</h2>
                    <p class="aviso-pago-demostracion">Demostración: el botón “Pagar” confirma la reserva, pero no realiza un cobro real.</p>
                    <div class="reservas-pendientes-lista">
                        <?php foreach ($reservasPendientes as $reserva): ?>
                            <article class="reserva-pendiente">
                                <div>
                                    <h3><?= $escapar((string) $reserva['origenVuelo']) ?> → <?= $escapar((string) $reserva['destinoVuelo']) ?></h3>
                                    <p>Reserva #<?= (int) $reserva['codReserva'] ?> · <?= $escapar((string) $reserva['nombreAerolinea']) ?></p>
                                    <p>Salida: <?= $escapar($formatearFecha((string) $reserva['fechaSalidaVuelo'])) ?> · <?= $escapar(substr((string) $reserva['horaSalidaVuelo'], 0, 5)) ?></p>
                                    <p>Precio reservado: $ <?= number_format((float) $reserva['precioVuelo'], 2, ',', '.') ?></p>
                                </div>
                                <div class="reserva-pendiente-acciones">
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= $escapar((string) $_SESSION['csrf_auth']) ?>">
                                        <input type="hidden" name="accion" value="pagar">
                                        <input type="hidden" name="codReserva" value="<?= (int) $reserva['codReserva'] ?>">
                                        <button class="boton-reserva boton-pagar" type="submit">Pagar (demostración)</button>
                                    </form>
                                    <a class="enlace-gestion-reservas" href="/Paginas/usuario/gestion_reservas.php">Gestionar o cancelar</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="vuelos-para-reservar" aria-labelledby="titulo-vuelos-reservar">
                <h2 id="titulo-vuelos-reservar">Vuelos disponibles</h2>
                <?php if ($vuelosDisponibles === []): ?>
                    <div class="vuelos-reserva-vacio">
                        <p>No hay vuelos futuros con asientos disponibles en este momento.</p>
                        <a href="/Paginas/Info/vuelos.php">Consultar información de vuelos</a>
                    </div>
                <?php else: ?>
                    <div class="vuelos-reserva-lista">
                        <?php foreach ($vuelosDisponibles as $vuelo): ?>
                            <article class="vuelo-reserva">
                                <div class="vuelo-reserva-detalle">
                                    <p class="vuelo-reserva-aerolinea"><?= $escapar((string) $vuelo['nombreAerolinea']) ?></p>
                                    <h3><?= $escapar((string) $vuelo['origenVuelo']) ?> <span aria-hidden="true">→</span> <?= $escapar((string) $vuelo['destinoVuelo']) ?></h3>
                                    <p>Salida: <?= $escapar($formatearFecha((string) $vuelo['fechaSalidaVuelo'])) ?> · <?= $escapar(substr((string) $vuelo['horaSalidaVuelo'], 0, 5)) ?></p>
                                    <p><?= (int) $vuelo['asientosDisponibles'] ?> <?= (int) $vuelo['asientosDisponibles'] === 1 ? 'asiento disponible' : 'asientos disponibles' ?></p>
                                </div>
                                <div class="vuelo-reserva-compra">
                                    <?php if ($vuelo['descuentoPromocion'] !== null): ?>
                                        <p><del>$ <?= number_format((float) $vuelo['precioOriginal'], 2, ',', '.') ?></del> $ <?= number_format((float) $vuelo['precioVuelo'], 2, ',', '.') ?></p>
                                        <p>Promoción: -<?= number_format((float) $vuelo['descuentoPromocion'], 2, ',', '.') ?>%</p>
                                    <?php else: ?>
                                        <p>$ <?= number_format((float) $vuelo['precioVuelo'], 2, ',', '.') ?></p>
                                    <?php endif; ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= $escapar((string) $_SESSION['csrf_auth']) ?>">
                                        <input type="hidden" name="accion" value="reservar">
                                        <input type="hidden" name="codVuelo" value="<?= (int) $vuelo['codVuelo'] ?>">
                                        <button class="boton-reserva boton-pagar" type="submit">Reservar</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../plantilla/pie_pagina.php'; ?>
</body>
</html>