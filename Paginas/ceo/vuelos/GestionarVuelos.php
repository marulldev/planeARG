<?php
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/conexion.php';

if (!isset($_SESSION['codUsuario'])) {
    header('Location: /Paginas/autenticacion/iniciar_sesion.php');
    exit;
}

if (($_SESSION['tipoUsuario'] ?? '') !== 'ceo de aerolinea') {
    if (($_SESSION['tipoUsuario'] ?? '') === 'administrador') {
        header('Location: /Paginas/admin/menu_admin.php');
    } elseif (($_SESSION['tipoUsuario'] ?? '') === 'usuario') {
        header('Location: /Paginas/usuario/menu_usuario.php');
    } else {
        header('Location: /');
    }
    exit;
}

$codUsuario = (int) $_SESSION['codUsuario'];
$csrfToken = (string) $_SESSION['csrf_auth'];
$mensaje = '';
$tipoMensaje = 'exito';
$errorCarga = '';
$aerolinea = null;
$vuelos = [];
$idEdicion = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
$modoEdicion = $idEdicion !== null && $idEdicion !== false && $idEdicion > 0;
$vueloEdicion = null;
$erroresFormulario = [];
$valoresFormulario = [
    'origenVuelo' => '',
    'destinoVuelo' => '',
    'fechaSalidaVuelo' => '',
    'horaSalidaVuelo' => '',
    'precioVuelo' => '',
    'capacidadTotal' => '',
];
$longitudTexto = static fn(string $texto): int => function_exists('mb_strlen')
    ? mb_strlen($texto, 'UTF-8')
    : strlen($texto);

if (isset($_SESSION['mensaje_gestion_vuelos'])) {
    $mensaje = (string) ($_SESSION['mensaje_gestion_vuelos']['texto'] ?? '');
    $tipoMensaje = (string) ($_SESSION['mensaje_gestion_vuelos']['tipo'] ?? 'exito');
    unset($_SESSION['mensaje_gestion_vuelos']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');
    $tokenEnviado = (string) ($_POST['csrf_token'] ?? '');
    $conexion = null;
    $transaccionIniciada = false;
    $redirigirDespuesDelPost = false;

    if (!hash_equals($csrfToken, $tokenEnviado)) {
        $_SESSION['mensaje_gestion_vuelos'] = [
            'tipo' => 'error',
            'texto' => 'La sesión venció. Recargá la página e intentá nuevamente.',
        ];
        header('Location: /Paginas/ceo/vuelos/GestionarVuelos.php');
        exit;
    }

    try {
        $conexion = conectarBaseDeDatos();
        $consultaAerolinea = $conexion->prepare(
            'SELECT codAerolinea
             FROM aerolineas
             WHERE codUsuario = ?
             LIMIT 1'
        );
        $consultaAerolinea->bind_param('i', $codUsuario);
        $consultaAerolinea->execute();
        $consultaAerolinea->bind_result($codAerolinea);
        $aerolineaEncontrada = $consultaAerolinea->fetch();
        $consultaAerolinea->close();

        if (!$aerolineaEncontrada) {
            throw new DomainException('Tu cuenta no está asociada a una aerolínea. Pedile al administrador que complete la asignación.');
        }
        $codAerolinea = (int) $codAerolinea;

        if ($accion === 'eliminar') {
            $idVuelo = filter_var($_POST['codVuelo'] ?? null, FILTER_VALIDATE_INT);
            if ($idVuelo === false || $idVuelo === null || $idVuelo < 1) {
                throw new DomainException('El código del vuelo no es válido.');
            }

            $conexion->begin_transaction();
            $transaccionIniciada = true;
            $consultaVuelo = $conexion->prepare(
                'SELECT codVuelo
                 FROM vuelos
                 WHERE codVuelo = ? AND codAerolinea = ?
                 FOR UPDATE'
            );
            $consultaVuelo->bind_param('ii', $idVuelo, $codAerolinea);
            $consultaVuelo->execute();
            $consultaVuelo->bind_result($codVueloEncontrado);
            $vueloEncontrado = $consultaVuelo->fetch();
            $consultaVuelo->close();

            if (!$vueloEncontrado) {
                throw new DomainException('El vuelo no existe o no pertenece a tu aerolínea.');
            }

            $consultaReservas = $conexion->prepare(
                'SELECT COUNT(*) FROM reservas WHERE codVuelo = ?'
            );
            $consultaReservas->bind_param('i', $idVuelo);
            $consultaReservas->execute();
            $consultaReservas->bind_result($cantidadReservas);
            $consultaReservas->fetch();
            $consultaReservas->close();

            if ((int) $cantidadReservas > 0) {
                throw new DomainException('No se puede eliminar un vuelo que ya tiene reservas asociadas.');
            }

            $eliminar = $conexion->prepare(
                'DELETE FROM vuelos WHERE codVuelo = ? AND codAerolinea = ?'
            );
            $eliminar->bind_param('ii', $idVuelo, $codAerolinea);
            $eliminar->execute();
            $eliminado = $eliminar->affected_rows === 1;
            $eliminar->close();

            if (!$eliminado) {
                throw new DomainException('No se pudo eliminar el vuelo. Actualizá la página e intentá nuevamente.');
            }

            $conexion->commit();
            $transaccionIniciada = false;
            $_SESSION['mensaje_gestion_vuelos'] = [
                'tipo' => 'exito',
                'texto' => 'El vuelo #' . $idVuelo . ' fue eliminado.',
            ];
            $redirigirDespuesDelPost = true;
        } elseif ($accion === 'guardar') {
            $idVueloIngresado = trim((string) ($_POST['codVuelo'] ?? ''));
            $idVuelo = $idVueloIngresado === ''
                ? null
                : filter_var($idVueloIngresado, FILTER_VALIDATE_INT);
            if ($idVueloIngresado !== '' && ($idVuelo === false || $idVuelo < 1)) {
                throw new DomainException('El código del vuelo no es válido.');
            }
            $esEdicion = $idVuelo !== null;
            $origen = trim((string) ($_POST['origenVuelo'] ?? ''));
            $destino = trim((string) ($_POST['destinoVuelo'] ?? ''));
            $fecha = trim((string) ($_POST['fechaSalidaVuelo'] ?? ''));
            $hora = trim((string) ($_POST['horaSalidaVuelo'] ?? ''));
            $precioIngresado = trim((string) ($_POST['precioVuelo'] ?? ''));
            $capacidadIngresada = trim((string) ($_POST['capacidadTotal'] ?? ''));

            $valoresFormulario = [
                'origenVuelo' => $origen,
                'destinoVuelo' => $destino,
                'fechaSalidaVuelo' => $fecha,
                'horaSalidaVuelo' => $hora,
                'precioVuelo' => $precioIngresado,
                'capacidadTotal' => $capacidadIngresada,
            ];
            $idEdicion = $esEdicion ? $idVuelo : null;
            $modoEdicion = $esEdicion;

            if ($origen === '' || $longitudTexto($origen) > 50) {
                $erroresFormulario[] = 'El origen es obligatorio y debe tener hasta 50 caracteres.';
            }
            if ($destino === '' || $longitudTexto($destino) > 50) {
                $erroresFormulario[] = 'El destino es obligatorio y debe tener hasta 50 caracteres.';
            }
            if ($origen !== '' && $destino !== '' && strcasecmp($origen, $destino) === 0) {
                $erroresFormulario[] = 'El origen y el destino deben ser diferentes.';
            }

            $fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
            if ($fechaValida === false || $fechaValida->format('Y-m-d') !== $fecha) {
                $erroresFormulario[] = 'Ingresá una fecha de salida válida.';
            }

            $horaValida = DateTimeImmutable::createFromFormat('!H:i', $hora);
            if ($horaValida === false || $horaValida->format('H:i') !== $hora) {
                $erroresFormulario[] = 'Ingresá una hora de salida válida.';
            }

            if ($fechaValida !== false && $horaValida !== false) {
                $salida = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $fecha . ' ' . $hora);
                if ($salida === false || $salida <= new DateTimeImmutable()) {
                    $erroresFormulario[] = 'La salida del vuelo debe ser posterior a la fecha y hora actuales.';
                }
            }

            if (!preg_match('/\A\d{1,8}(?:\.\d{1,2})?\z/', $precioIngresado) || (float) $precioIngresado <= 0) {
                $erroresFormulario[] = 'El precio debe ser mayor que cero y tener hasta dos decimales.';
            }

            if (!preg_match('/\A\d{1,9}\z/', $capacidadIngresada) || (int) $capacidadIngresada < 1) {
                $erroresFormulario[] = 'La capacidad total debe ser un número entero mayor que cero.';
            }

            if ($erroresFormulario !== []) {
                throw new DomainException(implode(' ', $erroresFormulario));
            }

            $precio = (float) $precioIngresado;
            $capacidadTotal = (int) $capacidadIngresada;
            $conexion->begin_transaction();
            $transaccionIniciada = true;

            if ($esEdicion) {
                $bloquearVuelo = $conexion->prepare(
                    'SELECT codVuelo
                     FROM vuelos
                     WHERE codVuelo = ? AND codAerolinea = ?
                     FOR UPDATE'
                );
                $bloquearVuelo->bind_param('ii', $idVuelo, $codAerolinea);
                $bloquearVuelo->execute();
                $bloquearVuelo->bind_result($codVueloEncontrado);
                $vueloEncontrado = $bloquearVuelo->fetch();
                $bloquearVuelo->close();

                if (!$vueloEncontrado) {
                    throw new DomainException('El vuelo no existe o no pertenece a tu aerolínea.');
                }

                $consultaReservas = $conexion->prepare(
                    'SELECT COUNT(*) FROM reservas WHERE codVuelo = ?'
                );
                $consultaReservas->bind_param('i', $idVuelo);
                $consultaReservas->execute();
                $consultaReservas->bind_result($cantidadReservas);
                $consultaReservas->fetch();
                $consultaReservas->close();

                if ((int) $cantidadReservas > 0) {
                    throw new DomainException('No se puede modificar un vuelo que ya tiene reservas asociadas.');
                }

                $actualizar = $conexion->prepare(
                    'UPDATE vuelos
                     SET origenVuelo = ?, destinoVuelo = ?, fechaSalidaVuelo = ?,
                         horaSalidaVuelo = ?, precioVuelo = ?, asientosDisponibles = ?,
                         capacidadTotal = ?
                     WHERE codVuelo = ? AND codAerolinea = ?'
                );
                $actualizar->bind_param(
                    'ssssdiiii',
                    $origen,
                    $destino,
                    $fecha,
                    $hora,
                    $precio,
                    $capacidadTotal,
                    $capacidadTotal,
                    $idVuelo,
                    $codAerolinea
                );
                $actualizar->execute();
                $actualizar->close();
                $textoExito = 'El vuelo #' . $idVuelo . ' fue actualizado.';
            } else {
                $crear = $conexion->prepare(
                    'INSERT INTO vuelos
                        (codAerolinea, origenVuelo, destinoVuelo, fechaSalidaVuelo,
                         horaSalidaVuelo, precioVuelo, asientosDisponibles, capacidadTotal)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $crear->bind_param(
                    'issssdii',
                    $codAerolinea,
                    $origen,
                    $destino,
                    $fecha,
                    $hora,
                    $precio,
                    $capacidadTotal,
                    $capacidadTotal
                );
                $crear->execute();
                $idVueloCreado = $conexion->insert_id;
                $crear->close();
                $textoExito = 'El vuelo #' . $idVueloCreado . ' fue creado.';
            }

            $conexion->commit();
            $transaccionIniciada = false;
            $_SESSION['mensaje_gestion_vuelos'] = [
                'tipo' => 'exito',
                'texto' => $textoExito,
            ];
            $redirigirDespuesDelPost = true;
        } else {
            throw new DomainException('La acción solicitada no es válida.');
        }
    } catch (DomainException $error) {
        if ($conexion instanceof mysqli && $transaccionIniciada) {
            $conexion->rollback();
            $transaccionIniciada = false;
        }

        if ($accion === 'guardar' && isset($valoresFormulario)) {
            $mensaje = $error->getMessage();
            $tipoMensaje = 'error';
        } else {
            $_SESSION['mensaje_gestion_vuelos'] = [
                'tipo' => 'error',
                'texto' => $error->getMessage(),
            ];
            $redirigirDespuesDelPost = true;
        }
    } catch (mysqli_sql_exception $error) {
        if ($conexion instanceof mysqli && $transaccionIniciada) {
            $conexion->rollback();
            $transaccionIniciada = false;
        }
        error_log('Error al gestionar vuelos: ' . $error->getMessage());
        $mensaje = 'No se pudo procesar la operación. Verificá la conexión y los datos de la base de datos.';
        $tipoMensaje = 'error';
    } finally {
        if ($conexion instanceof mysqli) {
            $conexion->close();
        }
    }

    if ($redirigirDespuesDelPost) {
        header('Location: /Paginas/ceo/vuelos/GestionarVuelos.php');
        exit;
    }
}

$conexion = null;
try {
    $conexion = conectarBaseDeDatos();
    $consultaAerolinea = $conexion->prepare(
        'SELECT codAerolinea, nombreAerolinea
         FROM aerolineas
         WHERE codUsuario = ?
         LIMIT 1'
    );
    $consultaAerolinea->bind_param('i', $codUsuario);
    $consultaAerolinea->execute();
    $consultaAerolinea->bind_result($codAerolinea, $nombreAerolinea);
    if ($consultaAerolinea->fetch()) {
        $aerolinea = [
            'codAerolinea' => (int) $codAerolinea,
            'nombreAerolinea' => (string) $nombreAerolinea,
        ];
    }
    $consultaAerolinea->close();

    if ($aerolinea !== null) {
        $codAerolineaConsulta = $aerolinea['codAerolinea'];
        $consultaVuelos = $conexion->prepare(
            'SELECT v.codVuelo, v.origenVuelo, v.destinoVuelo, v.fechaSalidaVuelo,
                    v.horaSalidaVuelo, v.precioVuelo, v.asientosDisponibles, v.capacidadTotal,
                    (SELECT COUNT(*) FROM reservas AS r WHERE r.codVuelo = v.codVuelo) AS cantidadReservas
             FROM vuelos AS v
             WHERE v.codAerolinea = ?
             ORDER BY v.fechaSalidaVuelo DESC, v.horaSalidaVuelo DESC, v.codVuelo DESC'
        );
        $consultaVuelos->bind_param('i', $codAerolineaConsulta);
        $consultaVuelos->execute();
        $consultaVuelos->bind_result(
            $codVuelo,
            $origenVuelo,
            $destinoVuelo,
            $fechaSalidaVuelo,
            $horaSalidaVuelo,
            $precioVuelo,
            $asientosDisponibles,
            $capacidadTotalVuelo,
            $cantidadReservas
        );

        while ($consultaVuelos->fetch()) {
            $vuelos[] = [
                'codVuelo' => $codVuelo,
                'origenVuelo' => $origenVuelo,
                'destinoVuelo' => $destinoVuelo,
                'fechaSalidaVuelo' => $fechaSalidaVuelo,
                'horaSalidaVuelo' => $horaSalidaVuelo,
                'precioVuelo' => $precioVuelo,
                'asientosDisponibles' => $asientosDisponibles,
                'capacidadTotal' => $capacidadTotalVuelo,
                'cantidadReservas' => (int) $cantidadReservas,
            ];
        }
        $consultaVuelos->close();

        if ($modoEdicion) {
            $consultaEdicion = $conexion->prepare(
                'SELECT codVuelo, origenVuelo, destinoVuelo, fechaSalidaVuelo,
                        horaSalidaVuelo, precioVuelo, capacidadTotal
                 FROM vuelos
                 WHERE codVuelo = ? AND codAerolinea = ?'
            );
            $consultaEdicion->bind_param('ii', $idEdicion, $codAerolineaConsulta);
            $consultaEdicion->execute();
            $consultaEdicion->bind_result(
                $codVueloEdicion,
                $origenEdicion,
                $destinoEdicion,
                $fechaEdicion,
                $horaEdicion,
                $precioEdicion,
                $capacidadEdicion
            );

            if ($consultaEdicion->fetch()) {
                $vueloEdicion = [
                    'codVuelo' => $codVueloEdicion,
                    'origenVuelo' => $origenEdicion,
                    'destinoVuelo' => $destinoEdicion,
                    'fechaSalidaVuelo' => $fechaEdicion,
                    'horaSalidaVuelo' => substr((string) $horaEdicion, 0, 5),
                    'precioVuelo' => $precioEdicion,
                    'capacidadTotal' => $capacidadEdicion,
                ];
                if ($mensaje === '') {
                    $valoresFormulario = [
                        'origenVuelo' => (string) $origenEdicion,
                        'destinoVuelo' => (string) $destinoEdicion,
                        'fechaSalidaVuelo' => (string) $fechaEdicion,
                        'horaSalidaVuelo' => substr((string) $horaEdicion, 0, 5),
                        'precioVuelo' => (string) $precioEdicion,
                        'capacidadTotal' => (string) $capacidadEdicion,
                    ];
                }
            } else {
                $modoEdicion = false;
                $errorCarga = 'El vuelo no existe o no pertenece a tu aerolínea.';
            }
            $consultaEdicion->close();
        }
    }
} catch (mysqli_sql_exception $error) {
    error_log('Error al cargar vuelos de la aerolínea: ' . $error->getMessage());
    $errorCarga = 'No se pudieron cargar los vuelos. Verificá que la base activa tenga aerolíneas.codUsuario asignado a tu cuenta CEO.';
} finally {
    if ($conexion instanceof mysqli) {
        $conexion->close();
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$urlCancelarEdicion = '/Paginas/ceo/vuelos/GestionarVuelos.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar vuelos | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../plantilla/encabezado.php'; ?>

    <main class="contenido-principal gestion-vuelos" aria-labelledby="titulo-gestion-vuelos">
        <header class="gestion-vuelos-encabezado">
            <p class="autenticacion-etiqueta">Gestión de aerolínea</p>
            <h1 id="titulo-gestion-vuelos">Gestionar vuelos</h1>
            <?php if ($aerolinea !== null): ?>
                <p>Vuelos de <?= $escapar($aerolinea['nombreAerolinea']) ?>. El código se asigna automáticamente y no se puede modificar.</p>
            <?php else: ?>
                <p>Los vuelos se asocian automáticamente a la aerolínea asignada a tu cuenta.</p>
            <?php endif; ?>
        </header>

        <?php if ($mensaje !== ''): ?>
            <p class="mensaje-reserva <?= $tipoMensaje === 'error' ? 'mensaje-reserva-error' : 'mensaje-reserva-exito' ?>" role="<?= $tipoMensaje === 'error' ? 'alert' : 'status' ?>">
                <?= $escapar($mensaje) ?>
            </p>
        <?php endif; ?>

        <?php if ($errorCarga !== ''): ?>
            <p class="mensaje-autenticacion" role="alert"><?= $escapar($errorCarga) ?></p>
        <?php elseif ($aerolinea === null): ?>
            <section class="gestion-vuelos-vacio">
                <h2>No hay una aerolínea asignada</h2>
                <p>Pedile al administrador que asocie tu cuenta CEO con una aerolínea antes de gestionar vuelos.</p>
            </section>
        <?php else: ?>
            <section class="formulario-vuelo-seccion" aria-labelledby="titulo-formulario-vuelo">
                <h2 id="titulo-formulario-vuelo"><?= $modoEdicion ? 'Editar vuelo #' . (int) $idEdicion : 'Crear vuelo' ?></h2>
                <?php if ($modoEdicion && $vueloEdicion !== null && ($vueloEdicion['codVuelo'] ?? 0) > 0): ?>
                    <?php
                    $vueloTieneReservas = false;
                    foreach ($vuelos as $vueloListado) {
                        if ((int) $vueloListado['codVuelo'] === (int) $vueloEdicion['codVuelo']) {
                            $vueloTieneReservas = $vueloListado['cantidadReservas'] > 0;
                            break;
                        }
                    }
                    ?>
                    <?php if ($vueloTieneReservas): ?>
                        <p class="aviso-vuelo-reservado">Este vuelo ya tiene reservas asociadas y no se puede editar.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <?php $vueloTieneReservas = false; ?>
                <?php endif; ?>
                <form class="formulario-vuelo" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $escapar($csrfToken) ?>">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="codVuelo" value="<?= $modoEdicion && $idEdicion !== false ? (int) $idEdicion : '' ?>">

                    <div class="campo-vuelo">
                        <label for="origenVuelo">Origen</label>
                        <input id="origenVuelo" name="origenVuelo" type="text" maxlength="50" value="<?= $escapar($valoresFormulario['origenVuelo']) ?>" required>
                    </div>
                    <div class="campo-vuelo">
                        <label for="destinoVuelo">Destino</label>
                        <input id="destinoVuelo" name="destinoVuelo" type="text" maxlength="50" value="<?= $escapar($valoresFormulario['destinoVuelo']) ?>" required>
                    </div>
                    <div class="campo-vuelo">
                        <label for="fechaSalidaVuelo">Fecha de salida</label>
                        <input id="fechaSalidaVuelo" name="fechaSalidaVuelo" type="date" min="<?= date('Y-m-d') ?>" value="<?= $escapar($valoresFormulario['fechaSalidaVuelo']) ?>" required>
                    </div>
                    <div class="campo-vuelo">
                        <label for="horaSalidaVuelo">Hora de salida</label>
                        <input id="horaSalidaVuelo" name="horaSalidaVuelo" type="time" value="<?= $escapar($valoresFormulario['horaSalidaVuelo']) ?>" required>
                    </div>
                    <div class="campo-vuelo">
                        <label for="precioVuelo">Precio</label>
                        <input id="precioVuelo" name="precioVuelo" type="number" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" value="<?= $escapar($valoresFormulario['precioVuelo']) ?>" required>
                    </div>
                    <div class="campo-vuelo">
                        <label for="capacidadTotal">Capacidad total de asientos</label>
                        <input id="capacidadTotal" name="capacidadTotal" type="number" min="1" max="999999999" step="1" value="<?= $escapar($valoresFormulario['capacidadTotal']) ?>" required>
                        <small>Al crear el vuelo, todos los asientos comienzan disponibles.</small>
                    </div>
                    <?php if ($mensaje !== '' && $tipoMensaje === 'error' && $erroresFormulario !== []): ?>
                        <p class="nota-vuelo-error" role="alert"><?= $escapar($mensaje) ?></p>
                    <?php endif; ?>
                    <div class="acciones-formulario-vuelo">
                        <?php if ($modoEdicion): ?>
                            <a class="cancelar-aerolinea" href="<?= $urlCancelarEdicion ?>">Cancelar edición</a>
                        <?php endif; ?>
                        <button class="boton-reserva boton-pagar" type="submit" <?= $vueloTieneReservas ? 'disabled' : '' ?>>
                            <?= $modoEdicion ? 'Guardar cambios' : 'Crear vuelo' ?>
                        </button>
                    </div>
                </form>
            </section>

            <section class="lista-vuelos-gestion" aria-labelledby="titulo-lista-vuelos">
                <h2 id="titulo-lista-vuelos">Vuelos de la aerolínea</h2>
                <?php if ($vuelos === []): ?>
                    <div class="gestion-vuelos-vacio">
                        <p>Todavía no hay vuelos registrados para esta aerolínea.</p>
                    </div>
                <?php else: ?>
                    <div class="lista-vuelos-elementos">
                        <?php foreach ($vuelos as $vuelo): ?>
                            <article class="vuelo-gestion-elemento">
                                <div class="vuelo-gestion-detalle">
                                    <p class="vuelo-gestion-codigo">Vuelo #<?= (int) $vuelo['codVuelo'] ?></p>
                                    <h3><?= $escapar((string) $vuelo['origenVuelo']) ?> <span aria-hidden="true">→</span> <?= $escapar((string) $vuelo['destinoVuelo']) ?></h3>
                                    <p>Salida: <?= $escapar(date('d/m/Y', strtotime((string) $vuelo['fechaSalidaVuelo']))) ?> · <?= $escapar(substr((string) $vuelo['horaSalidaVuelo'], 0, 5)) ?></p>
                                    <p>Precio: $ <?= number_format((float) $vuelo['precioVuelo'], 2, ',', '.') ?> · Asientos disponibles: <?= (int) $vuelo['asientosDisponibles'] ?> / <?= (int) $vuelo['capacidadTotal'] ?> totales</p>
                                    <?php if ($vuelo['cantidadReservas'] > 0): ?>
                                        <p class="vuelo-gestion-reservado"><?= (int) $vuelo['cantidadReservas'] ?> <?= $vuelo['cantidadReservas'] === 1 ? 'reserva asociada' : 'reservas asociadas' ?>; no se puede editar ni eliminar.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="acciones-vuelo-gestion">
                                    <?php if ($vuelo['cantidadReservas'] === 0): ?>
                                        <a class="enlace-editar-vuelo" href="?editar=<?= (int) $vuelo['codVuelo'] ?>">Editar</a>
                                        <form method="post" onsubmit="return confirm('¿Querés eliminar este vuelo?');">
                                            <input type="hidden" name="csrf_token" value="<?= $escapar($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="codVuelo" value="<?= (int) $vuelo['codVuelo'] ?>">
                                            <button class="boton-reserva boton-cancelar" type="submit">Eliminar</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="vuelo-gestion-protegido">Vuelo con reservas protegido</span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../../plantilla/pie_pagina.php'; ?>
</body>
</html>