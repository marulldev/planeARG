<?php
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/conexion.php';

if (!isset($_SESSION['codUsuario'])) {
    header('Location: /Paginas/autenticacion/iniciar_sesion.php');
    exit;
}

if (($_SESSION['tipoUsuario'] ?? '') !== 'ceo de aerolinea') {
    if (($_SESSION['tipoUsuario'] ?? '') === 'administrador') {
        header('Location: /Paginas/admin/menu_admin.php');
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
$fechaDesdeRecibida = $_GET['fecha_desde'] ?? '';
$fechaHastaRecibida = $_GET['fecha_hasta'] ?? '';
$fechaDesde = is_string($fechaDesdeRecibida) ? trim($fechaDesdeRecibida) : '';
$fechaHasta = is_string($fechaHastaRecibida) ? trim($fechaHastaRecibida) : '';
$ocupacionRecibida = $_GET['ocupacion'] ?? '';
$ocupacion = is_string($ocupacionRecibida) ? $ocupacionRecibida : '';
$imprimirRecibido = $_GET['imprimir'] ?? '';
$imprimir = is_string($imprimirRecibido) && $imprimirRecibido === '1';
$paginaSolicitada = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
$pagina = max(1, $paginaSolicitada === false ? 1 : $paginaSolicitada);
$elementosPorPagina = 10;
$totalVuelos = 0;
$totalCapacidad = 0;
$totalOcupados = 0;
$totalPaginas = 1;
$vuelos = [];
$aerolinea = null;
$errorFiltros = '';
$errorReporte = '';
$codUsuario = (int) $_SESSION['codUsuario'];

$fechaEsValida = static function (string $fecha): bool {
    if ($fecha === '') {
        return true;
    }

    $fechaAnalizada = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    $erroresFecha = DateTimeImmutable::getLastErrors();

    return $fechaAnalizada !== false
        && $fechaAnalizada->format('Y-m-d') === $fecha
        && ($erroresFecha === false
            || ($erroresFecha['warning_count'] === 0 && $erroresFecha['error_count'] === 0));
};

if (!$fechaEsValida($fechaDesde) || !$fechaEsValida($fechaHasta)) {
    $errorFiltros = 'Ingresá fechas válidas para aplicar el filtro.';
} elseif ($fechaDesde !== '' && $fechaHasta !== '' && $fechaDesde > $fechaHasta) {
    $errorFiltros = 'La fecha desde no puede ser posterior a la fecha hasta.';
}

if ($ocupacion !== '' && !array_key_exists($ocupacion, $rangosOcupacion)) {
    $errorFiltros = 'Seleccioná un rango de ocupación válido.';
}

$conexion = null;
try {
    $conexion = conectarBaseDeDatos();
    $consultaAerolinea = $conexion->prepare(
        "SELECT a.codAerolinea, a.nombreAerolinea
         FROM usuarios AS u
         INNER JOIN aerolineas AS a ON a.codAerolinea = u.codAerolinea
         WHERE u.codUsuario = ? AND u.tipoUsuario = 'ceo de aerolinea'
         LIMIT 1"
    );
    $consultaAerolinea->bind_param('i', $codUsuario);
    $consultaAerolinea->execute();
    $consultaAerolinea->bind_result($codAerolinea, $nombreAerolinea);
    if ($consultaAerolinea->fetch()) {
        $aerolinea = [
            'codAerolinea' => (int) $codAerolinea,
            'nombreAerolinea' => (string) $nombreAerolinea,
        ];
    }
    $consultaAerolinea->close();

    if ($aerolinea !== null && $errorFiltros === '') {
        $codAerolineaConsulta = $aerolinea['codAerolinea'];
        $condiciones = "
            v.codAerolinea = ?
            AND (? = '' OR v.fechaSalidaVuelo >= ?)
            AND (? = '' OR v.fechaSalidaVuelo <= ?)
            AND (
                ? = ''
                OR (
                    ? = 'menos20'
                    AND v.capacidadTotal > 0
                    AND GREATEST(0, LEAST(100,
                        (v.capacidadTotal - v.asientosDisponibles) * 100.0 / v.capacidadTotal
                    )) < 20
                )
                OR (
                    ? = '20a80'
                    AND v.capacidadTotal > 0
                    AND GREATEST(0, LEAST(100,
                        (v.capacidadTotal - v.asientosDisponibles) * 100.0 / v.capacidadTotal
                    )) >= 20
                    AND GREATEST(0, LEAST(100,
                        (v.capacidadTotal - v.asientosDisponibles) * 100.0 / v.capacidadTotal
                    )) < 80
                )
                OR (
                    ? = '80a99'
                    AND v.capacidadTotal > 0
                    AND GREATEST(0, LEAST(100,
                        (v.capacidadTotal - v.asientosDisponibles) * 100.0 / v.capacidadTotal
                    )) >= 80
                    AND GREATEST(0, LEAST(100,
                        (v.capacidadTotal - v.asientosDisponibles) * 100.0 / v.capacidadTotal
                    )) < 100
                )
                OR (
                    ? = '100'
                    AND v.capacidadTotal > 0
                    AND v.asientosDisponibles <= 0
                )
            )";
        $parametrosFiltro = [
            $codAerolineaConsulta,
            $fechaDesde,
            $fechaDesde,
            $fechaHasta,
            $fechaHasta,
            $ocupacion,
            $ocupacion,
            $ocupacion,
            $ocupacion,
            $ocupacion,
        ];
        $tiposFiltro = 'i' . str_repeat('s', 9);

        $consultaCantidad = $conexion->prepare(
            'SELECT COUNT(*) FROM vuelos AS v WHERE ' . $condiciones
        );
        $consultaCantidad->bind_param($tiposFiltro, ...$parametrosFiltro);
        $consultaCantidad->execute();
        $consultaCantidad->bind_result($totalVuelos);
        $consultaCantidad->fetch();
        $consultaCantidad->close();
        $totalVuelos = (int) $totalVuelos;

        $totalPaginas = max(1, (int) ceil($totalVuelos / $elementosPorPagina));
        $pagina = min($pagina, $totalPaginas);

        $consultaResumen = $conexion->prepare(
            'SELECT COALESCE(SUM(v.capacidadTotal), 0),
                    COALESCE(SUM(GREATEST(0, LEAST(
                        v.capacidadTotal,
                        v.capacidadTotal - v.asientosDisponibles
                    ))), 0)
             FROM vuelos AS v
             WHERE ' . $condiciones
        );
        $consultaResumen->bind_param($tiposFiltro, ...$parametrosFiltro);
        $consultaResumen->execute();
        $consultaResumen->bind_result($totalCapacidad, $totalOcupados);
        $consultaResumen->fetch();
        $consultaResumen->close();
        $totalCapacidad = (int) $totalCapacidad;
        $totalOcupados = (int) $totalOcupados;

        $consultaVuelos = $conexion->prepare(
            'SELECT v.codVuelo, v.origenVuelo, v.destinoVuelo, v.fechaSalidaVuelo,
                    v.horaSalidaVuelo, v.asientosDisponibles, v.capacidadTotal, v.precioVuelo
             FROM vuelos AS v
             WHERE ' . $condiciones . '
             ORDER BY v.fechaSalidaVuelo DESC, v.horaSalidaVuelo DESC, v.codVuelo DESC'
             . ($imprimir ? '' : ' LIMIT ? OFFSET ?')
        );

        if ($imprimir) {
            $consultaVuelos->bind_param($tiposFiltro, ...$parametrosFiltro);
        } else {
            $desplazamiento = ($pagina - 1) * $elementosPorPagina;
            $parametrosConsulta = [...$parametrosFiltro, $elementosPorPagina, $desplazamiento];
            $consultaVuelos->bind_param($tiposFiltro . 'ii', ...$parametrosConsulta);
        }

        $consultaVuelos->execute();
        $consultaVuelos->bind_result(
            $codVuelo,
            $origenVuelo,
            $destinoVuelo,
            $fechaSalidaVuelo,
            $horaSalidaVuelo,
            $asientosDisponibles,
            $capacidadTotal,
            $precioVuelo
        );

        while ($consultaVuelos->fetch()) {
            $capacidad = (int) $capacidadTotal;
            $asientosRestantes = (int) $asientosDisponibles;
            $asientosOcupados = $capacidad > 0
                ? max(0, min($capacidad, $capacidad - $asientosRestantes))
                : 0;

            $vuelos[] = [
                'codVuelo' => $codVuelo,
                'origenVuelo' => $origenVuelo,
                'destinoVuelo' => $destinoVuelo,
                'fechaSalidaVuelo' => $fechaSalidaVuelo,
                'horaSalidaVuelo' => $horaSalidaVuelo,
                'asientosDisponibles' => $asientosRestantes,
                'capacidadTotal' => $capacidad,
                'asientosOcupados' => $asientosOcupados,
                'precioVuelo' => $precioVuelo,
                'porcentajeOcupacion' => $capacidad > 0
                    ? ($asientosOcupados / $capacidad) * 100
                    : null,
            ];
        }
        $consultaVuelos->close();
    }
} catch (mysqli_sql_exception $error) {
    error_log('Error al cargar el reporte de ocupación del CEO: ' . $error->getMessage());
    $errorReporte = 'No se pudo cargar el reporte de ocupación. Verificá la conexión y que los vuelos tengan definida su capacidad total.';
} finally {
    if ($conexion instanceof mysqli) {
        $conexion->close();
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$parametrosUrl = array_filter([
    'fecha_desde' => $fechaDesde,
    'fecha_hasta' => $fechaHasta,
    'ocupacion' => $ocupacion,
], static fn(string $valor): bool => $valor !== '');
$urlPagina = static fn(int $numeroPagina): string => '?' . http_build_query(
    $parametrosUrl + ['pagina' => $numeroPagina]
);
$urlImpresion = '?' . http_build_query($parametrosUrl + ['imprimir' => 1]);
$porcentajeOcupacionGeneral = $totalCapacidad > 0
    ? ($totalOcupados / $totalCapacidad) * 100
    : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de ocupación | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../plantilla/encabezado.php'; ?>

    <main class="contenido-principal reporte-vuelos" aria-labelledby="titulo-reporte-ocupacion">
        <div class="reporte-vuelos-encabezado">
            <div>
                <p class="reporte-vuelos-etiqueta">Reporte de aerolínea</p>
                <h1 id="titulo-reporte-ocupacion">Reporte de ocupación</h1>
                <?php if ($aerolinea !== null): ?>
                    <p><?= $escapar($aerolinea['nombreAerolinea']) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($errorReporte === '' && $aerolinea !== null && $errorFiltros === ''): ?>
                <a class="reporte-vuelos-boton reporte-vuelos-imprimir" href="<?= $escapar($urlImpresion) ?>" target="_blank" rel="noopener">
                    Imprimir resultados
                </a>
            <?php endif; ?>
        </div>

        <?php if ($aerolinea !== null): ?>
            <form class="reporte-vuelos-filtros" method="get" action="ocupacion.php">
                <div class="reporte-vuelos-campo">
                    <label for="fecha-desde">Salida desde</label>
                    <input id="fecha-desde" name="fecha_desde" type="date" value="<?= $escapar($fechaDesde) ?>">
                </div>
                <div class="reporte-vuelos-campo">
                    <label for="fecha-hasta">Salida hasta</label>
                    <input id="fecha-hasta" name="fecha_hasta" type="date" value="<?= $escapar($fechaHasta) ?>">
                </div>
                <div class="reporte-vuelos-campo">
                    <label for="filtro-ocupacion">Ocupación</label>
                    <select id="filtro-ocupacion" name="ocupacion">
                        <option value="">Todos los porcentajes</option>
                        <?php foreach ($rangosOcupacion as $valor => $etiqueta): ?>
                            <option value="<?= $escapar($valor) ?>" <?= $ocupacion === $valor ? 'selected' : '' ?>>
                                <?= $escapar($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="reporte-vuelos-boton reporte-vuelos-aplicar" type="submit">Aplicar filtros</button>
            </form>
        <?php endif; ?>

        <?php if ($errorFiltros !== ''): ?>
            <p class="reporte-vuelos-mensaje error" role="alert"><?= $escapar($errorFiltros) ?></p>
        <?php endif; ?>
        <?php if ($errorReporte !== ''): ?>
            <p class="reporte-vuelos-mensaje error" role="alert"><?= $escapar($errorReporte) ?></p>
        <?php endif; ?>

        <?php if ($errorReporte === '' && $aerolinea === null): ?>
            <p class="reporte-vuelos-vacio" role="status">Tu cuenta no está asociada a una aerolínea. Pedile al administrador que complete la asignación.</p>
        <?php elseif ($errorReporte === '' && $errorFiltros === ''): ?>
            <?php if ($imprimir): ?>
                <p class="reporte-vuelos-resumen-impresion">
                    <?= $escapar($aerolinea['nombreAerolinea']) ?> ·
                    Salidas:
                    <?= $fechaDesde !== '' ? $escapar(date('d/m/Y', strtotime($fechaDesde))) : 'cualquier fecha' ?>
                    a
                    <?= $fechaHasta !== '' ? $escapar(date('d/m/Y', strtotime($fechaHasta))) : 'cualquier fecha' ?> ·
                    <?= $ocupacion !== '' ? $escapar($rangosOcupacion[$ocupacion]) : 'todos los niveles de ocupación' ?>
                </p>
            <?php endif; ?>

            <section class="reporte-ocupacion-resumen" aria-label="Resumen de ocupación">
                <p><span>Vuelos:</span> <?= $totalVuelos ?></p>
                <p><span>Asientos ocupados:</span> <?= $totalOcupados ?> / <?= $totalCapacidad ?></p>
                <p><span>Ocupación general:</span>
                    <?= $porcentajeOcupacionGeneral === null
                        ? 'Sin capacidad'
                        : number_format($porcentajeOcupacionGeneral, 1, ',', '.') . '%' ?>
                </p>
            </section>
            <p class="reporte-ocupacion-nota">Los asientos ocupados incluyen reservas pendientes de pago, ya que retienen disponibilidad.</p>

            <section class="reporte-vuelos-listado" aria-label="Ocupación de vuelos de la aerolínea" aria-live="polite">
                <?php if ($vuelos === []): ?>
                    <p class="reporte-vuelos-vacio">No hay vuelos que coincidan con los filtros seleccionados.</p>
                <?php else: ?>
                    <?php foreach ($vuelos as $vuelo): ?>
                        <article class="reporte-vuelos-fila">
                            <div class="reporte-vuelos-datos">
                                <p><span>Código de vuelo:</span> #<?= (int) $vuelo['codVuelo'] ?></p>
                                <p><span>Ruta:</span> <?= $escapar((string) $vuelo['origenVuelo']) ?> → <?= $escapar((string) $vuelo['destinoVuelo']) ?></p>
                            </div>
                            <div class="reporte-vuelos-datos">
                                <p><span>Salida:</span>
                                    <?= $escapar(date('d/m/Y', strtotime((string) $vuelo['fechaSalidaVuelo']))) ?>
                                    · <?= $escapar(substr((string) $vuelo['horaSalidaVuelo'], 0, 5)) ?>
                                </p>
                                <p><span>Precio:</span> $ <?= number_format((float) $vuelo['precioVuelo'], 2, ',', '.') ?></p>
                            </div>
                            <div class="reporte-vuelos-datos reporte-ocupacion-datos">
                                <p><span>Asientos ocupados:</span> <?= (int) $vuelo['asientosOcupados'] ?> / <?= (int) $vuelo['capacidadTotal'] ?></p>
                                <p><span>Disponibles:</span> <?= (int) $vuelo['asientosDisponibles'] ?></p>
                                <p><span>Ocupación:</span>
                                    <?= $vuelo['porcentajeOcupacion'] === null
                                        ? 'Sin capacidad'
                                        : number_format((float) $vuelo['porcentajeOcupacion'], 1, ',', '.') . '%' ?>
                                </p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <?php if (!$imprimir && $totalVuelos > 0): ?>
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
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../../plantilla/pie_pagina.php'; ?>
    <?php if ($imprimir && $errorReporte === '' && $errorFiltros === '' && $aerolinea !== null): ?>
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    <?php endif; ?>
</body>
</html>