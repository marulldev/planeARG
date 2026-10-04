<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vuelos | PlaneARG</title>
    <link rel="stylesheet" href="../../publico/css/style.css">
</head>

<body>
    <?php include "../plantilla/encabezado.php"; ?>

    <main class="contenido-principal vuelos">
        <section class="vuelos-presentacion" aria-labelledby="titulo-vuelos">
            <p class="vuelos-etiqueta">Planeá tu próximo viaje</p>
            <h1 id="titulo-vuelos">Vuelos disponibles</h1>
            <p>Explorá algunas opciones y encontrá tu próximo destino.</p>
        </section>

        <section class="vuelos-grupo" aria-labelledby="vuelos-columna-titulo">
            <h2 id="vuelos-columna-titulo">Vuelos en columna</h2>
            <div class="vuelos-lista vuelos-lista-columna">
                <?php
                $nombre = 'Buenos Aires';
                $destino = 'Bariloche';
                $fechaInicio = '15 oct 2026';
                $fechaFin = '22 oct 2026';
                $precio = '$ 125.000';
                include "../plantilla/itemVuelo.php";
                ?>

                <?php
                $nombre = 'Córdoba';
                $destino = 'Mendoza';
                $fechaInicio = '3 nov 2026';
                $fechaFin = '10 nov 2026';
                $precio = '$ 89.500';
                include "../plantilla/itemVuelo.php";
                ?>

                <?php
                $nombre = 'Buenos Aires';
                $destino = 'Ushuaia';
                $fechaInicio = '18 dic 2026';
                $fechaFin = '28 dic 2026';
                $precio = '$ 210.000';
                include "../plantilla/itemVuelo.php";
                ?>
            </div>
        </section>

        <section class="vuelos-grupo" aria-labelledby="vuelos-fila-titulo">
            <h2 id="vuelos-fila-titulo">Vuelos en fila</h2>
            <div class="vuelos-lista vuelos-lista-fila">
                <?php
                $nombre = 'Rosario';
                $destino = 'Salta';
                $fechaInicio = '8 ene 2027';
                $fechaFin = '15 ene 2027';
                $precio = '$ 98.000';
                include "../plantilla/itemVuelo.php";
                ?>

                <?php
                $nombre = 'Buenos Aires';
                $destino = 'Iguazú';
                $fechaInicio = '12 feb 2027';
                $fechaFin = '19 feb 2027';
                $precio = '$ 143.000';
                include "../plantilla/itemVuelo.php";
                ?>

                <?php
                $nombre = 'Mendoza';
                $destino = 'Córdoba';
                $fechaInicio = '4 mar 2027';
                $fechaFin = '11 mar 2027';
                $precio = '$ 76.500';
                include "../plantilla/itemVuelo.php";
                ?>
            </div>
        </section>
    </main>

    <?php include "../plantilla/pie_pagina.php"; ?>
</body>

</html>