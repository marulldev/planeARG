<?php
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/conexion.php';

if (!isset($_SESSION['codUsuario'])) {
    header('Location: /Paginas/autenticacion/iniciar_sesion.php');
    exit;
}

if (($_SESSION['tipoUsuario'] ?? '') !== 'administrador') {
    if (($_SESSION['tipoUsuario'] ?? '') === 'ceo de aerolinea') {
        header('Location: /Paginas/ceo/Menu%20ceo/panel_control.php');
    } elseif (($_SESSION['tipoUsuario'] ?? '') === 'usuario') {
        header('Location: /Paginas/usuario/menu_usuario.php');
    } else {
        header('Location: /');
    }
    exit;
}

$rangosOcupacion = [
    'menos20' => 'Menos de 20%',
    '20a80' => 'De 20% a menos de 80%',
    '80a99' => 'De 80% a menos de 100%',
    '100' => '100%',
];
$aerolineaEntrada = $_GET['aerolinea'] ?? '';
$aerolineaRecibida = $aerolineaEntrada === ''
    ? 0
    : filter_var($aerolineaEntrada, FILTER_VALIDATE_INT);
$codAerolinea = $aerolineaRecibida === false || $aerolineaRecibida < 0 ? 0 : $aerolineaRecibida;
$origenRecibido = $_GET['origen'] ?? '';
$origen = is_string($origenRecibido) ? trim($origenRecibido) : '';
$destinoRecibido = $_GET['destino'] ?? '';
$destino = is_string($destinoRecibido) ? trim($destinoRecibido) : '';
$fechaDesdeRecibida = $_GET['fecha_desde'] ?? '';
$fechaDesde = is_string($fechaDesdeRecibida) ? trim($fechaDesdeRecibida) : '';
$fechaHastaRecibida = $_GET['fecha_hasta'] ?? '';
$fechaHasta = is_string($fechaHastaRecibida) ? trim($fechaHastaRecibida) : '';
$ocupacionRecibida = $_GET['ocupacion'] ?? '';
$ocupacion = is_string($ocupacionRecibida) ? $ocupacionRecibida : '';
$imprimirRecibido = $_GET['imprimir'] ?? '';
$imprimir = is_string($imprimirRecibido) && $imprimirRecibido === '1';
$paginaSolicitada = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
$pagina = max(1, $paginaSolicitada === false ? 1 : $paginaSolicitada);
$elementosPorPagina = 7;
$errorFiltros = '';
$errorReporte = '';
$aerolineas = [];
$origenes = [];
$destinos = [];
$vuelos = [];
$totalVuelos = 0;
$totalPaginas = 1;

$fechaValida = static function (string $fecha): bool {
    if ($fecha === '') {
        return true;
    }

    $fechaAnalizada = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $erroresFecha = DateTimeImmutable::getLastErrors();

    return $fechaAnalizada !== false
        && $fechaAnalizada->format('Y-m-d') === $fecha
        && ($erroresFecha === false || ($erroresFecha['warning_count'] === 0 && $erroresFecha['error_count'] === 0));
};

if ($aerolineaRecibida === false || $aerolineaRecibida < 0) {
    $errorFiltros = 'Seleccioná una aerolínea válida.';
}
if (!$fechaValida($fechaDesde) || !$fechaValida($fechaHasta)) {
    $errorFiltros = 'Ingresá un rango de fechas válido.';
} elseif ($fechaDesde !== '' && $fechaHasta !== '' && $fechaDesde > $fechaHasta) {
    $errorFiltros = 'La fecha desde no puede ser posterior a la fecha hasta.';
}
if ($ocupacion !== '' && !array_key_exists($ocupacion, $rangosOcupacion)) {
    $errorFiltros = 'Seleccioná un rango de ocupación válido.';
}

$conexion = null;
try {
    $conexion = conectarBaseDeDatos();
    $resultadoAerolineas = $conexion->query(
        'SELECT codAerolinea, nombreAerolinea FROM aerolineas ORDER BY nombreAerolinea'
    );
    $aerolineas = $resultadoAerolineas->fetch_all(MYSQLI_ASSOC);
    $resultadoAerolineas->free();

    $resultadoOrigenes = $conexion->query(
        'SELECT DISTINCT origenVuelo FROM vuelos ORDER BY origenVuelo'
    );
    $origenes = array_column($resultadoOrigenes->fetch_all(MYSQLI_ASSOC), 'origenVuelo');
    $resultadoOrigenes->free();

    $resultadoDestinos = $conexion->query(
        'SELECT DISTINCT destinoVuelo FROM vuelos ORDER BY destinoVuelo'
    );
    $destinos = array_column($resultadoDestinos->fetch_all(MYSQLI_ASSOC), 'destinoVuelo');
    $resultadoDestinos->free();

    $aerolineaValida = $codAerolinea === 0;
    foreach ($aerolineas as $aerolinea) {
        if ((int) $aerolinea['codAerolinea'] === $codAerolinea) {
            $aerolineaValida = true;
            break;
        }
    }
    if (!$aerolineaValida) {
        $errorFiltros = 'Seleccioná una aerolínea válida.';
    }
    if ($origen !== '' && !in_array($origen, $origenes, true)) {
        $errorFiltros = 'Seleccioná un origen válido.';
    }
    if ($destino !== '' && !in_array($destino, $destinos, true)) {
        $errorFiltros = 'Seleccioná un destino válido.';
    }

    if ($errorFiltros === '') {
        $condicionesFiltro = "
            (? = 0 OR v.codAerolinea = ?)
            AND (? = '' OR v.origenVuelo = ?)
            AND (? = '' OR v.destinoVuelo = ?)
            AND (? = '' OR v.fechaSalidaVuelo >= ?)
            AND (? = '' OR v.fechaSalidaVuelo <= ?)
            AND (
                ? = ''
                OR (
                    ? = 'menos20'
                    AND v.capacidadTotal > 0
                    AND GREATEST(0, LEAST(100, (v.capacidadTotal - v.asientosDisponibles) * 100.0 / v.capacidadTotal)) < 20
                )
                OR (
                    ? = '20a80'
                    AND v.capacidadTotal > 0
                    AND GREATEST(0, LEAST(100, (v.capacidadTotal - v.asientosDisponibles) * 100.0 / v.capacidadTotal)) >= 20
                    AND GREATEST(0, LEAST(100, (v.capacidadTotal - v.asientosDisponibles) * 100.0 / v.capacidadTotal)) < 80
                )
                OR (
                    ? = '80a99'
                    AND v.capacidadTotal > 0
                    AND GREATEST(0, LEAST(100, (v.capacidadTotal - v.asientosDisponibles) * 100.0 / v.capacidadTotal)) >= 80
                    AND GREATEST(0, LEAST(100, (v.capacidadTotal - v.asientosDisponibles) * 100.0 / v.capacidadTotal)) < 100
                )
                OR (
                    ? = '100'
                    AND v.capacidadTotal > 0
                    AND v.asientosDisponibles <= 0
                )
            )";
        $tiposFiltro = 'ii' . str_repeat('s', 13);

        $consultaCantidad = $conexion->prepare(
            'SELECT COUNT(*) FROM vuelos AS v WHERE ' . $condicionesFiltro
        );
        $consultaCantidad->bind_param(
            $tiposFiltro,
            $codAerolinea,
            $codAerolinea,
            $origen,
            $origen,
            $destino,
            $destino,
            $fechaDesde,
            $fechaDesde,
            $fechaHasta,
            $fechaHasta,
            $ocupacion,
            $ocupacion,
            $ocupacion,
            $ocupacion,
            $ocupacion
        );
        $consultaCantidad->execute();
        $consultaCantidad->bind_result($totalVuelos);
        $consultaCantidad->fetch();
        $consultaCantidad->close();
        $totalVuelos = (int) $totalVuelos;

        $totalPaginas = max(1, (int) ceil($totalVuelos / $elementosPorPagina));
        $pagina = min($pagina, $totalPaginas);
        $consultaVuelos = $conexion->prepare(
            'SELECT v.codVuelo, v.codAerolinea, a.nombreAerolinea,
                    v.origenVuelo, v.destinoVuelo, v.fechaSalidaVuelo,
                    v.horaSalidaVuelo, v.asientosDisponibles, v.capacidadTotal, v.precioVuelo
             FROM vuelos AS v
             INNER JOIN aerolineas AS a ON a.codAerolinea = v.codAerolinea
             WHERE ' . $condicionesFiltro . '
             ORDER BY v.fechaSalidaVuelo DESC, v.horaSalidaVuelo DESC, v.codVuelo DESC'
             . ($imprimir ? '' : ' LIMIT ? OFFSET ?')
        );

        if ($imprimir) {
            $consultaVuelos->bind_param(
                $tiposFiltro,
                $codAerolinea,
                $codAerolinea,
                $origen,
                $origen,
                $destino,
                $destino,
                $fechaDesde,
                $fechaDesde,
                $fechaHasta,
                $fechaHasta,
                $ocupacion,
                $ocupacion,
                $ocupacion,
                $ocupacion,
                $ocupacion
            );
        } else {
            $desplazamiento = ($pagina - 1) * $elementosPorPagina;
            $consultaVuelos->bind_param(
                $tiposFiltro . 'ii',
                $codAerolinea,
                $codAerolinea,
                $origen,
                $origen,
                $destino,
                $destino,
                $fechaDesde,
                $fechaDesde,
                $fechaHasta,
                $fechaHasta,
                $ocupacion,
                $ocupacion,
                $ocupacion,
                $ocupacion,
                $ocupacion,
                $elementosPorPagina,
                $desplazamiento
            );
        }
        $consultaVuelos->execute();
        $vuelos = $consultaVuelos->get_result()->fetch_all(MYSQLI_ASSOC);
        $consultaVuelos->close();
    }
} catch (mysqli_sql_exception $error) {
    error_log('Error al cargar el reporte de vuelos: ' . $error->getMessage());
    $errorReporte = 'No se pudo cargar el reporte de vuelos. Verificá la conexión y que se haya aplicado la migración de capacidad total.';
} finally {
    if ($conexion instanceof mysqli) {
        $conexion->close();
    }
}

foreach ($vuelos as &$vuelo) {
    $capacidadTotal = (int) $vuelo['capacidadTotal'];
    $asientosDisponibles = (int) $vuelo['asientosDisponibles'];
    $asientosVendidos = max(0, min($capacidadTotal, $capacidadTotal - $asientosDisponibles));
    $vuelo['asientosVendidos'] = $asientosVendidos;
    $vuelo['porcentajeVendido'] = $capacidadTotal > 0
        ? ($asientosVendidos / $capacidadTotal) * 100
        : null;
}
unset($vuelo);

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$parametrosFiltro = array_filter([
    'aerolinea' => $codAerolinea > 0 ? $codAerolinea : '',
    'origen' => $origen,
    'destino' => $destino,
    'fecha_desde' => $fechaDesde,
    'fecha_hasta' => $fechaHasta,
    'ocupacion' => $ocupacion,
], static fn($valor): bool => $valor !== '');
$urlPagina = static function (int $numeroPagina) use ($parametrosFiltro): string {
    return '?' . http_build_query($parametrosFiltro + ['pagina' => $numeroPagina]);
};
$urlImpresion = '?' . http_build_query($parametrosFiltro + ['imprimir' => 1]);
$nombreAerolineaSeleccionada = '';
foreach ($aerolineas as $aerolinea) {
    if ((int) $aerolinea['codAerolinea'] === $codAerolinea) {
        $nombreAerolineaSeleccionada = (string) $aerolinea['nombreAerolinea'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de vuelos | PlaneARG</title>
    <link rel="stylesheet" href="../../../publico/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../../plantilla/encabezado.php'; ?>

    <main class="contenido-principal reporte-vuelos" aria-labelledby="titulo-reporte-vuelos">
        <div class="reporte-vuelos-encabezado">
            <div>
                <p class="reporte-vuelos-etiqueta">Administración</p>
                <h1 id="titulo-reporte-vuelos">Reporte de vuelos</h1>
            </div>
            <a class="reporte-vuelos-boton reporte-vuelos-imprimir" href="<?= $escapar($urlImpresion) ?>" target="_blank" rel="noopener">
                Imprimir resultados
            </a>
        </div>

        <form class="reporte-vuelos-filtros" method="get" action="rep_vuelos.php">
            <div class="reporte-vuelos-campo">
                <label for="filtro-aerolinea">Aerolínea</label>
                <select id="filtro-aerolinea" name="aerolinea">
                    <option value="">Todas</option>
                    <?php foreach ($aerolineas as $aerolinea): ?>
                        <option value="<?= (int) $aerolinea['codAerolinea'] ?>"
                            <?= (int) $aerolinea['codAerolinea'] === $codAerolinea ? 'selected' : '' ?>>
                            <?= $escapar((string) $aerolinea['nombreAerolinea']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="reporte-vuelos-campo">
                <label for="filtro-origen">Origen</label>
                <select id="filtro-origen" name="origen">
                    <option value="">Todos</option>
                    <?php foreach ($origenes as $origenDisponible): ?>
                        <option value="<?= $escapar((string) $origenDisponible) ?>" <?= $origen === $origenDisponible ? 'selected' : '' ?>>
                            <?= $escapar((string) $origenDisponible) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="reporte-vuelos-campo">
                <label for="filtro-destino">Destino</label>
                <select id="filtro-destino" name="destino">
                    <option value="">Todos</option>
                    <?php foreach ($destinos as $destinoDisponible): ?>
                        <option value="<?= $escapar((string) $destinoDisponible) ?>" <?= $destino === $destinoDisponible ? 'selected' : '' ?>>
                            <?= $escapar((string) $destinoDisponible) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="reporte-vuelos-campo">
                <label for="fecha-desde">Salida desde</label>
                <input id="fecha-desde" name="fecha_desde" type="date" value="<?= $escapar($fechaDesde) ?>">
            </div>
            <div class="reporte-vuelos-campo">
                <label for="fecha-hasta">Salida hasta</label>
                <input id="fecha-hasta" name="fecha_hasta" type="date" value="<?= $escapar($fechaHasta) ?>">
            </div>
            <div class="reporte-vuelos-campo">
                <label for="filtro-ocupacion">Ocupación vendida</label>
                <select id="filtro-ocupacion" name="ocupacion">
                    <option value="">Todos los porcentajes</option>
                    <?php foreach ($rangosOcupacion as $valorOcupacion => $etiquetaOcupacion): ?>
                        <option value="<?= $escapar($valorOcupacion) ?>" <?= $ocupacion === $valorOcupacion ? 'selected' : '' ?>>
                            <?= $escapar($etiquetaOcupacion) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="reporte-vuelos-boton reporte-vuelos-aplicar" type="submit">Aplicar filtros</button>
        </form>

        <?php if ($errorFiltros !== ''): ?>
            <p class="reporte-vuelos-mensaje error" role="alert"><?= $escapar($errorFiltros) ?></p>
        <?php endif; ?>
        <?php if ($errorReporte !== ''): ?>
            <p class="reporte-vuelos-mensaje error" role="alert"><?= $escapar($errorReporte) ?></p>
        <?php endif; ?>

        <?php if ($imprimir && $errorReporte === '' && $errorFiltros === ''): ?>
            <p class="reporte-vuelos-resumen-impresion">
                Filtros:
                <?= $nombreAerolineaSeleccionada !== '' ? $escapar($nombreAerolineaSeleccionada) : 'todas las aerolíneas' ?> ·
                <?= $origen !== '' ? 'origen ' . $escapar($origen) : 'todos los orígenes' ?> ·
                <?= $destino !== '' ? 'destino ' . $escapar($destino) : 'todos los destinos' ?> ·
                <?= $fechaDesde !== '' ? 'desde ' . $escapar(date('d/m/Y', strtotime($fechaDesde))) : 'cualquier fecha inicial' ?> ·
                <?= $fechaHasta !== '' ? 'hasta ' . $escapar(date('d/m/Y', strtotime($fechaHasta))) : 'cualquier fecha final' ?> ·
                <?= $ocupacion !== '' ? $escapar($rangosOcupacion[$ocupacion]) : 'cualquier ocupación' ?>
            </p>
        <?php endif; ?>

        <?php if ($errorReporte === '' && $errorFiltros === ''): ?>
            <section class="reporte-vuelos-listado" aria-label="Vuelos que coinciden con los filtros" aria-live="polite">
                <?php if ($vuelos === []): ?>
                    <p class="reporte-vuelos-vacio">No hay vuelos que coincidan con los filtros seleccionados.</p>
                <?php else: ?>
                    <?php foreach ($vuelos as $vuelo): ?>
                        <article class="reporte-vuelos-fila">
                            <div class="reporte-vuelos-datos">
                                <p><span>Código vuelo:</span> <?= (int) $vuelo['codVuelo'] ?></p>
                                <p><span>Aerolínea:</span> <?= $escapar((string) $vuelo['nombreAerolinea']) ?></p>
                            </div>
                            <div class="reporte-vuelos-datos">
                                <p><span>Origen:</span> <?= $escapar((string) $vuelo['origenVuelo']) ?></p>
                                <p><span>Destino:</span> <?= $escapar((string) $vuelo['destinoVuelo']) ?></p>
                            </div>
                            <div class="reporte-vuelos-datos">
                                <p><span>Fecha y hora de salida:</span>
                                    <?= $escapar(date('d/m/Y', strtotime((string) $vuelo['fechaSalidaVuelo']))) ?>
                                    <?= $escapar(substr((string) $vuelo['horaSalidaVuelo'], 0, 5)) ?>
                                </p>
                                <p><span>Asientos vendidos:</span>
                                    <?= (int) $vuelo['asientosVendidos'] ?> / <?= (int) $vuelo['capacidadTotal'] ?>
                                    (<?= $vuelo['porcentajeVendido'] === null ? 'Sin capacidad' : number_format((float) $vuelo['porcentajeVendido'], 1, ',', '.') . '%' ?>)
                                </p>
                                <p><span>Disponibles:</span> <?= (int) $vuelo['asientosDisponibles'] ?>
                                    <span class="reporte-vuelos-precio">Precio: $ <?= number_format((float) $vuelo['precioVuelo'], 2, ',', '.') ?></span>
                                </p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!$imprimir && $errorReporte === '' && $errorFiltros === '' && $totalVuelos > 0): ?>
            <nav class="reporte-vuelos-paginacion" aria-label="Páginas del reporte">
                <span>Mostrando <?= ($pagina - 1) * $elementosPorPagina + 1 ?>–<?= min($pagina * $elementosPorPagina, $totalVuelos) ?> de <?= $totalVuelos ?></span>
                <div>
                    <?php if ($pagina > 1): ?>
                        <a class="reporte-vuelos-boton" href="<?= $escapar($urlPagina($pagina - 1)) ?>" aria-label="Página anterior">Anterior</a>
                    <?php else: ?>
                        <span class="reporte-vuelos-boton deshabilitado" aria-disabled="true">Anterior</span>
                    <?php endif; ?>
                    <span class="reporte-vuelos-pagina">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                    <?php if ($pagina < $totalPaginas): ?>
                        <a class="reporte-vuelos-boton" href="<?= $escapar($urlPagina($pagina + 1)) ?>" aria-label="Página siguiente">Siguiente</a>
                    <?php else: ?>
                        <span class="reporte-vuelos-boton deshabilitado" aria-disabled="true">Siguiente</span>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../../plantilla/pie_pagina.php'; ?>
    <?php if ($imprimir && $errorReporte === '' && $errorFiltros === ''): ?>
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    <?php endif; ?>
</body>
</html>