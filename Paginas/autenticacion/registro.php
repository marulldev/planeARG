<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/conexion.php';

$erroresRegistro = [];
$nombreCompleto = '';
$emailIngresado = '';
$telefonoIngresado = '';
$codAerolineaIngresada = '';
$solicitudCeo = false;
$mensajeRegistro = '';
$aerolineas = [];
$errorCargaAerolineas = '';

$conexionAerolineas = null;
try {
    $conexionAerolineas = conectarBaseDeDatos();
    $resultadoAerolineas = $conexionAerolineas->query(
        'SELECT codAerolinea, nombreAerolinea, codigoIATA FROM aerolineas ORDER BY nombreAerolinea'
    );
    $aerolineas = $resultadoAerolineas->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $error) {
    error_log('Error al cargar aerolíneas para el registro (MySQL ' . $error->getCode() . '): ' . $error->getMessage());
    $errorCargaAerolineas = 'No se pudieron cargar las aerolíneas disponibles.';
} finally {
    if ($conexionAerolineas instanceof mysqli) {
        $conexionAerolineas->close();
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
    $emailIngresado = trim((string) ($_POST['email'] ?? ''));
    $telefonoIngresado = trim((string) ($_POST['telefono'] ?? ''));
    $codAerolineaIngresada = (string) ($_POST['cod_aerolinea'] ?? '');
    $solicitudCeo = ($_POST['soy_ceo'] ?? '') === '1';
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $repetirContrasena = (string) ($_POST['repetir_contrasena'] ?? '');
    $tokenEnviado = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_auth'], $tokenEnviado)) {
        $erroresRegistro[] = 'La sesión venció. Recargá la página e intentá nuevamente.';
    } else {
        if (!preg_match('/\A.{1,100}\z/u', $nombreCompleto)) {
            $erroresRegistro[] = 'Ingresá tu nombre completo (hasta 100 caracteres).';
        }

        if (!filter_var($emailIngresado, FILTER_VALIDATE_EMAIL) || strlen($emailIngresado) > 100) {
            $erroresRegistro[] = 'Ingresá un correo electrónico válido de hasta 100 caracteres.';
        }

        if (!preg_match('/\A.{1,20}\z/u', $telefonoIngresado)) {
            $erroresRegistro[] = 'Ingresá un teléfono de hasta 20 caracteres.';
        }

        if ($solicitudCeo) {
            if ($errorCargaAerolineas !== '') {
                $erroresRegistro[] = $errorCargaAerolineas;
            } elseif ($aerolineas === []) {
                $erroresRegistro[] = 'Todavía no hay aerolíneas disponibles para elegir.';
            } else {
                $codAerolineaValidado = filter_var($codAerolineaIngresada, FILTER_VALIDATE_INT);
                $codigosAerolineas = array_map('intval', array_column($aerolineas, 'codAerolinea'));

                if (
                    $codAerolineaValidado === false
                    || $codAerolineaValidado < 1
                    || !in_array($codAerolineaValidado, $codigosAerolineas, true)
                ) {
                    $erroresRegistro[] = 'Elegí una aerolínea válida para tu solicitud de CEO.';
                }
            }
        }

        if (strlen($contrasena) < 8 || strlen($contrasena) > 72) {
            $erroresRegistro[] = 'La contraseña debe tener al menos 8 caracteres y no superar 72 bytes.';
        }

        if ($contrasena !== $repetirContrasena) {
            $erroresRegistro[] = 'Las contraseñas no coinciden.';
        }

        if ($erroresRegistro === []) {
            $conexion = null;

            try {
                $conexion = conectarBaseDeDatos();
                $consultaExistente = $conexion->prepare(
                    'SELECT codUsuario FROM usuarios WHERE emailUsuario = ? LIMIT 1'
                );
                $consultaExistente->bind_param('s', $emailIngresado);
                $consultaExistente->execute();
                $consultaExistente->store_result();
                $emailExiste = $consultaExistente->num_rows > 0;
                $consultaExistente->close();

                if ($emailExiste) {
                    $erroresRegistro[] = 'Ya existe una cuenta registrada con ese correo electrónico.';
                } else {
                    $hashContrasena = password_hash($contrasena, PASSWORD_DEFAULT);
                    $tipoUsuarioNuevo = $solicitudCeo ? 'ceo pendiente' : 'usuario';
                    $codAerolineaRegistro = $solicitudCeo ? (int) $codAerolineaIngresada : null;
                    $consulta = $conexion->prepare(
                        "INSERT INTO usuarios
                            (nombreUsuario, claveUsuario, tipoUsuario, emailUsuario, telefonoUsuario, codAerolinea)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    $consulta->bind_param(
                        'sssssi',
                        $nombreCompleto,
                        $hashContrasena,
                        $tipoUsuarioNuevo,
                        $emailIngresado,
                        $telefonoIngresado,
                        $codAerolineaRegistro
                    );
                    $consulta->execute();
                    $codUsuario = $conexion->insert_id;
                    $consulta->close();

                    if ($solicitudCeo) {
                        $mensajeRegistro = 'Tu cuenta fue creada y la solicitud para ser CEO quedó pendiente de aprobación. Vas a poder iniciar sesión cuando un administrador la apruebe.';
                        $nombreCompleto = '';
                        $emailIngresado = '';
                        $telefonoIngresado = '';
                        $codAerolineaIngresada = '';
                        $solicitudCeo = false;
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['codUsuario'] = (int) $codUsuario;
                        $_SESSION['nombreUsuario'] = $nombreCompleto;
                        $_SESSION['tipoUsuario'] = 'usuario';
                        $_SESSION['emailUsuario'] = $emailIngresado;
                        $_SESSION['csrf_auth'] = bin2hex(random_bytes(32));

                        header('Location: /');
                        exit;
                    }
                }
            } catch (mysqli_sql_exception $error) {
                $codigoError = (int) $error->getCode();
                error_log('Error al crear una cuenta (MySQL ' . $codigoError . '): ' . $error->getMessage());

                $erroresPorCodigo = [
                    1044 => 'El usuario de la base de datos no tiene permiso para acceder a esa base. Revisá sus permisos en Hostinger.',
                    1045 => 'Hostinger rechazó el usuario o la contraseña de la base de datos. Revisá los datos de conexión configurados para el sitio.',
                    1049 => 'No se encontró la base de datos configurada. Revisá su nombre completo en la configuración de conexión.',
                    1048 => 'MySQL rechazó un valor NULL. Las cuentas de usuario no están asociadas a una aerolínea, por lo que usuarios.codAerolinea debe permitir NULL. Aplicá la migración PRUEBA/migraciones/20261025_cod_aerolinea_nullable.sql; si el error continúa, revisá el registro PHP para identificar la columna exacta.',
                    1054 => 'La tabla usuarios no tiene las columnas que espera el registro. Compará su estructura con el esquema PRUEBA/basepruba2.sql.',
                    1062 => 'Ya existe una cuenta registrada con ese correo electrónico.',
                    1146 => 'No se encontró la tabla usuarios en la base seleccionada. Importá la tabla en la base configurada.',
                    1142 => 'El usuario de la base de datos no tiene permiso para consultar la tabla usuarios. Revisá sus permisos en Hostinger.',
                    2002 => 'No se pudo establecer conexión con MySQL. Verificá el servidor de base de datos indicado por Hostinger.',
                    2003 => 'El servidor MySQL no está disponible en el host configurado. Revisá los datos de conexión del sitio.',
                    1265 => 'La base de datos todavía no permite el estado ceo pendiente. Actualizá el ENUM de tipoUsuario en phpMyAdmin.',
                    1364 => 'Falta una columna obligatoria en la tabla usuarios. Compará su estructura con PRUEBA/basepruba2.sql.',
                    1452 => 'La aerolínea seleccionada ya no está disponible. Elegí otra e intentá nuevamente.',
                ];

                $erroresRegistro[] = $erroresPorCodigo[$codigoError]
                    ?? 'No se pudo crear la cuenta. Código de referencia MySQL: ' . $codigoError . '.';
            } finally {
                if ($conexion instanceof mysqli) {
                    $conexion->close();
                }
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
    <title>Crear cuenta | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>

<body>
    <?php include __DIR__ . '/../plantilla/encabezado.php'; ?>

    <main class="contenido-principal autenticacion">
        <section class="tarjeta-autenticacion tarjeta-registro" aria-labelledby="titulo-registro">
            <p class="autenticacion-etiqueta">Sumate a PlaneARG</p>
            <h1 id="titulo-registro">Crear cuenta</h1>
            <p class="autenticacion-introduccion">Completá tus datos para registrarte.</p>

            <?php if ($mensajeRegistro !== ''): ?>
                <p class="mensaje-perfil-exito" role="status"><?= $escapar($mensajeRegistro) ?></p>
            <?php endif; ?>

            <?php if ($erroresRegistro !== []): ?>
                <div class="mensaje-autenticacion" role="alert">
                    <ul>
                        <?php foreach ($erroresRegistro as $error): ?>
                            <li><?= $escapar($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="formulario-autenticacion formulario-registro" method="post">
                <input type="hidden" name="csrf_token" value="<?= $escapar($_SESSION['csrf_auth']) ?>">
                <div class="campo-autenticacion campo-email-registro">
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

                <div class="campos-contrasena-registro">
                    <div class="campo-autenticacion campo-contrasena-visible">
                        <label for="contrasena">Contraseña</label>
                        <input
                            id="contrasena"
                            name="contrasena"
                            type="password"
                            minlength="8"
                            maxlength="72"
                            autocomplete="new-password"
                            placeholder="Creá una contraseña"
                            required>
                        <button
                            class="boton-visibilidad-contrasena"
                            id="alternar-visibilidad-contrasena"
                            type="button"
                            aria-label="Mostrar contraseña"
                            aria-controls="contrasena"
                            aria-pressed="false">
                            <img src="../../publico/icon/eye.png" alt="">
                        </button>
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
                            placeholder="Repetí tu contraseña"
                            required>
                    </div>
                </div>

                <div class="campo-autenticacion">
                    <label for="nombre-completo">Nombre completo</label>
                    <input
                        id="nombre-completo"
                        name="nombre_completo"
                        type="text"
                        maxlength="100"
                        autocomplete="name"
                        placeholder="Tu nombre y apellido"
                        value="<?= $escapar($nombreCompleto) ?>"
                        required>
                </div>

                <div class="campo-autenticacion">
                    <label for="telefono">Teléfono</label>
                    <input
                        id="telefono"
                        name="telefono"
                        type="tel"
                        maxlength="20"
                        autocomplete="tel"
                        placeholder="Tu número de teléfono"
                        value="<?= $escapar($telefonoIngresado) ?>"
                        required>
                </div>

                <label class="opcion-ceo-registro" for="soy-ceo">
                    <input
                        id="soy-ceo"
                        name="soy_ceo"
                        type="checkbox"
                        value="1"
                        <?= $solicitudCeo ? 'checked' : '' ?>>
                    <span>Soy CEO de una aerolínea <small>Tu solicitud necesitará aprobación antes de habilitar el acceso CEO.</small></span>
                </label>

                <div
                    class="campo-autenticacion campo-aerolinea-ceo"
                    id="campo-aerolinea-ceo"
                    <?= $solicitudCeo ? '' : 'hidden' ?>>
                    <label for="cod-aerolinea">Aerolínea</label>
                    <select
                        id="cod-aerolinea"
                        name="cod_aerolinea"
                        <?= $solicitudCeo && $aerolineas !== [] ? 'required' : 'disabled' ?>>
                        <option value="">Elegí una aerolínea</option>
                        <?php foreach ($aerolineas as $aerolinea): ?>
                            <option
                                value="<?= (int) $aerolinea['codAerolinea'] ?>"
                                <?= (string) $aerolinea['codAerolinea'] === $codAerolineaIngresada ? 'selected' : '' ?>>
                                <?= $escapar($aerolinea['nombreAerolinea']) ?> (<?= $escapar($aerolinea['codigoIATA']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errorCargaAerolineas !== ''): ?>
                        <small><?= $escapar($errorCargaAerolineas) ?></small>
                    <?php elseif ($aerolineas === []): ?>
                        <small>Todavía no hay aerolíneas disponibles para elegir.</small>
                    <?php endif; ?>
                </div>

                <button class="boton-autenticacion" type="submit">Crear cuenta</button>
            </form>

            <a class="enlace-recuperar" href="recuperar_contraseña.php">Recuperar contraseña</a>
            <p class="alternativa-autenticacion">
                ¿Ya tenés una cuenta?
                <a href="iniciar_sesion.php">Iniciar sesión</a>
            </p>
        </section>
    </main>

    <?php include __DIR__ . '/../plantilla/pie_pagina.php'; ?>
    <script>
        const campoContrasena = document.getElementById("contrasena");
        const botonVisibilidadContrasena = document.getElementById("alternar-visibilidad-contrasena");
        const iconoVisibilidadContrasena = botonVisibilidadContrasena.querySelector("img");
        const opcionCeo = document.getElementById("soy-ceo");
        const campoAerolineaCeo = document.getElementById("campo-aerolinea-ceo");
        const seleccionAerolinea = document.getElementById("cod-aerolinea");

        const actualizarCampoAerolinea = () => {
            const hayAerolineas = seleccionAerolinea.options.length > 1;
            campoAerolineaCeo.hidden = !opcionCeo.checked;
            seleccionAerolinea.disabled = !opcionCeo.checked || !hayAerolineas;
            seleccionAerolinea.required = opcionCeo.checked && hayAerolineas;
        };

        opcionCeo.addEventListener("change", actualizarCampoAerolinea);
        actualizarCampoAerolinea();

        botonVisibilidadContrasena.addEventListener("click", () => {
            const mostrarContrasena = campoContrasena.type === "password";
            campoContrasena.type = mostrarContrasena ? "text" : "password";
            botonVisibilidadContrasena.setAttribute("aria-label", mostrarContrasena ? "Ocultar contraseña" : "Mostrar contraseña");
            botonVisibilidadContrasena.setAttribute("aria-pressed", String(mostrarContrasena));
            iconoVisibilidadContrasena.src = mostrarContrasena ?
                "../../publico/icon/hidden.png" :
                "../../publico/icon/eye.png";
        });
    </script>
</body>

</html>
