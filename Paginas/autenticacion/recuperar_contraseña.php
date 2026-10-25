<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/correo.php';

$mensaje = '';
$error = '';
$emailIngresado = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $emailIngresado = trim((string) ($_POST['correo'] ?? ''));
    $tokenCsrf = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals((string) $_SESSION['csrf_auth'], $tokenCsrf)) {
        http_response_code(403);
        $error = 'La sesión venció. Recargá la página e intentá nuevamente.';
    } elseif (!filter_var($emailIngresado, FILTER_VALIDATE_EMAIL) || strlen($emailIngresado) > 100) {
        $error = 'Ingresá un correo electrónico válido.';
    } else {
        $conexion = null;
        $hashToken = '';
        $transaccionActiva = false;

        try {
            $conexion = conectarBaseDeDatos();
            $conexion->begin_transaction();
            $transaccionActiva = true;
            $conexion->query('DELETE FROM tokens_recuperacion WHERE venceEn <= NOW()');

            $consultaUsuario = $conexion->prepare(
                'SELECT codUsuario, emailUsuario
                 FROM usuarios
                 WHERE emailUsuario = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $consultaUsuario->bind_param('s', $emailIngresado);
            $consultaUsuario->execute();
            $consultaUsuario->bind_result($codUsuario, $emailUsuario);
            $usuarioEncontrado = $consultaUsuario->fetch();
            $consultaUsuario->close();
            $urlRestablecimiento = '';

            if ($usuarioEncontrado) {
                $consultaReciente = $conexion->prepare(
                    'SELECT codToken
                     FROM tokens_recuperacion
                     WHERE codUsuario = ? AND creadoEn > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                     LIMIT 1'
                );
                $consultaReciente->bind_param('i', $codUsuario);
                $consultaReciente->execute();
                $consultaReciente->store_result();
                $solicitudReciente = $consultaReciente->num_rows > 0;
                $consultaReciente->close();
                if (!$solicitudReciente) {
                    $urlBase = obtenerUrlBaseAplicacion();
                    $token = bin2hex(random_bytes(32));
                    $hashToken = hash('sha256', $token);

                    $eliminarTokensAnteriores = $conexion->prepare(
                        'DELETE FROM tokens_recuperacion WHERE codUsuario = ?'
                    );
                    $eliminarTokensAnteriores->bind_param('i', $codUsuario);
                    $eliminarTokensAnteriores->execute();
                    $eliminarTokensAnteriores->close();

                    $insertarToken = $conexion->prepare(
                        'INSERT INTO tokens_recuperacion (codUsuario, tokenHash, venceEn)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
                    );
                    $insertarToken->bind_param('is', $codUsuario, $hashToken);
                    $insertarToken->execute();
                    $insertarToken->close();

                    $urlRestablecimiento = $urlBase
                        . '/Paginas/autenticacion/restablecer_contrasena.php?token='
                        . rawurlencode($token);
                }
            }

            $conexion->commit();
            $transaccionActiva = false;

            if ($urlRestablecimiento !== '') {
                try {
                    enviarCorreoRecuperacion((string) $emailUsuario, $urlRestablecimiento);
                } catch (RuntimeException $errorEnvio) {
                    $eliminarToken = $conexion->prepare(
                        'DELETE FROM tokens_recuperacion WHERE tokenHash = ?'
                    );
                    $eliminarToken->bind_param('s', $hashToken);
                    $eliminarToken->execute();
                    $eliminarToken->close();
                    throw $errorEnvio;
                }
            }

            $mensaje = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña. El enlace vence en 30 minutos.';
        } catch (mysqli_sql_exception | RuntimeException $errorProceso) {
            if ($conexion instanceof mysqli && $transaccionActiva) {
                $conexion->rollback();
            }
            error_log('Error al solicitar recuperación de contraseña: ' . $errorProceso->getMessage());
            $error = 'No se pudo procesar la solicitud. Intentá nuevamente más tarde.';
        } finally {
            if ($conexion instanceof mysqli) {
                $conexion->close();
            }
        }
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Recuperar contraseña | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>

<body>
    <?php include __DIR__ . '/../plantilla/encabezado.php'; ?>

    <main class="contenido-principal autenticacion">
        <section class="tarjeta-autenticacion" aria-labelledby="titulo-recuperar">
            <p class="autenticacion-etiqueta">Recuperá tu acceso</p>
            <h1 id="titulo-recuperar">Recuperar contraseña</h1>
            <p class="autenticacion-introduccion">Ingresá el correo electrónico asociado a tu cuenta.</p>

            <?php if ($mensaje !== ''): ?>
                <p class="mensaje-perfil-exito" role="status"><?= $escapar($mensaje) ?></p>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <p class="mensaje-autenticacion" role="alert"><?= $escapar($error) ?></p>
            <?php endif; ?>

            <form class="formulario-autenticacion" method="post">
                <input type="hidden" name="csrf_token" value="<?= $escapar($_SESSION['csrf_auth']) ?>">
                <div class="campo-autenticacion">
                    <label for="correo">Correo electrónico</label>
                    <input
                        id="correo"
                        name="correo"
                        type="email"
                        maxlength="100"
                        autocomplete="email"
                        placeholder="Tu correo electrónico"
                        value="<?= $escapar($emailIngresado) ?>"
                        required>
                </div>

                <button class="boton-autenticacion" type="submit">Enviar instrucciones</button>
            </form>

            <p class="alternativa-autenticacion">
                ¿Recordaste tu contraseña?
                <a href="iniciar_sesion.php">Volver a iniciar sesión</a>
            </p>
        </section>
    </main>

    <?php include __DIR__ . '/../plantilla/pie_pagina.php'; ?>
</body>

</html>
