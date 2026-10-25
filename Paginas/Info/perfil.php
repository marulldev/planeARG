<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/conexion.php';

if (!isset($_SESSION['codUsuario'])) {
    header('Location: /Paginas/autenticacion/iniciar_sesion.php');
    exit;
}

$codUsuario = (int) $_SESSION['codUsuario'];
$nombreUsuario = '';
$emailUsuario = '';
$telefonoUsuario = '';
$tipoUsuarioValor = (string) ($_SESSION['tipoUsuario'] ?? '');
$erroresPerfil = [];
$mensajePerfil = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $nombreEnviado = trim((string) ($_POST['nombre_usuario'] ?? ''));
    $emailEnviado = trim((string) ($_POST['email_usuario'] ?? ''));
    $telefonoEnviado = trim((string) ($_POST['telefono_usuario'] ?? ''));
    $nuevaContrasena = (string) ($_POST['nueva_contrasena'] ?? '');
    $confirmarContrasena = (string) ($_POST['confirmar_contrasena'] ?? '');
    $tokenEnviado = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_auth'], $tokenEnviado)) {
        $erroresPerfil[] = 'La sesión venció. Recargá la página e intentá nuevamente.';
    } else {
        if (!preg_match('/\A.{1,100}\z/u', $nombreEnviado)) {
            $erroresPerfil[] = 'Ingresá un nombre de usuario de hasta 100 caracteres.';
        }

        if (!filter_var($emailEnviado, FILTER_VALIDATE_EMAIL) || strlen($emailEnviado) > 100) {
            $erroresPerfil[] = 'Ingresá un correo electrónico válido de hasta 100 caracteres.';
        }

        if (strlen($telefonoEnviado) > 20) {
            $erroresPerfil[] = 'El teléfono no puede superar los 20 caracteres.';
        }

        if ($nuevaContrasena !== '' || $confirmarContrasena !== '') {
            if (strlen($nuevaContrasena) < 8 || strlen($nuevaContrasena) > 72) {
                $erroresPerfil[] = 'La nueva contraseña debe tener al menos 8 caracteres y no superar 72 bytes.';
            }

            if ($nuevaContrasena !== $confirmarContrasena) {
                $erroresPerfil[] = 'La confirmación de la contraseña no coincide.';
            }
        }
    }

    if ($erroresPerfil === []) {
        $conexion = null;

        try {
            $conexion = conectarBaseDeDatos();
            $consultaEmail = $conexion->prepare(
                'SELECT codUsuario FROM usuarios WHERE emailUsuario = ? AND codUsuario <> ? LIMIT 1'
            );
            $consultaEmail->bind_param('si', $emailEnviado, $codUsuario);
            $consultaEmail->execute();
            $consultaEmail->store_result();
            $emailEnUso = $consultaEmail->num_rows > 0;
            $consultaEmail->close();

            if ($emailEnUso) {
                $erroresPerfil[] = 'Ese correo electrónico ya está asociado a otra cuenta.';
            } elseif ($nuevaContrasena !== '') {
                $hashContrasena = password_hash($nuevaContrasena, PASSWORD_DEFAULT);
                $consultaActualizar = $conexion->prepare(
                    'UPDATE usuarios
                     SET nombreUsuario = ?, emailUsuario = ?, telefonoUsuario = ?, claveUsuario = ?
                     WHERE codUsuario = ?'
                );
                $consultaActualizar->bind_param(
                    'ssssi',
                    $nombreEnviado,
                    $emailEnviado,
                    $telefonoEnviado,
                    $hashContrasena,
                    $codUsuario
                );
                $consultaActualizar->execute();
                $consultaActualizar->close();
            } else {
                $consultaActualizar = $conexion->prepare(
                    'UPDATE usuarios
                     SET nombreUsuario = ?, emailUsuario = ?, telefonoUsuario = ?
                     WHERE codUsuario = ?'
                );
                $consultaActualizar->bind_param(
                    'sssi',
                    $nombreEnviado,
                    $emailEnviado,
                    $telefonoEnviado,
                    $codUsuario
                );
                $consultaActualizar->execute();
                $consultaActualizar->close();
            }

            if ($erroresPerfil === []) {
                $nombreUsuario = $nombreEnviado;
                $emailUsuario = $emailEnviado;
                $telefonoUsuario = $telefonoEnviado;
                $_SESSION['nombreUsuario'] = $nombreUsuario;
                $_SESSION['emailUsuario'] = $emailUsuario;
                $mensajePerfil = 'Los cambios se guardaron correctamente.';
            }
        } catch (mysqli_sql_exception $error) {
            error_log('Error al actualizar perfil (MySQL ' . $error->getCode() . '): ' . $error->getMessage());
            $erroresPerfil[] = (int) $error->getCode() === 1062
                ? 'Ese correo electrónico ya está asociado a otra cuenta.'
                : 'No se pudieron guardar los cambios. Intentá nuevamente más tarde.';
        } finally {
            if ($conexion instanceof mysqli) {
                $conexion->close();
            }
        }
    }

    if ($erroresPerfil !== []) {
        $nombreUsuario = $nombreEnviado;
        $emailUsuario = $emailEnviado;
        $telefonoUsuario = $telefonoEnviado;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $conexion = null;

    try {
        $conexion = conectarBaseDeDatos();
        $consultaUsuario = $conexion->prepare(
            'SELECT nombreUsuario, emailUsuario, telefonoUsuario, tipoUsuario
             FROM usuarios
             WHERE codUsuario = ?
             LIMIT 1'
        );
        $consultaUsuario->bind_param('i', $codUsuario);
        $consultaUsuario->execute();
        $consultaUsuario->bind_result($nombreUsuario, $emailUsuario, $telefonoUsuario, $tipoUsuarioValor);
        $usuarioEncontrado = $consultaUsuario->fetch();
        $consultaUsuario->close();

        if (!$usuarioEncontrado) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf_auth'] = bin2hex(random_bytes(32));
            header('Location: /Paginas/autenticacion/iniciar_sesion.php');
            exit;
        }
    } catch (mysqli_sql_exception $error) {
        error_log('Error al cargar perfil (MySQL ' . $error->getCode() . '): ' . $error->getMessage());
        http_response_code(500);
        $erroresPerfil[] = 'No se pudo cargar tu perfil. Intentá nuevamente más tarde.';
    } finally {
        if ($conexion instanceof mysqli) {
            $conexion->close();
        }
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$rolVisible = match ($tipoUsuarioValor) {
    'administrador' => 'ADMIN',
    'ceo de aerolinea' => 'CEO',
    'usuario' => 'Usuario',
    default => 'Usuario',
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi perfil | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../plantilla/encabezado.php'; ?>

    <main class="contenido-principal perfil-pagina">
        <section class="tarjeta-perfil" aria-labelledby="titulo-perfil">
            <img class="avatar-perfil" src="/publico/imagenes/perfil.png" alt="Icono de perfil">
            <div class="contenido-perfil">
                <h1 id="titulo-perfil">Mi Perfil</h1>
                <p class="introduccion-perfil">Información de la cuenta (solo lectura)</p>

                <?php if ($erroresPerfil !== []): ?>
                    <div class="mensaje-autenticacion" role="alert">
                        <ul>
                            <?php foreach ($erroresPerfil as $error): ?>
                                <li><?= $escapar($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if ($mensajePerfil !== ''): ?>
                    <p class="mensaje-perfil-exito" role="status"><?= $escapar($mensajePerfil) ?></p>
                <?php endif; ?>

                <dl class="datos-perfil-solo-lectura">
                    <div>
                        <dt>Código de usuario</dt>
                        <dd><input type="text" value="<?= $codUsuario ?>" readonly aria-label="Código de usuario"></dd>
                    </div>
                    <div>
                        <dt>Tipo de usuario</dt>
                        <dd><input type="text" value="<?= $escapar($rolVisible) ?>" readonly aria-label="Tipo de usuario"></dd>
                    </div>
                </dl>

                <h2 class="subtitulo-edicion-perfil">Datos editables</h2>
                <form class="formulario-edicion-perfil" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $escapar($_SESSION['csrf_auth']) ?>">
                    <div class="campo-perfil">
                        <label for="nombre-usuario">Nombre de usuario <span aria-hidden="true">*</span></label>
                        <input
                            id="nombre-usuario"
                            name="nombre_usuario"
                            type="text"
                            maxlength="100"
                            autocomplete="name"
                            value="<?= $escapar((string) $nombreUsuario) ?>"
                            required>
                    </div>
                    <div class="campo-perfil">
                        <label for="nueva-contrasena">Nueva clave</label>
                        <div class="campo-contrasena-perfil">
                            <input
                                id="nueva-contrasena"
                                name="nueva_contrasena"
                                type="password"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                placeholder="Dejar en blanco para conservarla">
                            <button class="boton-visibilidad-contrasena" type="button" data-toggle-secret-field="nueva-contrasena" aria-label="Mostrar nueva clave">
                                <img src="/publico/icon/eye.png" alt="">
                            </button>
                        </div>
                    </div>
                    <div class="campo-perfil">
                        <label for="confirmar-contrasena">Confirmar clave</label>
                        <div class="campo-contrasena-perfil">
                            <input
                                id="confirmar-contrasena"
                                name="confirmar_contrasena"
                                type="password"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password">
                            <button class="boton-visibilidad-contrasena" type="button" data-toggle-secret-field="confirmar-contrasena" aria-label="Mostrar confirmación de clave">
                                <img src="/publico/icon/eye.png" alt="">
                            </button>
                        </div>
                    </div>
                    <div class="campo-perfil">
                        <label for="email-usuario">Correo electrónico <span aria-hidden="true">*</span></label>
                        <input
                            id="email-usuario"
                            name="email_usuario"
                            type="email"
                            maxlength="100"
                            autocomplete="email"
                            value="<?= $escapar((string) $emailUsuario) ?>"
                            required>
                    </div>
                    <div class="campo-perfil">
                        <label for="telefono-usuario">Teléfono</label>
                        <input
                            id="telefono-usuario"
                            name="telefono_usuario"
                            type="tel"
                            maxlength="20"
                            autocomplete="tel"
                            value="<?= $escapar((string) $telefonoUsuario) ?>">
                    </div>

                    <div class="acciones-perfil">
                        <p><span aria-hidden="true">*</span> Campos obligatorios</p>
                        <div>
                            <a class="boton-cancelar-perfil" href="/Paginas/Info/perfil.php">Cancelar</a>
                            <button class="boton-guardar-perfil" type="submit">Guardar cambios</button>
                        </div>
                    </div>
                </form>

                <form class="formulario-cerrar-sesion-perfil" action="/Paginas/autenticacion/cerrar_sesion.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= $escapar($_SESSION['csrf_auth']) ?>">
                    <button class="boton-cerrar-sesion-perfil" type="submit">Cerrar sesión</button>
                </form>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/../plantilla/pie_pagina.php'; ?>
    <script>
        document.querySelectorAll("[data-toggle-secret-field]").forEach((boton) => {
            boton.addEventListener("click", () => {
                const campo = document.getElementById(boton.dataset.toggleSecretField);
                const mostrar = campo.type === "password";
                campo.type = mostrar ? "text" : "password";
                boton.setAttribute("aria-pressed", String(mostrar));
                boton.setAttribute("aria-label", mostrar ? "Ocultar clave" : "Mostrar clave");
                boton.querySelector("img").src = mostrar
                    ? "/publico/icon/hidden.png"
                    : "/publico/icon/eye.png";
            });
        });
    </script>
</body>
</html>
