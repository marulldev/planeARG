<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/conexion.php';

$errorAutenticacion = '';
$emailIngresado = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $emailIngresado = trim((string) ($_POST['email'] ?? ''));
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $tokenEnviado = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_auth'], $tokenEnviado)) {
        $errorAutenticacion = 'La sesión venció. Recargá la página e intentá nuevamente.';
    } elseif (!filter_var($emailIngresado, FILTER_VALIDATE_EMAIL) || $contrasena === '') {
        $errorAutenticacion = 'Ingresá un correo electrónico y una contraseña válidos.';
    } else {
        $conexion = null;

        try {
            $conexion = conectarBaseDeDatos();
            $consulta = $conexion->prepare(
                'SELECT codUsuario, nombreUsuario, claveUsuario, tipoUsuario
                 FROM usuarios
                 WHERE emailUsuario = ?
                 LIMIT 1'
            );
            $consulta->bind_param('s', $emailIngresado);
            $consulta->execute();
            $consulta->bind_result($codUsuario, $nombreUsuario, $hashContrasena, $tipoUsuario);
            $usuarioEncontrado = $consulta->fetch();
            $consulta->close();

            if ($usuarioEncontrado && is_string($hashContrasena) && password_verify($contrasena, $hashContrasena)) {
                session_regenerate_id(true);
                $_SESSION['codUsuario'] = (int) $codUsuario;
                $_SESSION['nombreUsuario'] = $nombreUsuario;
                $_SESSION['tipoUsuario'] = $tipoUsuario;
                $_SESSION['csrf_auth'] = bin2hex(random_bytes(32));

                if ($tipoUsuario === 'administrador') {
                    header('Location: /Paginas/admin/menu_admin.php');
                } elseif ($tipoUsuario === 'ceo de aerolinea') {
                    header('Location: /Paginas/ceo/Menu%20ceo/panel_control.php');
                } else {
                    header('Location: /');
                }
                exit;
            }

            $errorAutenticacion = 'El correo o la contraseña no son correctos.';
        } catch (mysqli_sql_exception $error) {
            error_log('Error al iniciar sesión: ' . $error->getMessage());
            $errorAutenticacion = 'No se pudo procesar el inicio de sesión. Intentá nuevamente más tarde.';
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
    <title>Iniciar sesión | PlaneARG</title>
    <link rel="stylesheet" href="../../publico/css/style.css">
</head>

<body>
    <?php include __DIR__ . '/../plantilla/encabezado.php'; ?>

    <main class="contenido-principal autenticacion">
        <section class="tarjeta-autenticacion" aria-labelledby="titulo-login">
            <p class="autenticacion-etiqueta">Te damos la bienvenida</p>
            <h1 id="titulo-login">Iniciar sesión</h1>
            <p class="autenticacion-introduccion">Ingresá tus datos para continuar.</p>

            <?php if ($errorAutenticacion !== ''): ?>
                <p class="mensaje-autenticacion" role="alert"><?= $escapar($errorAutenticacion) ?></p>
            <?php endif; ?>

            <form class="formulario-autenticacion" method="post">
                <input type="hidden" name="csrf_token" value="<?= $escapar($_SESSION['csrf_auth']) ?>">
                <div class="campo-autenticacion">
                    <label for="email">Correo electrónico</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        maxlength="100"
                        autocomplete="email"
                        placeholder="Tu correo electrónico"
                        value="<?= $escapar($emailIngresado) ?>"
                        required>
                </div>

                <div class="campo-autenticacion">
                    <label for="contrasena">Contraseña</label>
                    <input
                        id="contrasena"
                        name="contrasena"
                        type="password"
                        autocomplete="current-password"
                        placeholder="Tu contraseña"
                        required>
                </div>

                <button class="boton-autenticacion" type="submit">Ingresar</button>
            </form>

            <a class="enlace-recuperar" href="recuperar_contraseña.php">Recuperar contraseña</a>
            <p class="alternativa-autenticacion">
                ¿Todavía no tenés una cuenta?
                <a href="registro.php">Crear cuenta</a>
            </p>
        </section>
    </main>

    <?php include __DIR__ . '/../plantilla/pie_pagina.php'; ?>
</body>

</html>
