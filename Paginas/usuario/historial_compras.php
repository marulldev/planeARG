<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/conexion.php';

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

$compras = [];
$errorHistorial = '';
$conexion = null;

try {
    $conexion = conectarBaseDeDatos();
    $consulta = $conexion->prepare(
        "SELECT r.codReserva, r.fechaReserva, r.estadoReserva,
                v.origenVuelo, v.destinoVuelo, v.fechaSalidaVuelo,
                v.horaSalidaVuelo, v.precioVuelo, a.nombreAerolinea
         FROM reservas AS r
         INNER JOIN vuelos AS v ON v.codVuelo = r.codVuelo
         INNER JOIN aerolineas AS a ON a.codAerolinea = v.codAerolinea
         WHERE r.codUsuario = ? AND r.estadoReserva = 'confirmada'
         ORDER BY r.fechaReserva DESC, r.codReserva DESC"
    );
    $codUsuario = (int) $_SESSION['codUsuario'];
    $consulta->bind_param('i', $codUsuario);
    $consulta->execute();
    $consulta->bind_result(
        $codReserva,
        $fechaReserva,
        $estadoReserva,
        $origenVuelo,
        $destinoVuelo,
        $fechaSalidaVuelo,
        $horaSalidaVuelo,
        $precioVuelo,
        $nombreAerolinea
    );

    while ($consulta->fetch()) {
        $compras[] = [
            'codReserva' => $codReserva,
            'fechaReserva' => $fechaReserva,
            'estadoReserva' => $estadoReserva,
            'origenVuelo' => $origenVuelo,
            'destinoVuelo' => $destinoVuelo,
            'fechaSalidaVuelo' => $fechaSalidaVuelo,
            'horaSalidaVuelo' => $horaSalidaVuelo,
            'precioVuelo' => $precioVuelo,
            'nombreAerolinea' => $nombreAerolinea,
        ];
    }

    $consulta->close();
} catch (mysqli_sql_exception $error) {
    error_log('Error al consultar el historial de compras: ' . $error->getMessage());
    $errorHistorial = 'No se pudo cargar tu historial de compras. Intentá nuevamente más tarde.';
} finally {
    if ($conexion instanceof mysqli) {
        $conexion->close();
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formatearFecha = static fn(string $fecha): string => date('d/m/Y', strtotime($fecha));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de compras | PlaneARG</title>
    <link rel="stylesheet" href="../../publico/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../plantilla/encabezado.php'; ?>

    <main class="contenido-principal historial-compras" aria-labelledby="titulo-historial">
        <header class="historial-compras-encabezado">
            <p class="autenticacion-etiqueta">Tu cuenta</p>
            <h1 id="titulo-historial">Historial de compras</h1>
            <p>Consultá los vuelos que compraste y los datos de cada reserva.</p>
        </header>

        <?php if ($errorHistorial !== ''): ?>
            <p class="mensaje-autenticacion" role="alert"><?= $escapar($errorHistorial) ?></p>
        <?php elseif ($compras === []): ?>
            <section class="historial-compras-vacio" aria-live="polite">
                <h2>Todavía no tenés compras confirmadas</h2>
                <p>Cuando confirmes la compra de un vuelo, vas a poder consultarlo acá.</p>
                <a class="historial-compras-enlace" href="/Paginas/Info/vuelos.php">Ver vuelos disponibles</a>
            </section>
        <?php else: ?>
            <div class="historial-compras-tabla-contenedor">
                <table class="historial-compras-tabla">
                    <caption>Compras confirmadas, de la más reciente a la más antigua</caption>
                    <thead>
                        <tr>
                            <th scope="col">Reserva</th>
                            <th scope="col">Fecha de compra</th>
                            <th scope="col">Vuelo</th>
                            <th scope="col">Aerolínea</th>
                            <th scope="col">Salida</th>
                            <th scope="col">Precio</th>
                            <th scope="col">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compras as $compra): ?>
                            <tr>
                                <td>#<?= (int) $compra['codReserva'] ?></td>
                                <td><?= $escapar($formatearFecha((string) $compra['fechaReserva'])) ?></td>
                                <td><?= $escapar((string) $compra['origenVuelo']) ?> → <?= $escapar((string) $compra['destinoVuelo']) ?></td>
                                <td><?= $escapar((string) $compra['nombreAerolinea']) ?></td>
                                <td>
                                    <?= $escapar($formatearFecha((string) $compra['fechaSalidaVuelo'])) ?>
                                    <?= $escapar(substr((string) $compra['horaSalidaVuelo'], 0, 5)) ?>
                                </td>
                                <td>$ <?= number_format((float) $compra['precioVuelo'], 2, ',', '.') ?></td>
                                <td><span class="historial-compras-estado"><?= $escapar((string) $compra['estadoReserva']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../plantilla/pie_pagina.php'; ?>
</body>
</html>