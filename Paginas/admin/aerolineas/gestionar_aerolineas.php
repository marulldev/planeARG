<?php
require_once __DIR__ . '/../../includes/conexion.php';
$elementosPorPagina = 5;
$paginaSolicitada = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT);
$paginaListado = max(1, (int) $paginaSolicitada);
$cantidadAerolineas = 0;
$cantidadPaginas = 1;
$aerolineasPagina = [];
$errorBaseDatos = '';

	$conexion = null;
	try {
		$conexion = conectarBaseDeDatos();
		$resultadoCantidad = $conexion->query('SELECT COUNT(*) AS total FROM aerolineas');
		$cantidadAerolineas = (int) $resultadoCantidad->fetch_assoc()['total'];
		$cantidadPaginas = max(1, (int) ceil($cantidadAerolineas / $elementosPorPagina));
		$paginaListado = min($paginaListado, $cantidadPaginas);
		$desplazamiento = ($paginaListado - 1) * $elementosPorPagina;

		$consulta = $conexion->prepare(
			'SELECT codAerolinea, nombreAerolinea, codigoIATA, descripcionAerolinea, codPais FROM aerolineas ORDER BY codAerolinea DESC LIMIT ? OFFSET ?'
		);
		$consulta->bind_param('ii', $elementosPorPagina, $desplazamiento);
		$consulta->execute();
		$aerolineasPagina = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);
		$consulta->close();
	} catch (Throwable $error) {
		$errorBaseDatos = 'No se pudo conectar con la base planearg. Verificá MySQL y la importación de PRUEBA/basepruba2.sql.';
	} finally {
		if ($conexion instanceof mysqli) {
			$conexion->close();
		}
	}

$escapar = static function ($valor): string {
	return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Gestionar aerolíneas | PlaneARG</title>
	<link rel="stylesheet" href="../../../publico/css/style.css">
</head>

<body>
	<?php include __DIR__ . "/../../plantilla/encabezado.php"; ?>

	<main class="contenido-principal gestionar-aerolineas">
		<div class="encabezado-gestion-aerolineas">
			<div>
				<p class="etiqueta-gestion-aerolineas">Administración</p>
				<h1>Gestionar aerolíneas</h1>
			</div>
			<a class="boton-agregar-aerolinea" href="crear_aerolinea.php">
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d="M12 5v14M5 12h14"></path>
				</svg>
				<span>Agregar aerolínea</span>
			</a>
		</div>

		<?php if (isset($_GET['creada'])): ?>
			<p class="mensaje-formulario-aerolinea exito-formulario-aerolinea" role="status">La aerolínea se guardó correctamente.</p>
		<?php endif; ?>

		<?php if ($errorBaseDatos !== ''): ?>
			<p class="mensaje-formulario-aerolinea error-formulario-aerolinea" role="alert"><?= $escapar($errorBaseDatos) ?></p>
		<?php endif; ?>

		<section class="lista-aerolineas" aria-label="Listado de aerolíneas">
			<?php if ($errorBaseDatos !== ''): ?>
				<div class="estado-vacio-aerolineas">
					<h2>No se pudo cargar el listado</h2>
					<p>Revisá la conexión con la base de datos para ver las aerolíneas.</p>
				</div>
			<?php elseif ($aerolineasPagina !== []): ?>
				<?php foreach ($aerolineasPagina as $aerolinea): ?>
					<article class="fila-aerolinea">
						<div class="datos-aerolinea">
							<div class="identidad-aerolinea">
								<h2><?= $escapar($aerolinea['nombreAerolinea'] ?? '') ?></h2>
								<span class="codigo-iata-aerolinea" title="Código IATA">
									<?= $escapar($aerolinea['codigoIATA'] ?? '') ?>
								</span>
							</div>
							<p class="descripcion-aerolinea">
								<?= $escapar($aerolinea['descripcionAerolinea'] ?? '') ?>
							</p>
							<div class="metadatos-aerolinea">
								<span>Código: <?= $escapar($aerolinea['codAerolinea'] ?? '') ?></span>
								<span>País: <?= $escapar($aerolinea['codPais'] ?? '') ?></span>
							</div>
						</div>
						<div class="acciones-aerolinea">
							<button class="boton-icono boton-eliminar-aerolinea" type="button" aria-label="Eliminar <?= $escapar($aerolinea['nombreAerolinea'] ?? 'aerolínea') ?>" title="Acción disponible al conectar la base de datos" disabled>
								<img src="../../../publico/icon/trash-alt-svgrepo-com.svg" alt="" aria-hidden="true">
							</button>
							<button class="boton-icono boton-editar-aerolinea" type="button" aria-label="Editar <?= $escapar($aerolinea['nombreAerolinea'] ?? 'aerolínea') ?>" title="Acción disponible al conectar la base de datos" disabled>
								<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
									<path d="m4 16.5-.8 4.3 4.3-.8L20 7.5 16.5 4 4 16.5Z"></path>
									<path d="m14.8 5.7 3.5 3.5M3 21h18"></path>
								</svg>
							</button>
						</div>
					</article>
				<?php endforeach; ?>
			<?php else: ?>
				<div class="estado-vacio-aerolineas">
					<span class="icono-vacio-aerolineas" aria-hidden="true">
						<img src="../../../publico/icon/airplane-svgrepo-com.svg" alt="">
					</span>
					<h2>Todavía no hay aerolíneas</h2>
					<p>Cuando se creen las aerolíneas aparecerán en esta lista.</p>
				</div>
			<?php endif; ?>
		</section>

		<?php if ($errorBaseDatos === ''): ?>
		<nav class="paginacion-aerolineas" aria-label="Paginación de aerolíneas">
			<a class="control-paginacion-aerolineas <?= $paginaListado <= 1 ? 'deshabilitado' : '' ?>"
			   href="?pagina=<?= max(1, $paginaListado - 1) ?>"
			   <?= $paginaListado <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
				Anterior
			</a>
			<span class="estado-paginacion-aerolineas">
				Página <?= $paginaListado ?> de <?= $cantidadPaginas ?>
				<span><?= $cantidadAerolineas ?> <?= $cantidadAerolineas === 1 ? 'aerolínea' : 'aerolíneas' ?></span>
			</span>
			<a class="control-paginacion-aerolineas <?= $paginaListado >= $cantidadPaginas ? 'deshabilitado' : '' ?>"
			   href="?pagina=<?= min($cantidadPaginas, $paginaListado + 1) ?>"
			   <?= $paginaListado >= $cantidadPaginas ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
				Siguiente
			</a>
		</nav>
		<?php endif; ?>
	</main>

	<?php include __DIR__ . "/../../plantilla/pie_pagina.php"; ?>
</body>

</html>
