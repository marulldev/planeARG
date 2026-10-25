<?php
require_once __DIR__ . '/Paginas/includes/sesion.php';
require_once __DIR__ . '/Paginas/includes/conexion.php';

$zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
$fechaActual = (new DateTimeImmutable('today', $zonaHoraria))->format('Y-m-d');
$novedades = [];
$vuelos = [];
$promociones = [];
$errorCarga = '';
$conexion = null;

try {
    $conexion = conectarBaseDeDatos();

    $consultaNovedades = $conexion->prepare(
        'SELECT codNovedad, textoNovedad, fechaPublicacionNovedad
         FROM novedades
         WHERE fechaPublicacionNovedad <= ?
           AND (fechaExpiracionNovedad IS NULL OR fechaExpiracionNovedad >= ?)
         ORDER BY codNovedad DESC
         LIMIT 2'
    );
    $consultaNovedades->bind_param('ss', $fechaActual, $fechaActual);
    $consultaNovedades->execute();
    $consultaNovedades->bind_result($codNovedad, $textoNovedad, $fechaPublicacionNovedad);
    while ($consultaNovedades->fetch()) {
        $novedades[] = [
            'codNovedad' => $codNovedad,
            'textoNovedad' => $textoNovedad,
            'fechaPublicacionNovedad' => $fechaPublicacionNovedad,
        ];
    }
    $consultaNovedades->close();

    $consultaVuelos = $conexion->prepare(
        'SELECT v.codVuelo, v.origenVuelo, v.destinoVuelo, v.fechaSalidaVuelo,
                v.horaSalidaVuelo, v.precioVuelo, v.asientosDisponibles,
                a.nombreAerolinea
         FROM vuelos AS v
         INNER JOIN aerolineas AS a ON a.codAerolinea = v.codAerolinea
         WHERE TIMESTAMP(v.fechaSalidaVuelo, v.horaSalidaVuelo) > NOW()
           AND v.asientosDisponibles > 0
         ORDER BY v.codVuelo DESC
         LIMIT 2'
    );
    $consultaVuelos->execute();
    $consultaVuelos->bind_result(
        $codVuelo,
        $origenVuelo,
        $destinoVuelo,
        $fechaSalidaVuelo,
        $horaSalidaVuelo,
        $precioVuelo,
        $asientosDisponibles,
        $nombreAerolinea
    );
    while ($consultaVuelos->fetch()) {
        $vuelos[] = [
            'codVuelo' => $codVuelo,
            'origenVuelo' => $origenVuelo,
            'destinoVuelo' => $destinoVuelo,
            'fechaSalidaVuelo' => $fechaSalidaVuelo,
            'horaSalidaVuelo' => $horaSalidaVuelo,
            'precioVuelo' => $precioVuelo,
            'asientosDisponibles' => $asientosDisponibles,
            'nombreAerolinea' => $nombreAerolinea,
        ];
    }
    $consultaVuelos->close();

    $consultaPromociones = $conexion->prepare(
        "SELECT p.codPromocion, p.descripcionPromocion, p.descuentoPromocion,
                p.fechaExpiracionPromocion, a.nombreAerolinea
         FROM promociones AS p
         INNER JOIN aerolineas AS a ON a.codAerolinea = p.codAerolinea
         WHERE p.estadoPromocion = 'aprobada'
           AND (p.fechaExpiracionPromocion IS NULL OR p.fechaExpiracionPromocion >= ?)
         ORDER BY p.codPromocion DESC
         LIMIT 2"
    );
    $consultaPromociones->bind_param('s', $fechaActual);
    $consultaPromociones->execute();
    $consultaPromociones->bind_result(
        $codPromocion,
        $descripcionPromocion,
        $descuentoPromocion,
        $fechaExpiracionPromocion,
        $nombreAerolinea
    );
    while ($consultaPromociones->fetch()) {
        $promociones[] = [
            'codPromocion' => $codPromocion,
            'descripcionPromocion' => $descripcionPromocion,
            'descuentoPromocion' => $descuentoPromocion,
            'fechaExpiracionPromocion' => $fechaExpiracionPromocion,
            'nombreAerolinea' => $nombreAerolinea,
        ];
    }
    $consultaPromociones->close();
} catch (mysqli_sql_exception $error) {
    error_log('Error al cargar el contenido de inicio: ' . $error->getMessage());
    $errorCarga = 'No se pudo cargar el contenido de inicio. Intentá nuevamente más tarde.';
} finally {
    if ($conexion instanceof mysqli) {
        $conexion->close();
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>planeARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>



<body>
    <?php
    $espacioEnBlanco = false;
    include __DIR__ . "/Paginas/plantilla/encabezado.php";
    ?>

    <main class="contenido-principal">
        <section class="portada-inicio" aria-labelledby="titulo-inicio">

            <video class="video-portada" autoplay muted loop playsinline aria-hidden="true">
                <source src="publico/Videos/gemini_generated_video_e538cfe8.mp4" type="video/mp4">
            </video>

            <div class="contenido-portada">
                <h1 id="titulo-inicio">Plane<span>ARG</span></h1>
            </div>

        </section>

        <div class="inicio-secciones">
            <?php if ($errorCarga !== ''): ?>
                <p class="mensaje-autenticacion" role="alert"><?= $escapar($errorCarga) ?></p>
            <?php endif; ?>

            <section class="inicio-seccion" aria-labelledby="titulo-novedades-inicio">
                <div class="inicio-seccion-encabezado">
                    <h2 id="titulo-novedades-inicio">Últimas novedades</h2>
                    <a href="/Paginas/novedades/listar.php">Ver todas</a>
                </div>
                <?php if ($novedades === [] && $errorCarga === ''): ?>
                    <p class="inicio-vacio">Por ahora no hay novedades vigentes.</p>
                <?php elseif ($novedades !== []): ?>
                    <div class="inicio-lista inicio-lista-novedades">
                        <?php foreach ($novedades as $novedad): ?>
                            <article class="novedad-publica">
                                <p class="inicio-fecha">
                                    Publicada el
                                    <time datetime="<?= $escapar((string) $novedad['fechaPublicacionNovedad']) ?>">
                                        <?= $escapar((new DateTimeImmutable((string) $novedad['fechaPublicacionNovedad'], $zonaHoraria))->format('d/m/Y')) ?>
                                    </time>
                                </p>
                                <p class="novedad-publica-texto"><?= nl2br($escapar((string) $novedad['textoNovedad'])) ?></p>
                                <span class="novedad-publica-codigo">Novedad #<?= (int) $novedad['codNovedad'] ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="inicio-seccion" aria-labelledby="titulo-vuelos-inicio">
                <div class="inicio-seccion-encabezado">
                    <h2 id="titulo-vuelos-inicio">Últimos vuelos publicados</h2>
                    <a href="/Paginas/Info/vuelos.php">Ver todos</a>
                </div>
                <?php if ($vuelos === [] && $errorCarga === ''): ?>
                    <p class="inicio-vacio">Por ahora no hay vuelos futuros con asientos disponibles.</p>
                <?php elseif ($vuelos !== []): ?>
                    <div class="inicio-lista">
                        <?php foreach ($vuelos as $vuelo): ?>
                            <article class="item-vuelo" aria-label="Vuelo de <?= $escapar((string) $vuelo['origenVuelo']) ?> a <?= $escapar((string) $vuelo['destinoVuelo']) ?>">
                                <div class="item-vuelo-icono" aria-hidden="true">
                                    <img src="/publico/icon/airplane-svgrepo-com.svg" alt="">
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
                                <p class="item-vuelo-precio">$ <?= number_format((float) $vuelo['precioVuelo'], 2, ',', '.') ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="inicio-seccion" aria-labelledby="titulo-promociones-inicio">
                <div class="inicio-seccion-encabezado">
                    <h2 id="titulo-promociones-inicio">Últimas promociones</h2>
                </div>
                <?php if ($promociones === [] && $errorCarga === ''): ?>
                    <p class="inicio-vacio">Por ahora no hay promociones vigentes.</p>
                <?php elseif ($promociones !== []): ?>
                    <div class="inicio-lista">
                        <?php foreach ($promociones as $promocion): ?>
                            <article class="promocion-elemento">
                                <div>
                                    <p class="promocion-aerolinea"><?= $escapar((string) $promocion['nombreAerolinea']) ?></p>
                                    <h3><?= $escapar((string) $promocion['descripcionPromocion']) ?></h3>
                                    <p>
                                        Promoción #<?= (int) $promocion['codPromocion'] ?>
                                        · Descuento: <?= number_format((float) $promocion['descuentoPromocion'], 2, ',', '.') ?>%
                                        · Expira:
                                        <?= $promocion['fechaExpiracionPromocion'] !== null
                                            ? $escapar(date('d/m/Y', strtotime((string) $promocion['fechaExpiracionPromocion'])))
                                            : 'Sin fecha definida' ?>
                                    </p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php include __DIR__ . "/Paginas/plantilla/pie_pagina.php"; ?>
</body>

</html>