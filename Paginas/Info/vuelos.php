<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/conexion.php';

$vuelos = [];
$errorCarga = '';
$conexion = null;

try {
    $conexion = conectarBaseDeDatos();
    $consulta = $conexion->prepare(
        "SELECT v.codVuelo, v.origenVuelo, v.destinoVuelo, v.fechaSalidaVuelo,
                v.horaSalidaVuelo, v.precioVuelo, p.descuentoPromocion,
                ROUND(v.precioVuelo * (100 - COALESCE(p.descuentoPromocion, 0)) / 100, 2) AS precioFinal,
                v.asientosDisponibles,
                a.nombreAerolinea
         FROM vuelos AS v
         INNER JOIN aerolineas AS a ON a.codAerolinea = v.codAerolinea
         LEFT JOIN promociones AS p
           ON p.codPromocion = (
               SELECT MAX(p2.codPromocion)
               FROM promociones AS p2
               WHERE p2.codAerolinea = v.codAerolinea
                 AND p2.estadoPromocion = 'aprobada'
                 AND (p2.fechaExpiracionPromocion IS NULL OR p2.fechaExpiracionPromocion >= CURRENT_DATE)
           )
         WHERE TIMESTAMP(v.fechaSalidaVuelo, v.horaSalidaVuelo) > NOW()
           AND v.asientosDisponibles > 0
         ORDER BY v.fechaSalidaVuelo, v.horaSalidaVuelo, v.codVuelo"
    );
    $consulta->execute();
    $consulta->bind_result(
        $codVuelo,
        $origenVuelo,
        $destinoVuelo,
        $fechaSalidaVuelo,
        $horaSalidaVuelo,
        $precioOriginal,
        $descuentoPromocion,
        $precioVuelo,
        $asientosDisponibles,
        $nombreAerolinea
    );

    while ($consulta->fetch()) {
        $vuelos[] = [
            'codVuelo' => $codVuelo,
            'origenVuelo' => $origenVuelo,
            'destinoVuelo' => $destinoVuelo,
            'fechaSalidaVuelo' => $fechaSalidaVuelo,
            'horaSalidaVuelo' => $horaSalidaVuelo,
            'precioVuelo' => $precioVuelo,
            'precioOriginal' => $precioOriginal,
            'descuentoPromocion' => $descuentoPromocion,
            'asientosDisponibles' => $asientosDisponibles,
            'nombreAerolinea' => $nombreAerolinea,
        ];
    }
    $consulta->close();
} catch (mysqli_sql_exception $error) {
    error_log('Error al consultar vuelos disponibles: ' . $error->getMessage());
    $errorCarga = 'No se pudieron cargar los vuelos disponibles. Intentá nuevamente más tarde.';
} finally {
    if ($conexion instanceof mysqli) {
        $conexion->close();
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$usuarioPuedeReservar = isset($_SESSION['codUsuario'])
    && ($_SESSION['tipoUsuario'] ?? '') === 'usuario';

?>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vuelos | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>

<body>
    <?php include __DIR__ . '/../plantilla/encabezado.php'; ?>

    <main class="contenido-principal vuelos">
        <section class="vuelos-presentacion" aria-labelledby="titulo-vuelos">
            <p class="vuelos-etiqueta">Planeá tu próximo viaje</p>
            <h1 id="titulo-vuelos">Vuelos disponibles</h1>
            <p>Consultá los próximos vuelos, sus horarios, aerolíneas y asientos disponibles.</p>
        </section>

        <section class="vuelos-grupo" aria-labelledby="vuelos-lista-titulo">
            <h2 id="vuelos-lista-titulo">Próximas salidas</h2>
            <?php if ($errorCarga !== ''): ?>
                <p class="mensaje-autenticacion" role="alert"><?= $escapar($errorCarga) ?></p>
            <?php elseif ($vuelos === []): ?>
                <div class="vuelos-sin-resultados" role="status">
                    <h3>No hay vuelos disponibles por el momento</h3>
                    <p>Los vuelos futuros con asientos disponibles aparecerán en esta sección.</p>
                </div>
            <?php else: ?>
                <div class="vuelos-lista vuelos-lista-columna">
                    <?php foreach ($vuelos as $vuelo): ?>
                        <article class="item-vuelo" aria-label="Vuelo de <?= $escapar((string) $vuelo['origenVuelo']) ?> a <?= $escapar((string) $vuelo['destinoVuelo']) ?>">
                            <div class="item-vuelo-icono" aria-hidden="true">
                                <img src="../../publico/icon/airplane-svgrepo-com.svg" alt="">
                            </div>
                            <div class="item-vuelo-detalles">
                                <p class="item-vuelo-aerolinea"><?= $escapar((string) $vuelo['nombreAerolinea']) ?> · Vuelo #<?= (int) $vuelo['codVuelo'] ?></p>
                                <h3 class="item-vuelo-ruta">
                                    <strong><?= $escapar((string) $vuelo['origenVuelo']) ?> <span aria-hidden="true">→</span> <?= $escapar((string) $vuelo['destinoVuelo']) ?></strong>
                                </h3>
                                <p class="item-vuelo-fechas">
                                    Salida: <?= $escapar(date('d/m/Y', strtotime((string) $vuelo['fechaSalidaVuelo']))) ?>
                                    · <?= $escapar(substr((string) $vuelo['horaSalidaVuelo'], 0, 5)) ?>
                                    · <?= (int) $vuelo['asientosDisponibles'] ?>
                                    <?= (int) $vuelo['asientosDisponibles'] === 1 ? 'asiento disponible' : 'asientos disponibles' ?>
                                </p>
                            </div>
                            <div class="item-vuelo-accion">
                                <?php if ($vuelo['descuentoPromocion'] !== null): ?>
                                    <p class="item-vuelo-precio"><del>$ <?= number_format((float) $vuelo['precioOriginal'], 2, ',', '.') ?></del> $ <?= number_format((float) $vuelo['precioVuelo'], 2, ',', '.') ?></p>
                                    <p>Promoción: -<?= number_format((float) $vuelo['descuentoPromocion'], 2, ',', '.') ?>%</p>
                                <?php else: ?>
                                    <p class="item-vuelo-precio">$ <?= number_format((float) $vuelo['precioVuelo'], 2, ',', '.') ?></p>
                                <?php endif; ?>
                                <?php if ($usuarioPuedeReservar): ?>
                                    <a class="boton-vuelo-reservar" href="/Paginas/usuario/reservar_vuelo.php">Reservar</a>
                                <?php elseif (!isset($_SESSION['codUsuario'])): ?>
                                    <a class="boton-vuelo-reservar" href="/Paginas/autenticacion/iniciar_sesion.php">Iniciá sesión para reservar</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include __DIR__ . '/../plantilla/pie_pagina.php'; ?>
</body>

</html>