<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Panel CEO | PlaneARG</title>
	<link rel="stylesheet" href="../../publico/css/style.css">
</head>
<body>
	<?php include "../plantilla/encabezado.php"; ?>

	<main class="contenido-principal menu-admin">
		<h1>Menú CEO</h1>

		<nav class="acciones-admin" aria-label="Gestión de PlaneARG">
			<a class="accion-admin" href="/planeARG/Paginas/ceo/vuelos/GestionarVuelos.php">
				Gestión de vuelos
			</a>
			<a class="accion-admin" href="/planeARG/Paginas/ceo/promociones/GestionarPromociones.php">
				Gestión de promociones
			</a>
		</nav>

		<section class="reportes-admin" aria-labelledby="titulo-reportes-ceo">
			<h2 id="titulo-reportes-ceo">Reportes</h2>
			<nav class="enlaces-reportes-admin" aria-label="Reportes CEO">
				<a href="/planeARG/Paginas/ceo/reportes/ventas.php">Reporte de ventas</a>
				<a href="/planeARG/Paginas/ceo/reportes/ocupacion.php">Reporte de ocupación de vuelos</a>
			</nav>
		</section>
	</main>

	<?php include "../plantilla/pie_pagina.php"; ?>
</body>
</html>
