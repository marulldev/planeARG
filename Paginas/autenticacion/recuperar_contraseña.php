<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>

<body>
    <?php include "../plantilla/encabezado.php"; ?>

    <main class="contenido-principal autenticacion">
        <section class="tarjeta-autenticacion" aria-labelledby="titulo-recuperar">
            <p class="autenticacion-etiqueta">Recuperá tu acceso</p>
            <h1 id="titulo-recuperar">Recuperar contraseña</h1>
            <p class="autenticacion-introduccion">Ingresá el correo electrónico asociado a tu cuenta.</p>

            <form class="formulario-autenticacion">
                <div class="campo-autenticacion">
                    <label for="correo">Correo electrónico</label>
                    <input
                        id="correo"
                        name="correo"
                        type="email"
                        autocomplete="email"
                        placeholder="Tu correo electrónico"
                        required>
                </div>

                <button class="boton-autenticacion" type="button">Enviar instrucciones</button>
            </form>

            <p class="alternativa-autenticacion">
                ¿Recordaste tu contraseña?
                <a href="iniciar_sesion.php">Volver a iniciar sesión</a>
            </p>
        </section>
    </main>

    <?php include "../plantilla/pie_pagina.php"; ?>
</body>

</html>