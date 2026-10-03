<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | PlaneARG</title>
    <link rel="stylesheet" href="../../publico/css/style.css">
</head>

<body>
    <?php include "../plantilla/encabezado.php"; ?>

    <main class="contenido-principal autenticacion">
        <section class="tarjeta-autenticacion" aria-labelledby="titulo-login">
            <p class="autenticacion-etiqueta">Te damos la bienvenida</p>
            <h1 id="titulo-login">Iniciar sesión</h1>
            <p class="autenticacion-introduccion">Ingresá tus datos para continuar.</p>

            <form class="formulario-autenticacion" method="post">
                <div class="campo-autenticacion">
                    <label for="identificador">Nombre de usuario o correo electrónico</label>
                    <input
                        id="identificador"
                        name="identificador"
                        type="text"
                        autocomplete="username"
                        placeholder="Tu nombre de usuario o correo"
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

    <?php include "../plantilla/pie_pagina.php"; ?>
</body>

</html>