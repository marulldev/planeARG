<?php
require_once __DIR__ . '/../includes/sesion.php';

if (!isset($_SESSION['codUsuario'])) {
    header('Location: /Paginas/autenticacion/iniciar_sesion.php');
    exit;
}

$nombreUsuario = htmlspecialchars(
    (string) ($_SESSION['nombreUsuario'] ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$emailUsuario = htmlspecialchars(
    (string) ($_SESSION['emailUsuario'] ?? ''),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$tipoUsuario = match ($_SESSION['tipoUsuario'] ?? '') {
    'administrador' => 'Administrador',
    'ceo de aerolinea' => 'CEO de aerolínea',
    'usuario' => 'Pasajero',
    default => 'Usuario',
};
$tipoUsuario = htmlspecialchars($tipoUsuario, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi perfil | PlaneARG</title>
    <link rel="stylesheet" href="../../publico/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../plantilla/encabezado.php'; ?>

    <main class="contenido-principal autenticacion">
        <section class="tarjeta-autenticacion" aria-labelledby="titulo-perfil">
            <p class="autenticacion-etiqueta">Tu cuenta</p>
            <h1 id="titulo-perfil">Mi Perfil</h1>
            <dl class="datos-perfil">
                <div><dt>Nombre</dt><dd><?= $nombreUsuario ?></dd></div>
                <div><dt>Correo electrónico</dt><dd><?= $emailUsuario ?></dd></div>
                <div><dt>Tipo de cuenta</dt><dd><?= $tipoUsuario ?></dd></div>
            </dl>
        </section>
    </main>

    <?php include __DIR__ . '/../plantilla/pie_pagina.php'; ?>
</body>
</html>