<?php $paginaActual = basename($_SERVER['SCRIPT_NAME']); ?>


<header class="encabezado-principal">
    <a href = "/planeARG/Paginas/inicio/index.php" >
        <div class="circulo-flashero">
            <img src="../../publico/imagenes/Logo_plano_sinfondo.png" alt="Logo PlaneARG" class="logo-marca">
        </div>
    </a>
    <div class="contenedor-encabezado">
        <a href="#" class="marca" aria-label="Inicio">
            <span class="nombre-marca">PlaneARG</span>
        </a>

        <nav class="navegacion-principal" aria-label="Navegación principal">
            <a href="/planeARG/Paginas/Info/acerca_nosotros.php"
               class="<?= $paginaActual === 'acerca_nosotros.php' ? 'activo' : '' ?>"
               <?= $paginaActual === 'acerca_nosotros.php' ? 'aria-current="page"' : '' ?>>
                Sobre nosotros
            </a>
            <a href="#">Vuelos</a>
            <a href="#">Ayuda</a>
            <a href="#" class="enlace-perfil">Mi perfil</a>
        </nav>
    </div>
</header>