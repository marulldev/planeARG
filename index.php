<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>planeARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>



<body>
    <?php
    $espacioEnBlanco = false;
    include __DIR__ . "/Paginas/plantilla/encabezado.php";
    ?>

    <main class="contenido-principal">
        <section class="portada-inicio" aria-labelledby="titulo-inicio">

            <video class="video-portada" autoplay muted loop playsinline aria-hidden="true">
                <source src="publico/Videos/gemini_generated_video_e538cfe8.mp4" type="video/mp4">
            </video>

            <div class="contenido-portada">
                <h1 id="titulo-inicio">Plane<span>ARG</span></h1>
            </div>

        </section>
    </main>

    <?php include __DIR__ . "/Paginas/plantilla/pie_pagina.php"; ?>
</body>

</html>