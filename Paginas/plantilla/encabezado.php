<?php
$paginaActual = basename($_SERVER['SCRIPT_NAME']);
$espacioEnBlanco = isset($espacioEnBlanco) ? $espacioEnBlanco : true;
?>


<header class="encabezado-principal <?= $espacioEnBlanco ? '' : 'encabezado-superpuesto' ?>">
    <a href="/">
        <div class="circulo-flashero">
            <img src="/publico/imagenes/Logo_plano_sinfondo.png" alt="Logo PlaneARG" class="logo-marca">
        </div>
    </a>
    <div class="contenedor-encabezado">
        <a href="/" class="marca" aria-label="Inicio">
            <span class="nombre-marca">PlaneARG</span>
        </a>

        <nav class="navegacion-principal" aria-label="Navegación principal">
            <a href="/Paginas/Info/acerca_nosotros.php"
               class="<?= $paginaActual === 'acerca_nosotros.php' ? 'activo' : '' ?>"
               <?= $paginaActual === 'acerca_nosotros.php' ? 'aria-current="page"' : '' ?>>
                Sobre nosotros
            </a>
            <a href="/Paginas/Info/vuelos.php" class="<?= $paginaActual === 'vuelos.php' ? 'activo' : '' ?>" <?= $paginaActual === 'vuelos.php' ? 'aria-current="page"' : '' ?>>Vuelos</a>
            <a href="/Paginas/Info/ayuda.php" class="<?= $paginaActual === 'ayuda.php' ? 'activo' : '' ?>" <?= $paginaActual === 'ayuda.php' ? 'aria-current="page"' : '' ?>>Ayuda</a>
            <a href="/Paginas/autenticacion/iniciar_sesion.php" class="enlace-perfil">Mi perfil</a>
        </nav>
    </div>
</header>
