<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta | PlaneARG</title>
    <link rel="stylesheet" href="../../publico/css/style.css">
</head>

<body>
    <?php include "../plantilla/encabezado.php"; ?>

    <main class="contenido-principal autenticacion">
        <section class="tarjeta-autenticacion tarjeta-registro" aria-labelledby="titulo-registro">
            <p class="autenticacion-etiqueta">Sumate a PlaneARG</p>
            <h1 id="titulo-registro">Crear cuenta</h1>
            <p class="autenticacion-introduccion">Completá tus datos para registrarte.</p>

            <form class="formulario-autenticacion formulario-registro" method="post">
                <div class="campo-autenticacion">
                    <label for="identificador">Correo electrónico</label>
                    <input
                        id="identificador"
                        name="identificador"
                        type="email"
                        autocomplete="email"
                        placeholder="Tu correo electrónico"
                        required>
                </div>

                <div class="campos-contrasena-registro">
                    <div class="campo-autenticacion campo-contrasena-visible">
                        <label for="contrasena">Contraseña</label>
                        <input
                            id="contrasena"
                            name="contrasena"
                            type="password"
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
                            autocomplete="new-password"
                            placeholder="Repetí tu contraseña"
                            required>
                    </div>
                </div>

                <div class="campo-autenticacion">
                    <label for="nombre">Nombre</label>
                    <input
                        id="nombre"
                        name="nombre"
                        type="text"
                        autocomplete="given-name"
                        placeholder="Tu nombre"
                        required>
                </div>

                <div class="campo-autenticacion">
                    <label for="apellido">Apellido</label>
                    <input
                        id="apellido"
                        name="apellido"
                        type="text"
                        autocomplete="family-name"
                        placeholder="Tu apellido"
                        required>
                </div>

                <div class="campo-autenticacion">
                    <label for="dni">DNI</label>
                    <input
                        id="dni"
                        name="dni"
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="Tu número de DNI"
                        required>
                </div>

                <div class="campo-autenticacion">
                    <label for="telefono">Teléfono</label>
                    <input
                        id="telefono"
                        name="telefono"
                        type="tel"
                        autocomplete="tel"
                        placeholder="Tu número de teléfono"
                        required>
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

    <?php include "../plantilla/pie_pagina.php"; ?>
    <script>
        const campoContrasena = document.getElementById("contrasena");
        const botonVisibilidadContrasena = document.getElementById("alternar-visibilidad-contrasena");
        const iconoVisibilidadContrasena = botonVisibilidadContrasena.querySelector("img");

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