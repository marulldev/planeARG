<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/conexion.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Referrer-Policy: no-referrer');

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$mensaje = '';
$error = '';
$restablecimientoCompletado = false;
$tokenFormatoValido = preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;

if (!$tokenFormatoValido) {
    $error = 'El enlace no es válido o venció. Solicitá uno nuevo.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $tokenCsrf = (string) ($_POST['csrf_token'] ?? '');
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $repetirContrasena = (string) ($_POST['repetir_contrasena'] ?? '');

    if (!hash_equals((string) $_SESSION['csrf_auth'], $tokenCsrf)) {
        http_response_code(403);
        $error = 'La sesión venció. Recargá el enlace e intentá nuevamente.';
    } elseif (strlen($contrasena) < 8 || strlen($contrasena) > 72) {
        $error = 'La contraseña debe tener al menos 8 caracteres y no superar 72 bytes.';
    } elseif ($contrasena !== $repetirContrasena) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $conexion = null;
        $transaccionActiva = false;

        try {
            $conexion = conectarBaseDeDatos();
            $conexion->begin_transaction();
            $transaccionActiva = true;

            $hashToken = hash('sha256', $token);
            $consultaToken = $conexion->prepare(
                'SELECT codUsuario
                 FROM tokens_recuperacion
                 WHERE tokenHash = ? AND venceEn > NOW()
                 LIMIT 1
                 FOR UPDATE'
            );
            $consultaToken->bind_param('s', $hashToken);
            $consultaToken->execute();
            $consultaToken->bind_result($codUsuario);
            $tokenValido = $consultaToken->fetch();
            $consultaToken->close();

            if (!$tokenValido) {
                $conexion->rollback();
                $transaccionActiva = false;
                $tokenFormatoValido = false;
                $error = 'El enlace no es válido o venció. Solicitá uno nuevo.';
            } else {
                $hashContrasena = password_hash($contrasena, PASSWORD_DEFAULT);
                if (!is_string($hashContrasena)) {
                    throw new RuntimeException('No se pudo generar el hash de la contraseña.');
                }

                $actualizarContrasena = $conexion->prepare(
                    'UPDATE usuarios SET claveUsuario = ? WHERE codUsuario = ?'
                );
                $actualizarContrasena->bind_param('si', $hashContrasena, $codUsuario);
                $actualizarContrasena->execute();
                $actualizarContrasena->close();

                $eliminarTokens = $conexion->prepare(
                    'DELETE FROM tokens_recuperacion WHERE codUsuario = ?'
                );
                $eliminarTokens->bind_param('i', $codUsuario);
                $eliminarTokens->execute();
                $eliminarTokens->close();

                $conexion->commit();
                $transaccionActiva = false;
                $restablecimientoCompletado = true;
                $mensaje = 'Tu contraseña se cambió correctamente. Ya podés iniciar sesión.';
                $token = '';
            }
        } catch (mysqli_sql_exception | RuntimeException $errorProceso) {
            if ($conexion instanceof mysqli && $transaccionActiva) {
                $conexion->rollback();
            }
            error_log('Error al restablecer contraseña: ' . $errorProceso->getMessage());
            $error = 'No se pudo cambiar la contraseña. Intentá nuevamente más tarde.';
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
    <title>Restablecer contraseña | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>

<body>
    <?php include __DIR__ . '/../plantilla/encabezado.php'; ?>

    <main class="contenido-principal autenticacion">
        <section class="tarjeta-autenticacion" aria-labelledby="titulo-restablecer">
            <p class="autenticacion-etiqueta">Recuperá tu acceso</p>
            <h1 id="titulo-restablecer">Crear una contraseña nueva</h1>

            <?php if ($mensaje !== ''): ?>
                <p class="mensaje-perfil-exito" role="status"><?= $escapar($mensaje) ?></p>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <p class="mensaje-autenticacion" role="alert"><?= $escapar($error) ?></p>
            <?php endif; ?>

            <?php if (!$restablecimientoCompletado && $tokenFormatoValido): ?>
                <p class="autenticacion-introduccion">Elegí una contraseña de al menos 8 caracteres.</p>
                <form class="formulario-autenticacion" method="post" action="restablecer_contrasena.php">
                    <input type="hidden" name="csrf_token" value="<?= $escapar($_SESSION['csrf_auth']) ?>">
                    <input type="hidden" name="token" value="<?= $escapar($token) ?>">
                    <div class="campo-autenticacion">
                        <label for="contrasena">Nueva contraseña</label>
                        <input
                            id="contrasena"
                            name="contrasena"
                            type="password"
                            minlength="8"
                            maxlength="72"
                            autocomplete="new-password"
                            required>
                    </div>
                    <div class="campo-autenticacion">
                        <label for="repetir-contrasena">Repetir contraseña</label>
                        <input
                            id="repetir-contrasena"
                            name="repetir_contrasena"
                            type="password"
                            minlength="8"
                            maxlength="72"
                            autocomplete="new-password"
                            required>
                    </div>
                    <button class="boton-autenticacion" type="submit">Cambiar contraseña</button>
                </form>
            <?php endif; ?>

            <p class="alternativa-autenticacion">
                <a href="iniciar_sesion.php">Volver a iniciar sesión</a>
                <?php if ($error !== ''): ?>
                    · <a href="recuperar_contraseña.php">Solicitar otro enlace</a>
                <?php endif; ?>
            </p>
        </section>
    </main>

    <?php include __DIR__ . '/../plantilla/pie_pagina.php'; ?>
</body>

</html>
