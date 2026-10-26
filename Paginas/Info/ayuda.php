<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/correo.php';

$mensaje = '';
$error = '';
$nombre = '';
$asunto = '';
$descripcion = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $asunto = trim((string) ($_POST['asunto'] ?? ''));
    $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
    $tokenCsrf = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals((string) $_SESSION['csrf_auth'], $tokenCsrf)) {
        http_response_code(403);
        $error = 'La sesión venció. Recargá la página e intentá nuevamente.';
    } elseif (
        $nombre === ''
        || strlen($nombre) > 400
        || $asunto === ''
        || strlen($asunto) > 600
        || preg_match('/[\r\n]/', $asunto)
        || $descripcion === ''
        || strlen($descripcion) > 20000
    ) {
        $error = 'Completá todos los campos y verificá que no superen el límite indicado.';
    } else {
        try {
            enviarCorreoAyuda($nombre, $asunto, $descripcion);
            $mensaje = 'Tu consulta fue enviada. Gracias por comunicarte con PlaneARG.';
            $nombre = '';
            $asunto = '';
            $descripcion = '';
        } catch (RuntimeException $errorEnvio) {
            error_log('Error al enviar consulta desde ayuda: ' . $errorEnvio->getMessage());
            $error = 'No se pudo enviar tu consulta. Intentá nuevamente más tarde.';
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
    <title>planeARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>

<body>
    <?php include "../plantilla/encabezado.php";?>

    <main class="contenido-principal ayuda">
        <section class="ayuda-presentacion" aria-labelledby="titulo-ayuda">
            <h1 id="titulo-ayuda">Estamos para ayudarte a organizar tu viaje</h1>
            <p>Encontrá respuestas sobre la búsqueda de vuelos, tus reservas y la información que necesitás antes de viajar.</p>
        </section>

        <section class="ayuda-preguntas" aria-labelledby="titulo-preguntas">
            <div class="ayuda-encabezado-seccion">
                <h2 id="titulo-preguntas">Preguntas frecuentes</h2>
            </div>

            <div class="ayuda-lista-preguntas">
                <details>
                    <summary>¿Cómo puedo buscar un vuelo?</summary>
                    <p>Ingresá a la sección de búsqueda de vuelos y completá los datos del viaje, como el origen, el destino y las fechas. Revisá las opciones disponibles y sus condiciones antes de elegir.</p>
                </details>
                <details>
                    <summary>¿Cómo consulto los datos de mi reserva?</summary>
                    <p>Iniciá sesión con tu cuenta y entrá a la sección de gestión de reservas o al historial de compras para consultar los viajes asociados a tu usuario.</p>
                </details>
                <details>
                    <summary>¿Puedo cambiar o cancelar un vuelo desde PlaneARG?</summary>
                    <p>Las opciones de cambio y cancelación dependen de las condiciones de la tarifa y de la aerolínea. Revisá la información de tu reserva y contactá a la aerolínea para confirmar las alternativas disponibles.</p>
                </details>
                <details>
                    <summary>¿Dónde consulto los requisitos de equipaje y documentación?</summary>
                    <p>Los requisitos pueden variar según la aerolínea, el destino y la fecha del viaje. Confirmalos directamente con la aerolínea y con las autoridades correspondientes antes de viajar.</p>
                </details>
                <details>
                    <summary>¿Cómo me entero de promociones y novedades?</summary>
                    <p>Visitá la sección de novedades y promociones para consultar la información publicada y las condiciones de cada oferta.</p>
                </details>
            </div>
        </section>

        <aside class="ayuda-informacion" aria-labelledby="titulo-antes-de-viajar">
            <div>
                <p class="ayuda-etiqueta">Antes de viajar</p>
                <h2 id="titulo-antes-de-viajar">Confirmá siempre las condiciones vigentes</h2>
            </div>
            <p>Los horarios, servicios, tarifas y requisitos pueden cambiar. Para información definitiva sobre tu vuelo, verificá los datos con la aerolínea correspondiente.</p>
        </aside>

        <section class="ayuda-contacto" aria-labelledby="titulo-contacto-ayuda">
            <h2 id="titulo-contacto-ayuda">Contactanos</h2>
            <p>Dejanos tu consulta y te responderemos a la brevedad.</p>

            <?php if ($mensaje !== ''): ?>
                <p class="mensaje-perfil-exito" role="status"><?= $escapar($mensaje) ?></p>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <p class="mensaje-autenticacion" role="alert"><?= $escapar($error) ?></p>
            <?php endif; ?>

            <form class="formulario-ayuda" method="post">
                <input type="hidden" name="csrf_token" value="<?= $escapar($_SESSION['csrf_auth']) ?>">

                <div class="campo-ayuda">
                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" type="text" maxlength="100" autocomplete="name" value="<?= $escapar($nombre) ?>" required>
                </div>
                <div class="campo-ayuda">
                    <label for="asunto">Asunto</label>
                    <input id="asunto" name="asunto" type="text" maxlength="150" value="<?= $escapar($asunto) ?>" required>
                </div>
                <div class="campo-ayuda">
                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" rows="6" maxlength="5000" required><?= $escapar($descripcion) ?></textarea>
                </div>

                <button class="boton-ayuda" type="submit">Enviar consulta</button>
            </form>
        </section>
    </main>

    <?php include "../plantilla/pie_pagina.php"; ?>
</body>

</html>