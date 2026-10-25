<?php
require_once __DIR__ . '/../includes/sesion.php';

if (!isset($_SESSION['codUsuario'])) {
    header('Location: /Paginas/autenticacion/iniciar_sesion.php');
    exit;
}

if (($_SESSION['tipoUsuario'] ?? '') !== 'usuario') {
    if (($_SESSION['tipoUsuario'] ?? '') === 'administrador') {
        header('Location: /Paginas/admin/menu_admin.php');
    } elseif (($_SESSION['tipoUsuario'] ?? '') === 'ceo de aerolinea') {
        header('Location: /Paginas/ceo/Menu%20ceo/panel_control.php');
    } else {
        header('Location: /');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú de pasajero | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../plantilla/encabezado.php'; ?>

    <main class="contenido-principal menu-admin">
        <h1>Menú de pasajero</h1>
        <nav class="acciones-admin" aria-label="Opciones de pasajero">
            <a class="accion-admin" href="/Paginas/Info/vuelos.php">Consultar vuelos</a>
            <a class="accion-admin" href="/Paginas/usuario/reservar_vuelo.php">Reservar un vuelo</a>
            <a class="accion-admin" href="/Paginas/usuario/gestion_reservas.php">Gestionar reservas</a>
            <a class="accion-admin" href="/Paginas/usuario/historial_compras.php">Historial de compras</a>
        </nav>
    </main>

    <?php include __DIR__ . '/../plantilla/pie_pagina.php'; ?>
</body>
</html>