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

$fechaDesdeRecibida = $_GET['fecha_desde'] ?? '';
$fechaHastaRecibida = $_GET['fecha_hasta'] ?? '';
$fechaDesde = is_string($fechaDesdeRecibida) ? trim($fechaDesdeRecibida) : '';
$fechaHasta = is_string($fechaHastaRecibida) ? trim($fechaHastaRecibida) : '';
$imprimirRecibido = $_GET['imprimir'] ?? '';
$imprimir = is_string($imprimirRecibido) && $imprimirRecibido === '1';
$paginaSolicitada = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
$pagina = max(1, $paginaSolicitada === false ? 1 : $paginaSolicitada);
$elementosPorPagina = 10;
$totalVentas = 0;
$totalImporte = 0.0;
$totalPaginas = 1;
$ventas = [];
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
        $consultaResumen = $conexion->prepare(
            "SELECT COUNT(*), COALESCE(SUM(r.precioReserva), 0)
             FROM reservas AS r
             INNER JOIN vuelos AS v ON v.codVuelo = r.codVuelo
             WHERE v.codAerolinea = ?
               AND r.estadoReserva = 'confirmada'
               AND (? = '' OR r.fechaReserva >= ?)
               AND (? = '' OR r.fechaReserva <= ?)"
        );
        $consultaResumen->bind_param(
            'issss',
            $codAerolineaConsulta,
            $fechaDesde,
            $fechaDesde,
            $fechaHasta,
            $fechaHasta
        );
        $consultaResumen->execute();
        $consultaResumen->bind_result($totalVentas, $totalImporte);
        $consultaResumen->fetch();
        $consultaResumen->close();

        $totalVentas = (int) $totalVentas;
        $totalImporte = (float) $totalImporte;
        $totalPaginas = max(1, (int) ceil($totalVentas / $elementosPorPagina));
        $pagina = min($pagina, $totalPaginas);

        $consultaVentas = $conexion->prepare(
            "SELECT r.codReserva, r.codUsuario, u.nombreUsuario, r.fechaReserva,
                    v.codVuelo, v.origenVuelo, v.destinoVuelo, v.fechaSalidaVuelo,
                    v.horaSalidaVuelo, r.precioReserva
             FROM reservas AS r
             INNER JOIN usuarios AS u ON u.codUsuario = r.codUsuario
             INNER JOIN vuelos AS v ON v.codVuelo = r.codVuelo
             WHERE v.codAerolinea = ?
               AND r.estadoReserva = 'confirmada'
               AND (? = '' OR r.fechaReserva >= ?)
               AND (? = '' OR r.fechaReserva <= ?)
             ORDER BY r.fechaReserva DESC, r.codReserva DESC"
             . ($imprimir ? '' : ' LIMIT ? OFFSET ?')
        );

        if ($imprimir) {
            $consultaVentas->bind_param(
                'issss',
                $codAerolineaConsulta,
                $fechaDesde,
                $fechaDesde,
                $fechaHasta,
                $fechaHasta
            );
        } else {
            $desplazamiento = ($pagina - 1) * $elementosPorPagina;
            $consultaVentas->bind_param(
                'issssii',
                $codAerolineaConsulta,
                $fechaDesde,
                $fechaDesde,
                $fechaHasta,
                $fechaHasta,
                $elementosPorPagina,
                $desplazamiento
            );
        }

        $consultaVentas->execute();
        $consultaVentas->bind_result(
            $codReserva,
            $codUsuarioVenta,
            $nombreUsuario,
            $fechaReserva,
            $codVuelo,
            $origenVuelo,
            $destinoVuelo,
            $fechaSalidaVuelo,
            $horaSalidaVuelo,
            $precioReserva
        );

        while ($consultaVentas->fetch()) {
            $ventas[] = [
                'codReserva' => $codReserva,
                'codUsuario' => $codUsuarioVenta,
                'nombreUsuario' => $nombreUsuario,
                'fechaReserva' => $fechaReserva,
                'codVuelo' => $codVuelo,
                'origenVuelo' => $origenVuelo,
                'destinoVuelo' => $destinoVuelo,
                'fechaSalidaVuelo' => $fechaSalidaVuelo,
                'horaSalidaVuelo' => $horaSalidaVuelo,
                'precioReserva' => $precioReserva,
            ];
        }
        $consultaVentas->close();
    }
} catch (mysqli_sql_exception $error) {
    error_log('Error al cargar el reporte de ventas del CEO: ' . $error->getMessage());
    $errorReporte = 'No se pudo cargar el reporte de ventas. Intentá nuevamente más tarde.';
} finally {
    if ($conexion instanceof mysqli) {
        $conexion->close();
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$parametrosFiltro = array_filter([
    'fecha_desde' => $fechaDesde,
    'fecha_hasta' => $fechaHasta,
], static fn(string $valor): bool => $valor !== '');
$urlPagina = static fn(int $numeroPagina): string => '?' . http_build_query(
    $parametrosFiltro + ['pagina' => $numeroPagina]
);
$urlImpresion = '?' . http_build_query($parametrosFiltro + ['imprimir' => 1]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de ventas | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../plantilla/encabezado.php'; ?>

    <main class="contenido-principal reporte-ventas" aria-labelledby="titulo-reporte-ventas">
        <div class="reporte-ventas-encabezado">
            <div>
                <p class="reporte-ventas-etiqueta">Reporte de aerolínea</p>
                <h1 id="titulo-reporte-ventas">Reporte de ventas</h1>
                <?php if ($aerolinea !== null): ?>
                    <p><?= $escapar($aerolinea['nombreAerolinea']) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($errorReporte === '' && $aerolinea !== null && $errorFiltros === ''): ?>
                <a class="reporte-ventas-boton reporte-ventas-imprimir" href="<?= $escapar($urlImpresion) ?>" target="_blank" rel="noopener">
                    Imprimir resultados
                </a>
            <?php endif; ?>
        </div>

        <?php if ($aerolinea !== null): ?>
            <form class="reporte-ventas-filtros reporte-ventas-filtros-ceo" method="get" action="ventas.php">
                <div class="reporte-ventas-campo">
                    <label for="fecha-desde">Fecha desde</label>
                    <input id="fecha-desde" name="fecha_desde" type="date" value="<?= $escapar($fechaDesde) ?>">
                </div>
                <div class="reporte-ventas-campo">
                    <label for="fecha-hasta">Fecha hasta</label>
                    <input id="fecha-hasta" name="fecha_hasta" type="date" value="<?= $escapar($fechaHasta) ?>">
                </div>
                <button class="reporte-ventas-boton reporte-ventas-aplicar" type="submit">Aplicar filtros</button>
            </form>
        <?php endif; ?>

        <?php if ($errorFiltros !== ''): ?>
            <p class="reporte-ventas-mensaje error" role="alert"><?= $escapar($errorFiltros) ?></p>
        <?php endif; ?>
        <?php if ($errorReporte !== ''): ?>
            <p class="reporte-ventas-mensaje error" role="alert"><?= $escapar($errorReporte) ?></p>
        <?php endif; ?>

        <?php if ($errorReporte === '' && $aerolinea === null): ?>
            <p class="reporte-ventas-vacio" role="status">Tu cuenta no está asociada a una aerolínea. Pedile al administrador que complete la asignación.</p>
        <?php elseif ($errorReporte === '' && $errorFiltros === ''): ?>
            <?php if ($imprimir): ?>
                <p class="reporte-ventas-resumen-impresion">
                    <?= $aerolinea !== null ? $escapar($aerolinea['nombreAerolinea']) . ' · ' : '' ?>
                    Período:
                    <?= $fechaDesde !== '' ? $escapar(date('d/m/Y', strtotime($fechaDesde))) : 'inicio' ?>
                    al
                    <?= $fechaHasta !== '' ? $escapar(date('d/m/Y', strtotime($fechaHasta))) : 'actualidad' ?>
                </p>
            <?php endif; ?>

            <section class="reporte-ventas-resumen" aria-label="Resumen de ventas">
                <p><span>Ventas confirmadas:</span> <?= $totalVentas ?></p>
                <p><span>Importe total:</span> $ <?= number_format($totalImporte, 2, ',', '.') ?></p>
            </section>

            <section class="reporte-ventas-listado" aria-label="Ventas confirmadas de la aerolínea" aria-live="polite">
                <?php if ($ventas === []): ?>
                    <p class="reporte-ventas-vacio">No hay ventas confirmadas que coincidan con las fechas seleccionadas.</p>
                <?php else: ?>
                    <?php foreach ($ventas as $venta): ?>
                        <article class="reporte-ventas-fila">
                            <div class="reporte-ventas-datos">
                                <p><span>Código de reserva:</span> #<?= (int) $venta['codReserva'] ?></p>
                                <p><span>Pasajero:</span> <?= $escapar((string) $venta['nombreUsuario']) ?> (<?= (int) $venta['codUsuario'] ?>)</p>
                                <p><span>Fecha de compra:</span> <?= $escapar(date('d/m/Y', strtotime((string) $venta['fechaReserva']))) ?></p>
                            </div>
                            <div class="reporte-ventas-datos">
                                <p><span>Vuelo:</span> #<?= (int) $venta['codVuelo'] ?> · <?= $escapar((string) $venta['origenVuelo']) ?> → <?= $escapar((string) $venta['destinoVuelo']) ?></p>
                                <p><span>Salida:</span> <?= $escapar(date('d/m/Y', strtotime((string) $venta['fechaSalidaVuelo']))) ?> · <?= $escapar(substr((string) $venta['horaSalidaVuelo'], 0, 5)) ?></p>
                                <p><span>Importe:</span> $ <?= number_format((float) $venta['precioReserva'], 2, ',', '.') ?> <span class="reporte-ventas-estado">Confirmada</span></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <?php if (!$imprimir && $totalVentas > 0): ?>
                <nav class="reporte-ventas-paginacion" aria-label="Páginas del reporte">
                    <span>Mostrando <?= ($pagina - 1) * $elementosPorPagina + 1 ?>–<?= min($pagina * $elementosPorPagina, $totalVentas) ?> de <?= $totalVentas ?></span>
                    <div>
                        <?php if ($pagina > 1): ?>
                            <a class="reporte-ventas-boton" href="<?= $escapar($urlPagina($pagina - 1)) ?>" aria-label="Página anterior">Anterior</a>
                        <?php else: ?>
                            <span class="reporte-ventas-boton deshabilitado" aria-disabled="true">Anterior</span>
                        <?php endif; ?>
                        <span class="reporte-ventas-pagina">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                        <?php if ($pagina < $totalPaginas): ?>
                            <a class="reporte-ventas-boton" href="<?= $escapar($urlPagina($pagina + 1)) ?>" aria-label="Página siguiente">Siguiente</a>
                        <?php else: ?>
                            <span class="reporte-ventas-boton deshabilitado" aria-disabled="true">Siguiente</span>
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