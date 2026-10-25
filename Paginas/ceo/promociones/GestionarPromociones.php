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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $descripcion = trim((string) ($_POST['descripcionPromocion'] ?? ''));
    $descuentoIngresado = trim((string) ($_POST['descuentoPromocion'] ?? ''));

    if (!hash_equals((string) $_SESSION['csrf_auth'], (string) ($_POST['csrf_token'] ?? ''))) {
        $_SESSION['mensaje_promociones_ceo'] = [
            'tipo' => 'error',
            'texto' => 'La sesión venció. Recargá la página e intentá nuevamente.',
        ];
    } elseif ($descripcion === '' || (function_exists('mb_strlen') ? mb_strlen($descripcion, 'UTF-8') : strlen($descripcion)) > 200) {
        $_SESSION['mensaje_promociones_ceo'] = [
            'tipo' => 'error',
            'texto' => 'La descripción es obligatoria y debe tener como máximo 200 caracteres.',
        ];
    } elseif (!preg_match('/\A(?:100(?:\.0{1,2})?|(?:\d{1,2})(?:\.\d{1,2})?)\z/', $descuentoIngresado)
        || (float) $descuentoIngresado <= 0
        || (float) $descuentoIngresado > 100) {
        $_SESSION['mensaje_promociones_ceo'] = [
            'tipo' => 'error',
            'texto' => 'Ingresá un descuento mayor que 0 y de hasta 100, con un máximo de dos decimales.',
        ];
    } else {
        $conexion = null;

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
                $_SESSION['mensaje_promociones_ceo'] = [
                    'tipo' => 'error',
                    'texto' => 'Tu cuenta no está asociada a una aerolínea. Pedile al administrador que complete la asignación.',
                ];
            } else {
                $descuento = (float) $descuentoIngresado;
                $consulta = $conexion->prepare(
                    "INSERT INTO promociones
                        (descripcionPromocion, descuentoPromocion, codAerolinea, estadoPromocion)
                     VALUES (?, ?, ?, 'pendiente')"
                );
                $consulta->bind_param('sdi', $descripcion, $descuento, $codAerolinea);
                $consulta->execute();
                $codPromocion = $conexion->insert_id;
                $consulta->close();

                $_SESSION['mensaje_promociones_ceo'] = [
                    'tipo' => 'exito',
                    'texto' => 'La promoción #' . $codPromocion . ' fue creada y quedó pendiente de aprobación del administrador.',
                ];
            }
        } catch (mysqli_sql_exception $error) {
            error_log('Error al crear una promoción: ' . $error->getMessage());
            $_SESSION['mensaje_promociones_ceo'] = [
                'tipo' => 'error',
                'texto' => 'No se pudo crear la promoción. Verificá la asociación de la aerolínea y volvé a intentarlo.',
            ];
        } finally {
            if ($conexion instanceof mysqli) {
                $conexion->close();
            }
        }
    }

    header('Location: /Paginas/ceo/promociones/GestionarPromociones.php');
    exit;
}

$mensajePromociones = '';
$tipoMensaje = 'exito';
if (isset($_SESSION['mensaje_promociones_ceo'])) {
    $mensajePromociones = (string) ($_SESSION['mensaje_promociones_ceo']['texto'] ?? '');
    $tipoMensaje = (string) ($_SESSION['mensaje_promociones_ceo']['tipo'] ?? 'exito');
    unset($_SESSION['mensaje_promociones_ceo']);
}

$aerolinea = null;
$promociones = [];
$errorCarga = '';
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
        $consultaPromociones = $conexion->prepare(
            'SELECT codPromocion, descripcionPromocion, descuentoPromocion, estadoPromocion
             FROM promociones
             WHERE codAerolinea = ?
             ORDER BY codPromocion DESC'
        );
        $consultaPromociones->bind_param('i', $codAerolineaConsulta);
        $consultaPromociones->execute();
        $consultaPromociones->bind_result(
            $codPromocion,
            $descripcionPromocion,
            $descuentoPromocion,
            $estadoPromocion
        );

        while ($consultaPromociones->fetch()) {
            $promociones[] = [
                'codPromocion' => $codPromocion,
                'descripcionPromocion' => $descripcionPromocion,
                'descuentoPromocion' => $descuentoPromocion,
                'estadoPromocion' => $estadoPromocion,
            ];
        }
        $consultaPromociones->close();
    }
} catch (mysqli_sql_exception $error) {
    error_log('Error al cargar promociones de la aerolínea: ' . $error->getMessage());
    $errorCarga = 'No se pudieron cargar las promociones. Verificá que la tabla de aerolíneas tenga asignado el CEO y volvé a intentarlo.';
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
    <title>Gestionar promociones | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../plantilla/encabezado.php'; ?>

    <main class="contenido-principal gestion-promociones" aria-labelledby="titulo-promociones">
        <header class="gestion-promociones-encabezado">
            <p class="autenticacion-etiqueta">Gestión de aerolínea</p>
            <h1 id="titulo-promociones">Gestionar promociones</h1>
            <?php if ($aerolinea !== null): ?>
                <p>Promociones de <?= $escapar($aerolinea['nombreAerolinea']) ?>. Las nuevas promociones requieren aprobación del administrador.</p>
            <?php else: ?>
                <p>Las promociones se asocian automáticamente a la aerolínea asignada a tu cuenta.</p>
            <?php endif; ?>
        </header>

        <?php if ($mensajePromociones !== ''): ?>
            <p class="mensaje-reserva <?= $tipoMensaje === 'error' ? 'mensaje-reserva-error' : 'mensaje-reserva-exito' ?>" role="<?= $tipoMensaje === 'error' ? 'alert' : 'status' ?>">
                <?= $escapar($mensajePromociones) ?>
            </p>
        <?php endif; ?>

        <?php if ($errorCarga !== ''): ?>
            <p class="mensaje-autenticacion" role="alert"><?= $escapar($errorCarga) ?></p>
        <?php elseif ($aerolinea === null): ?>
            <section class="gestion-promociones-vacio">
                <h2>No hay una aerolínea asignada</h2>
                <p>Pedile al administrador que asocie tu cuenta CEO con una aerolínea antes de crear promociones.</p>
            </section>
        <?php else: ?>
            <section class="formulario-promocion-seccion" aria-labelledby="titulo-nueva-promocion">
                <h2 id="titulo-nueva-promocion">Crear promoción</h2>
                <form class="formulario-promocion" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $escapar((string) $_SESSION['csrf_auth']) ?>">
                    <div class="campo-promocion">
                        <label for="descripcionPromocion">Descripción</label>
                        <textarea id="descripcionPromocion" name="descripcionPromocion" maxlength="200" rows="3" required></textarea>
                    </div>
                    <div class="campo-promocion campo-promocion-descuento">
                        <label for="descuentoPromocion">Descuento (%)</label>
                        <input id="descuentoPromocion" name="descuentoPromocion" type="number" min="0.01" max="100" step="0.01" inputmode="decimal" required>
                    </div>
                    <p class="nota-promocion-pendiente">Al crearla, quedará pendiente y no se podrá aplicar hasta que un administrador la apruebe.</p>
                    <button class="boton-reserva boton-pagar" type="submit">Enviar a aprobación</button>
                </form>
            </section>

            <section class="lista-promociones" aria-labelledby="titulo-lista-promociones">
                <h2 id="titulo-lista-promociones">Promociones de la aerolínea</h2>
                <?php if ($promociones === []): ?>
                    <div class="gestion-promociones-vacio">
                        <p>Todavía no hay promociones para esta aerolínea.</p>
                    </div>
                <?php else: ?>
                    <div class="lista-promociones-elementos">
                        <?php foreach ($promociones as $promocion): ?>
                            <article class="promocion-elemento">
                                <div>
                                    <h3><?= $escapar((string) $promocion['descripcionPromocion']) ?></h3>
                                    <p>Código: #<?= (int) $promocion['codPromocion'] ?> · Descuento: <?= number_format((float) $promocion['descuentoPromocion'], 2, ',', '.') ?>%</p>
                                </div>
                                <span class="estado-promocion estado-promocion-<?= $escapar((string) $promocion['estadoPromocion']) ?>">
                                    <?= $escapar((string) $promocion['estadoPromocion']) ?>
                                </span>
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