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

$tiposUsuario = ['administrador', 'ceo de aerolinea', 'usuario'];
$tipoRecibido = $_GET['tipo'] ?? '';
$tipoUsuario = is_string($tipoRecibido) ? $tipoRecibido : '';
$imprimirRecibido = $_GET['imprimir'] ?? '';
$imprimir = is_string($imprimirRecibido) && $imprimirRecibido === '1';
$paginaSolicitada = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
$pagina = max(1, $paginaSolicitada === false ? 1 : $paginaSolicitada);
$elementosPorPagina = 7;
$errorFiltros = '';
$errorReporte = '';
$usuarios = [];
$totalUsuarios = 0;
$totalPaginas = 1;

if ($tipoUsuario !== '' && !in_array($tipoUsuario, $tiposUsuario, true)) {
    $errorFiltros = 'Seleccioná un tipo de usuario válido.';
}

$conexion = null;
try {
    $conexion = conectarBaseDeDatos();

    if ($errorFiltros === '') {
        $consultaCantidad = $conexion->prepare(
            "SELECT COUNT(*) FROM usuarios WHERE (? = '' OR tipoUsuario = ?)"
        );
        $consultaCantidad->bind_param('ss', $tipoUsuario, $tipoUsuario);
        $consultaCantidad->execute();
        $consultaCantidad->bind_result($totalUsuarios);
        $consultaCantidad->fetch();
        $consultaCantidad->close();
        $totalUsuarios = (int) $totalUsuarios;

        $totalPaginas = max(1, (int) ceil($totalUsuarios / $elementosPorPagina));
        $pagina = min($pagina, $totalPaginas);
        $consultaUsuarios = $conexion->prepare(
            'SELECT codUsuario, nombreUsuario, emailUsuario, telefonoUsuario, tipoUsuario
             FROM usuarios
             WHERE (? = \'\' OR tipoUsuario = ?)
             ORDER BY codUsuario DESC'
             . ($imprimir ? '' : ' LIMIT ? OFFSET ?')
        );

        if ($imprimir) {
            $consultaUsuarios->bind_param('ss', $tipoUsuario, $tipoUsuario);
        } else {
            $desplazamiento = ($pagina - 1) * $elementosPorPagina;
            $consultaUsuarios->bind_param(
                'ssii',
                $tipoUsuario,
                $tipoUsuario,
                $elementosPorPagina,
                $desplazamiento
            );
        }
        $consultaUsuarios->execute();
        $usuarios = $consultaUsuarios->get_result()->fetch_all(MYSQLI_ASSOC);
        $consultaUsuarios->close();
    }
} catch (mysqli_sql_exception $error) {
    error_log('Error al cargar el reporte de usuarios: ' . $error->getMessage());
    $errorReporte = 'No se pudo cargar el reporte de usuarios. Intentá nuevamente más tarde.';
} finally {
    if ($conexion instanceof mysqli) {
        $conexion->close();
    }
}

$escapar = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$parametrosFiltro = $tipoUsuario !== '' ? ['tipo' => $tipoUsuario] : [];
$urlPagina = static function (int $numeroPagina) use ($parametrosFiltro): string {
    return '?' . http_build_query($parametrosFiltro + ['pagina' => $numeroPagina]);
};
$urlImpresion = '?' . http_build_query($parametrosFiltro + ['imprimir' => 1]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de usuarios | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
    <?php include __DIR__ . '/../../plantilla/encabezado.php'; ?>

    <main class="contenido-principal reporte-usuarios" aria-labelledby="titulo-reporte-usuarios">
        <div class="reporte-usuarios-encabezado">
            <div>
                <p class="reporte-usuarios-etiqueta">Administración</p>
                <h1 id="titulo-reporte-usuarios">Reporte de usuarios</h1>
            </div>
            <a class="reporte-usuarios-boton reporte-usuarios-imprimir" href="<?= $escapar($urlImpresion) ?>" target="_blank" rel="noopener">
                Imprimir resultados
            </a>
        </div>

        <form class="reporte-usuarios-filtros" method="get" action="rep_ususarios.php">
            <div class="reporte-usuarios-campo">
                <label for="filtro-tipo">Tipo de usuario</label>
                <select id="filtro-tipo" name="tipo">
                    <option value="">Todos</option>
                    <?php foreach ($tiposUsuario as $tipoDisponible): ?>
                        <option value="<?= $escapar($tipoDisponible) ?>" <?= $tipoUsuario === $tipoDisponible ? 'selected' : '' ?>>
                            <?= $escapar(ucfirst($tipoDisponible)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="reporte-usuarios-boton reporte-usuarios-aplicar" type="submit">Aplicar filtro</button>
        </form>

        <?php if ($errorFiltros !== ''): ?>
            <p class="reporte-usuarios-mensaje error" role="alert"><?= $escapar($errorFiltros) ?></p>
        <?php endif; ?>
        <?php if ($errorReporte !== ''): ?>
            <p class="reporte-usuarios-mensaje error" role="alert"><?= $escapar($errorReporte) ?></p>
        <?php endif; ?>

        <?php if ($imprimir && $errorReporte === '' && $errorFiltros === ''): ?>
            <p class="reporte-usuarios-resumen-impresion">
                Filtro: <?= $tipoUsuario !== '' ? $escapar(ucfirst($tipoUsuario)) : 'todos los tipos de usuario' ?>
            </p>
        <?php endif; ?>

        <?php if ($errorReporte === '' && $errorFiltros === ''): ?>
            <section class="reporte-usuarios-listado" aria-label="Usuarios que coinciden con el filtro" aria-live="polite">
                <?php if ($usuarios === []): ?>
                    <p class="reporte-usuarios-vacio">No hay usuarios que coincidan con el filtro seleccionado.</p>
                <?php else: ?>
                    <?php foreach ($usuarios as $usuario): ?>
                        <article class="reporte-usuarios-fila">
                            <div class="reporte-usuarios-datos">
                                <p><span>Código usuario:</span> <?= (int) $usuario['codUsuario'] ?></p>
                                <p><span>Nombre usuario:</span> <?= $escapar((string) $usuario['nombreUsuario']) ?></p>
                            </div>
                            <div class="reporte-usuarios-datos">
                                <p><span>Email de usuario:</span> <?= $escapar((string) $usuario['emailUsuario']) ?></p>
                                <p><span>Teléfono del usuario:</span> <?= $escapar((string) ($usuario['telefonoUsuario'] ?? 'No informado')) ?></p>
                            </div>
                            <div class="reporte-usuarios-datos">
                                <p><span>Tipo de usuario:</span> <?= $escapar((string) $usuario['tipoUsuario']) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!$imprimir && $errorReporte === '' && $errorFiltros === '' && $totalUsuarios > 0): ?>
            <nav class="reporte-usuarios-paginacion" aria-label="Páginas del reporte">
                <span>Mostrando <?= ($pagina - 1) * $elementosPorPagina + 1 ?>–<?= min($pagina * $elementosPorPagina, $totalUsuarios) ?> de <?= $totalUsuarios ?></span>
                <div>
                    <?php if ($pagina > 1): ?>
                        <a class="reporte-usuarios-boton" href="<?= $escapar($urlPagina($pagina - 1)) ?>" aria-label="Página anterior">Anterior</a>
                    <?php else: ?>
                        <span class="reporte-usuarios-boton deshabilitado" aria-disabled="true">Anterior</span>
                    <?php endif; ?>
                    <span class="reporte-usuarios-pagina">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                    <?php if ($pagina < $totalPaginas): ?>
                        <a class="reporte-usuarios-boton" href="<?= $escapar($urlPagina($pagina + 1)) ?>" aria-label="Página siguiente">Siguiente</a>
                    <?php else: ?>
                        <span class="reporte-usuarios-boton deshabilitado" aria-disabled="true">Siguiente</span>
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