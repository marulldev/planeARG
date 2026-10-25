<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Depurar encabezado | PlaneARG</title>
    <link rel="stylesheet" href="../../publico/css/style.css">
</head>
<body>
    <header class="encabezado-principal">
        <a href="/" aria-label="Inicio">
            <div class="circulo-flashero">
                <img src="/publico/imagenes/Logo_plano_sinfondo.png" alt="Logo PlaneARG" class="logo-marca">
            </div>
        </a>
        <div class="contenedor-encabezado">
            <a href="/" class="marca" aria-label="Inicio">
                <span class="nombre-marca">PlaneARG</span>
            </a>

            <nav class="navegacion-principal" aria-label="Navegación principal">
                <a href="/Paginas/Info/acerca_nosotros.php">Sobre nosotros</a>
                <a href="/Paginas/Info/vuelos.php">Vuelos</a>
                <a href="/Paginas/Info/ayuda.php">Ayuda</a>
                <a href="/Paginas/Info/perfil.php" class="enlace-perfil">
                    <img class="icono-perfil" src="/publico/imagenes/perfil.png" alt="" aria-hidden="true">
                    Mi Perfil
                </a>
            </nav>
        </div>
    </header>

    <main class="contenido-principal autenticacion">
        <section class="tarjeta-autenticacion" aria-labelledby="titulo-depuracion">
            <p class="autenticacion-etiqueta">Depuración</p>
            <h1 id="titulo-depuracion">Encabezado de prueba</h1>
            <p class="autenticacion-introduccion">
                Esta copia muestra el icono de perfil por defecto y no requiere iniciar sesión.
            </p>
        </section>
    </main>
</body>
</html>
