<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>planeARG</title>
    <link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>

<body>
    <?php include "../plantilla/encabezado.php";?>

    <main class="contenido-principal ayuda">
        <section class="ayuda-presentacion" aria-labelledby="titulo-ayuda">
            <h1 id="titulo-ayuda">Estamos para ayudarte a organizar tu viaje</h1>
            <p>Encontrá respuestas sobre la búsqueda de vuelos, tus reservas y la información que necesitás antes de viajar.</p>
        </section>

        <section class="ayuda-preguntas" aria-labelledby="titulo-preguntas">
            <div class="ayuda-encabezado-seccion">
                <h2 id="titulo-preguntas">Preguntas frecuentes</h2>
            </div>

            <div class="ayuda-lista-preguntas">
                <details>
                    <summary>¿Cómo puedo buscar un vuelo?</summary>
                    <p>Ingresá a la sección de búsqueda de vuelos y completá los datos del viaje, como el origen, el destino y las fechas. Revisá las opciones disponibles y sus condiciones antes de elegir.</p>
                </details>
                <details>
                    <summary>¿Cómo consulto los datos de mi reserva?</summary>
                    <p>Iniciá sesión con tu cuenta y entrá a la sección de gestión de reservas o al historial de compras para consultar los viajes asociados a tu usuario.</p>
                </details>
                <details>
                    <summary>¿Puedo cambiar o cancelar un vuelo desde PlaneARG?</summary>
                    <p>Las opciones de cambio y cancelación dependen de las condiciones de la tarifa y de la aerolínea. Revisá la información de tu reserva y contactá a la aerolínea para confirmar las alternativas disponibles.</p>
                </details>
                <details>
                    <summary>¿Dónde consulto los requisitos de equipaje y documentación?</summary>
                    <p>Los requisitos pueden variar según la aerolínea, el destino y la fecha del viaje. Confirmalos directamente con la aerolínea y con las autoridades correspondientes antes de viajar.</p>
                </details>
                <details>
                    <summary>¿Cómo me entero de promociones y novedades?</summary>
                    <p>Visitá la sección de novedades y promociones para consultar la información publicada y las condiciones de cada oferta.</p>
                </details>
            </div>
        </section>

        <aside class="ayuda-informacion" aria-labelledby="titulo-antes-de-viajar">
            <div>
                <p class="ayuda-etiqueta">Antes de viajar</p>
                <h2 id="titulo-antes-de-viajar">Confirmá siempre las condiciones vigentes</h2>
            </div>
            <p>Los horarios, servicios, tarifas y requisitos pueden cambiar. Para información definitiva sobre tu vuelo, verificá los datos con la aerolínea correspondiente.</p>
        </aside>
    </main>

    <?php include "../plantilla/pie_pagina.php"; ?>
</body>

</html>