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

$estadosReserva = ['pendiente de pago', 'confirmada', 'cancelada'];
$fechaMinimaRecibida = $_GET['fecha_minima'] ?? '';
$fechaMinima = is_string($fechaMinimaRecibida) ? trim($fechaMinimaRecibida) : '';
$codAerolinea = filter_var($_GET['aerolinea'] ?? 0, FILTER_VALIDATE_INT);
$estadoRecibido = $_GET['estado'] ?? '';
$estado = is_string($estadoRecibido) ? $estadoRecibido : '';
$imprimirRecibido = $_GET['imprimir'] ?? '';
$imprimir = is_string($imprimirRecibido) && $imprimirRecibido === '1';
$paginaSolicitada = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
$pagina = max(1, $paginaSolicitada === false ? 1 : $paginaSolicitada);
$elementosPorPagina = 7;
$fechaValida = $fechaMinima === '';
$errorFiltros = '';
$errorReporte = '';
$aerolineas = [];
$ventas = [];
$totalVentas = 0;
$totalPaginas = 1;

if ($fechaMinima !== '') {
    $fechaAnalizada = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaMinima);
    $erroresFecha = DateTimeImmutable::getLastErrors();
    $fechaValida = $fechaAnalizada !== false
        && $fechaAnalizada->format('Y-m-d') === $fechaMinima
        && ($erroresFecha === false || ($erroresFecha['warning_count'] === 0 && $erroresFecha['error_count'] === 0));

    if (!$fechaValida) {
        $errorFiltros = 'Ingresá una fecha mínima válida.';
    }
}

if ($codAerolinea === false || $codAerolinea < 0) {
    $codAerolinea = 0;
    $errorFiltros = 'Seleccioná una aerolínea válida.';
}

if ($estado !== '' && !in_array($estado, $estadosReserva, true)) {
    $estado = '';
    $errorFiltros = 'Seleccioná un estado válido.';
}

$conexion = null;
try {
    $conexion = conectarBaseDeDatos();
    $resultadoAerolineas = $conexion->query(
        'SELECT codAerolinea, nombreAerolinea FROM aerolineas ORDER BY nombreAerolinea'
    );
    $aerolineas = $resultadoAerolineas->fetch_all(MYSQLI_ASSOC);
    $resultadoAerolineas->free();

    if ($fechaValida && $errorFiltros === '') {
        $consultaCantidad = $conexion->prepare(
            "SELECT COUNT(*)
             FROM reservas AS r
             INNER JOIN vuelos AS v ON v.codVuelo = r.codVuelo
             WHERE (? = '' OR r.fechaReserva >= ?)
               AND (? = 0 OR v.codAerolinea = ?)
               AND (? = '' OR r.estadoReserva = ?)"
        );
        $consultaCantidad->bind_param(
            'ssiiss',
            $fechaMinima,
            $fechaMinima,
            $codAerolinea,
            $codAerolinea,
            $estado,
            $estado
        );
        $consultaCantidad->execute();
        $consultaCantidad->bind_result($totalVentas);
        $consultaCantidad->fetch();
        $consultaCantidad->close();
        $totalVentas = (int) $totalVentas;

        $totalPaginas = max(1, (int) ceil($totalVentas / $elementosPorPagina));
        $pagina = min($pagina, $totalPaginas);
        $consultaVentas = $conexion->prepare(
            "SELECT r.codReserva, r.codUsuario, u.nombreUsuario, r.fechaReserva,
                    v.codAerolinea, a.nombreAerolinea, v.codVuelo, v.precioVuelo,
                    r.estadoReserva
             FROM reservas AS r
             INNER JOIN usuarios AS u ON u.codUsuario = r.codUsuario
             INNER JOIN vuelos AS v ON v.codVuelo = r.codVuelo
             INNER JOIN aerolineas AS a ON a.codAerolinea = v.codAerolinea
             WHERE (? = '' OR r.fechaReserva >= ?)
               AND (? = 0 OR v.codAerolinea = ?)
               AND (? = '' OR r.estadoReserva = ?)
             ORDER BY r.fechaReserva DESC, r.codReserva DESC"
             . ($imprimir ? '' : ' LIMIT ? OFFSET ?')
        );

        if ($imprimir) {
            $consultaVentas->bind_param(
                'ssiiss',
                $fechaMinima,
                $fechaMinima,
                $codAerolinea,
                $codAerolinea,
                $estado,
                $estado
            );
        } else {
            $desplazamiento = ($pagina - 1) * $elementosPorPagina;
            $consultaVentas->bind_param(
                'ssiissii',
                $fechaMinima,
                $fechaMinima,
                $codAerolinea,
                $codAerolinea,
                $estado,
                $estado,
                $elementosPorPagina,
                $desplazamiento
            );
        }
        $consultaVentas->execute();
        $ventas = $consultaVentas->get_result()->fetch_all(MYSQLI_ASSOC);
        $consultaVentas->close();
    }
} catch (mysqli_sql_exception $error) {
    error_log('Error al cargar el reporte de ventas: ' . $error->getMessage());
    $errorReporte = 'No se pudo cargar el reporte de ventas. Intentá nuevamente más tarde.';
} finally {
    if ($conexion instanceof mysqli) {
        $conexion->close();
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$parametrosFiltro = array_filter([
    'fecha_minima' => $fechaMinima,
    'aerolinea' => $codAerolinea > 0 ? $codAerolinea : '',
    'estado' => $estado,
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
    <title>Reporte de ventas | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../plantilla/encabezado.php'; ?>

    <main class="contenido-principal reporte-ventas" aria-labelledby="titulo-reporte-ventas">
        <div class="reporte-ventas-encabezado">
            <div>
                <p class="reporte-ventas-etiqueta">Administración</p>
                <h1 id="titulo-reporte-ventas">Reporte de ventas</h1>
            </div>
            <a class="reporte-ventas-boton reporte-ventas-imprimir" href="<?= $escapar($urlImpresion) ?>" target="_blank" rel="noopener">
                Imprimir resultados
            </a>
        </div>

        <form class="reporte-ventas-filtros" method="get" action="rep_ventas.php">
            <div class="reporte-ventas-campo">
                <label for="fecha-minima">Fecha mínima de reserva</label>
                <input id="fecha-minima" name="fecha_minima" type="date" value="<?= $escapar($fechaMinima) ?>">
            </div>
            <div class="reporte-ventas-campo">
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
            <div class="reporte-ventas-campo">
                <label for="filtro-estado">Estado</label>
                <select id="filtro-estado" name="estado">
                    <option value="">Todos</option>
                    <?php foreach ($estadosReserva as $estadoDisponible): ?>
                        <option value="<?= $escapar($estadoDisponible) ?>" <?= $estado === $estadoDisponible ? 'selected' : '' ?>>
                            <?= $escapar(ucfirst($estadoDisponible)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="reporte-ventas-boton reporte-ventas-aplicar" type="submit">Aplicar filtros</button>
        </form>

        <?php if ($errorFiltros !== ''): ?>
            <p class="reporte-ventas-mensaje error" role="alert"><?= $escapar($errorFiltros) ?></p>
        <?php endif; ?>
        <?php if ($errorReporte !== ''): ?>
            <p class="reporte-ventas-mensaje error" role="alert"><?= $escapar($errorReporte) ?></p>
        <?php endif; ?>

        <?php if ($imprimir && $errorReporte === '' && $errorFiltros === ''): ?>
            <p class="reporte-ventas-resumen-impresion">
                Filtros:
                <?= $fechaMinima !== '' ? 'desde ' . $escapar(date('d/m/Y', strtotime($fechaMinima))) : 'todas las fechas' ?> ·
                <?= $nombreAerolineaSeleccionada !== '' ? $escapar($nombreAerolineaSeleccionada) : 'todas las aerolíneas' ?> ·
                <?= $estado !== '' ? $escapar(ucfirst($estado)) : 'todos los estados' ?>
            </p>
        <?php endif; ?>

        <?php if ($errorReporte === '' && $errorFiltros === ''): ?>
            <section class="reporte-ventas-listado" aria-label="Reservas que coinciden con los filtros" aria-live="polite">
                <?php if ($ventas === []): ?>
                    <p class="reporte-ventas-vacio">No hay ventas que coincidan con los filtros seleccionados.</p>
                <?php else: ?>
                    <?php foreach ($ventas as $venta): ?>
                        <article class="reporte-ventas-fila">
                            <div class="reporte-ventas-datos">
                                <p><span>Código reserva:</span> <?= (int) $venta['codReserva'] ?></p>
                                <p><span>Código usuario:</span> <?= (int) $venta['codUsuario'] ?> · <?= $escapar((string) $venta['nombreUsuario']) ?></p>
                                <p><span>Fecha reserva:</span> <?= $escapar(date('d/m/Y', strtotime((string) $venta['fechaReserva']))) ?></p>
                            </div>
                            <div class="reporte-ventas-datos">
                                <p><span>Aerolínea:</span> <?= $escapar((string) $venta['nombreAerolinea']) ?> (<?= (int) $venta['codAerolinea'] ?>)</p>
                                <p><span>Código vuelo:</span> <?= (int) $venta['codVuelo'] ?></p>
                                <p><span>Precio:</span> $ <?= number_format((float) $venta['precioVuelo'], 2, ',', '.') ?>
                                    <span class="reporte-ventas-estado">Estado: <?= $escapar((string) $venta['estadoReserva']) ?></span>
                                </p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!$imprimir && $errorReporte === '' && $errorFiltros === '' && $totalVentas > 0): ?>
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
    </main>

    <?php include __DIR__ . '/../../plantilla/pie_pagina.php'; ?>
    <?php if ($imprimir && $errorReporte === '' && $errorFiltros === ''): ?>
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    <?php endif; ?>
</body>
</html>