<?php
require_once __DIR__ . '/../includes/sesion.php';

$paginaActual = basename($_SERVER['SCRIPT_NAME']);
$espacioEnBlanco = isset($espacioEnBlanco) ? $espacioEnBlanco : true;
$usuarioAutenticado = isset($_SESSION['codUsuario']);
$nombreUsuario = htmlspecialchars(
    (string) ($_SESSION['nombreUsuario'] ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$tipoUsuario = (string) ($_SESSION['tipoUsuario'] ?? '');
$rutaMenu = match ($tipoUsuario) {
    'administrador' => '/Paginas/admin/menu_admin.php',
    'ceo de aerolinea' => '/Paginas/ceo/Menu%20ceo/panel_control.php',
    default => '/Paginas/usuario/menu_usuario.php',
};
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
            <?php if ($usuarioAutenticado): ?>
                <a href="<?= $rutaMenu ?>" class="enlace-menu">MENU</a>
                <a href="/Paginas/Info/perfil.php" class="enlace-perfil">Mi Perfil</a>
                <span class="saludo-usuario">Hola, <?= $nombreUsuario ?></span>
                <form class="formulario-cerrar-sesion" action="/Paginas/autenticacion/cerrar_sesion.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_auth'], ENT_QUOTES, 'UTF-8') ?>">
                    <button class="enlace-perfil" type="submit">Cerrar sesión</button>
                </form>
            <?php else: ?>
                <a href="/Paginas/autenticacion/iniciar_sesion.php" class="enlace-perfil">Iniciar Sesión</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
