<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Panel CEO | PlaneARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>
<body>
	<?php include __DIR__ . "/../../plantilla/encabezado.php"; ?>

	<main class="contenido-principal menu-admin">
		<h1>Menú CEO</h1>

		<nav class="acciones-admin" aria-label="Gestión de PlaneARG">
			<a class="accion-admin" href="/Paginas/ceo/vuelos/GestionarVuelos.php">
				<span class="icono-admin icono-confirmacion" aria-hidden="true">
					<img src="/publico/icon/tick.png" alt="">
				</span>
				Gestión de vuelos
			</a>
			<a class="accion-admin" href="/Paginas/ceo/promociones/GestionarPromociones.php">
				<span class="icono-admin icono-promocion" aria-hidden="true">
					<img src="/publico/icon/percent-symbol-svgrepo-com.svg" alt="">
				</span>
				Gestión de promociones
			</a>
		</nav>

		<section class="reportes-admin" aria-labelledby="titulo-reportes-ceo">
			<h2 id="titulo-reportes-ceo">Reportes</h2>
			<nav class="enlaces-reportes-admin" aria-label="Reportes CEO">
				<a href="/Paginas/ceo/reportes/ventas.php">
					<img class="icono-reporte" src="/publico/icon/document-text-svgrepo-com.svg" alt="" aria-hidden="true">
					Reporte de ventas
				</a>
				<a href="/Paginas/ceo/reportes/ocupacion.php">
					<img class="icono-reporte" src="/publico/icon/document-text-svgrepo-com.svg" alt="" aria-hidden="true">
					Reporte de ocupación de vuelos
				</a>
			</nav>
		</section>
	</main>

	<?php include __DIR__ . "/../../plantilla/pie_pagina.php"; ?>
</body>
</html>
