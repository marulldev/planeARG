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
					<img src="/planeARG/publico/icon/airplane-svgrepo-com.svg" alt="">
				</span>
				<span>Gestionar aerolíneas</span>
			</a>
			<a class="accion-admin" href="promociones/aprobar/denegar_promociones.php">
				<span class="icono-admin icono-promocion" aria-hidden="true">
					<img src="/planeARG/publico/icon/percent-symbol-svgrepo-com.svg" alt="">
				</span>
				<span>Aprobar o denegar promociones</span>
			</a>
			<a class="accion-admin" href="novedades/gestionar_novedades.php">
				<span class="icono-admin icono-novedad" aria-hidden="true">
					<img src="/planeARG/publico/icon/megaphone-solid-svgrepo-com.svg" alt="">
				</span>
				<span>Gestionar novedades</span>
			</a>
		</nav>

		<section class="reportes-admin" aria-labelledby="titulo-reportes-admin">
			<h2 id="titulo-reportes-admin">Reportes</h2>
			<nav class="enlaces-reportes-admin" aria-label="Reportes">
				<a href="reportes/rep_ventas.php">
					<img class="icono-reporte" src="/planeARG/publico/icon/document-text-svgrepo-com.svg" alt="" aria-hidden="true">
					<span>Reporte de ventas</span>
				</a>
				<a href="reportes/rep_vuelos.php">
					<img class="icono-reporte" src="/planeARG/publico/icon/document-text-svgrepo-com.svg" alt="" aria-hidden="true">
					<span>Reporte de vuelos</span>
				</a>
				<a href="reportes/rep_ususarios.php">
					<img class="icono-reporte" src="/planeARG/publico/icon/document-text-svgrepo-com.svg" alt="" aria-hidden="true">
					<span>Reporte de usuarios</span>
				</a>
			</nav>
		</section>
	</main>

	<?php include "../plantilla/pie_pagina.php"; ?>
</body>

</html>
