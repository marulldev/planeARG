<?php
$vistaDemostracion = ($_GET['vista'] ?? '') === 'demo';
$aerolineas = $vistaDemostracion ? [
	['nombre' => 'Aero Sur'],
	['nombre' => 'Cóndor Líneas'],
	['nombre' => 'Patagonia Air'],
	['nombre' => 'Río Plata Airways'],
	['nombre' => 'Horizonte Aéreo'],
	['nombre' => 'Nube Austral'],
	['nombre' => 'Andes Express'],
	['nombre' => 'Costa Atlántica'],
] : [];
$elementosPorPagina = 5;
$paginaSolicitada = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT);
$paginaListado = max(1, (int) $paginaSolicitada);
$cantidadAerolineas = count($aerolineas);
$cantidadPaginas = max(1, (int) ceil($cantidadAerolineas / $elementosPorPagina));
$paginaListado = min($paginaListado, $cantidadPaginas);
$aerolineasPagina = array_slice($aerolineas, ($paginaListado - 1) * $elementosPorPagina, $elementosPorPagina);

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
			<button class="boton-agregar-aerolinea" type="button" disabled title="Disponible cuando se conecte la base de datos">
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d="M12 5v14M5 12h14"></path>
				</svg>
				<span>Agregar aerolínea</span>
			</button>
		</div>

		<?php if ($vistaDemostracion): ?>
			<p class="aviso-vista-aerolineas" role="status">
				Vista previa: estos datos son ficticios y no se guardan.
				<a href="gestionar_aerolineas.php">Volver al listado vacío</a>
			</p>
		<?php else: ?>
			<p class="enlace-vista-aerolineas">
				<a href="?vista=demo">Ver vista previa con datos de ejemplo</a>
			</p>
		<?php endif; ?>

		<section class="lista-aerolineas" aria-label="Listado de aerolíneas">
			<?php if ($aerolineasPagina !== []): ?>
				<?php foreach ($aerolineasPagina as $aerolinea): ?>
					<article class="fila-aerolinea">
						<h2><?= $escapar($aerolinea['nombre'] ?? '') ?></h2>
						<div class="acciones-aerolinea">
							<button class="boton-icono boton-eliminar-aerolinea" type="button" aria-label="Eliminar <?= $escapar($aerolinea['nombre'] ?? 'aerolínea') ?>" title="Acción disponible al conectar la base de datos" disabled>
								<img src="../../../publico/icon/trash-alt-svgrepo-com.svg" alt="" aria-hidden="true">
							</button>
							<button class="boton-icono boton-editar-aerolinea" type="button" aria-label="Editar <?= $escapar($aerolinea['nombre'] ?? 'aerolínea') ?>" title="Acción disponible al conectar la base de datos" disabled>
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

		<nav class="paginacion-aerolineas" aria-label="Paginación de aerolíneas">
			<a class="control-paginacion-aerolineas <?= $paginaListado <= 1 ? 'deshabilitado' : '' ?>"
			   href="?pagina=<?= max(1, $paginaListado - 1) ?><?= $vistaDemostracion ? '&amp;vista=demo' : '' ?>"
			   <?= $paginaListado <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
				Anterior
			</a>
			<span class="estado-paginacion-aerolineas">
				Página <?= $paginaListado ?> de <?= $cantidadPaginas ?>
				<span><?= $cantidadAerolineas ?> <?= $cantidadAerolineas === 1 ? 'aerolínea' : 'aerolíneas' ?></span>
			</span>
			<a class="control-paginacion-aerolineas <?= $paginaListado >= $cantidadPaginas ? 'deshabilitado' : '' ?>"
			   href="?pagina=<?= min($cantidadPaginas, $paginaListado + 1) ?><?= $vistaDemostracion ? '&amp;vista=demo' : '' ?>"
			   <?= $paginaListado >= $cantidadPaginas ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
				Siguiente
			</a>
		</nav>
	</main>

	<?php include __DIR__ . "/../../plantilla/pie_pagina.php"; ?>
</body>

</html>
