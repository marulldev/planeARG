<?php
$paginaActual = basename($_SERVER['SCRIPT_NAME']);
$encabezadoSuperpuesto = !empty($encabezadoSuperpuesto);
?>


<header class="encabezado-principal <?= $encabezadoSuperpuesto ? 'encabezado-superpuesto' : '' ?>">
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
            <a href="/planeARG/Paginas/Info/ayuda.php" class="<?= $paginaActual === 'ayuda.php' ? 'activo' : '' ?>" <?= $paginaActual === 'ayuda.php' ? 'aria-current="page"' : '' ?>>Ayuda</a>
            <a href="/planeARG/Paginas/autenticacion/iniciar_sesion.php" class="enlace-perfil">Mi perfil</a>
        </nav>
    </div>
</header>
