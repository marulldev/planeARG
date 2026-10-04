<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Menú administrador | PlaneARG</title>
	<link rel="stylesheet" href="../../publico/css/style.css">
</head>

<body>
	<?php include "../plantilla/encabezado.php"; ?>

	<main class="contenido-principal menu-admin">
		<h1>Menú administrador</h1>

		<nav class="acciones-admin" aria-label="Gestión administrativa">
			<a class="accion-admin" href="aerolineas/gestionar_aerolineas.php">
				<span class="icono-admin icono-avion" aria-hidden="true">
					<img src="../../publico/icon/airplane-svgrepo-com.svg" alt="">
				</span>
				<span>Gestionar aerolíneas</span>
			</a>
			<a class="accion-admin" href="promociones/aprobar/denegar_promociones.php">
				<span class="icono-admin icono-promocion" aria-hidden="true">
					<svg viewBox="0 0 48 48" focusable="false">
						<circle cx="15" cy="14" r="7"></circle>
						<circle cx="33" cy="34" r="7"></circle>
						<path d="M36 9 12 39"></path>
					</svg>
				</span>
				<span>Aprobar o denegar promociones</span>
			</a>
			<a class="accion-admin" href="novedades/gestionar_novedades.php">
				<span class="icono-admin icono-novedad" aria-hidden="true">
					<svg viewBox="0 0 48 48" focusable="false">
						<path d="M8 20v8h8l18 9V11l-18 9H8Z"></path>
						<path d="m17 29 4 11h8l-5-8"></path>
						<path d="M39 18c2 2 2 10 0 12M36 14c4 4 4 16 0 20"></path>
					</svg>
				</span>
				<span>Gestionar novedades</span>
			</a>
		</nav>

		<section class="reportes-admin" aria-labelledby="titulo-reportes-admin">
			<h2 id="titulo-reportes-admin">Reportes</h2>
			<nav class="enlaces-reportes-admin" aria-label="Reportes">
				<a href="reportes/crear_reporte.php">
					<svg class="icono-reporte" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
						<path d="M14 6h14l8 8v28H14a4 4 0 0 1-4-4V10a4 4 0 0 1 4-4Z"></path>
						<path d="M28 6v10h10M17 24h16M17 31h16M17 38h11"></path>
					</svg>
					<span>Generar reportes</span>
				</a>
				<a href="reportes/rep_ventas.php">
					<svg class="icono-reporte" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
						<path d="M14 6h14l8 8v28H14a4 4 0 0 1-4-4V10a4 4 0 0 1 4-4Z"></path>
						<path d="M28 6v10h10M17 24h16M17 31h16M17 38h11"></path>
					</svg>
					<span>Reporte de ventas</span>
				</a>
				<a href="reportes/rep_vuelos.php">
					<svg class="icono-reporte" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
						<path d="M14 6h14l8 8v28H14a4 4 0 0 1-4-4V10a4 4 0 0 1 4-4Z"></path>
						<path d="M28 6v10h10M17 24h16M17 31h16M17 38h11"></path>
					</svg>
					<span>Reporte de vuelos</span>
				</a>
				<a href="reportes/rep_ususarios.php">
					<svg class="icono-reporte" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
						<path d="M14 6h14l8 8v28H14a4 4 0 0 1-4-4V10a4 4 0 0 1 4-4Z"></path>
						<path d="M28 6v10h10M17 24h16M17 31h16M17 38h11"></path>
					</svg>
					<span>Reporte de usuarios</span>
				</a>
			</nav>
		</section>
	</main>

	<?php include "../plantilla/pie_pagina.php"; ?>
</body>

</html>
