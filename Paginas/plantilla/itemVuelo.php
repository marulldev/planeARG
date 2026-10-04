<?php
$nombre = isset($nombre) ? $nombre : 'Origen';
$destino = isset($destino) ? $destino : 'Destino';
$fechaInicio = isset($fechaInicio) ? $fechaInicio : 'Fecha de inicio';
$fechaFin = isset($fechaFin) ? $fechaFin : 'Fecha de fin';
$precio = isset($precio) ? $precio : 'Consultar precio';

$escapar = static function ($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};
?>
<article class="item-vuelo" aria-label="Vuelo de <?= $escapar($nombre) ?> a <?= $escapar($destino) ?>">
    <div class="item-vuelo-icono" aria-hidden="true">
        <img src="../../publico/icon/airplane-svgrepo-com.svg" alt="">
    </div>
    <div class="item-vuelo-detalles">
        <h2 class="item-vuelo-ruta"><strong><?= $escapar($nombre) ?> <span aria-hidden="true">→</span> <?= $escapar($destino) ?></strong></h2>
        <p class="item-vuelo-fechas"><?= $escapar($fechaInicio) ?> <span aria-hidden="true">—</span> <strong><?= $escapar($fechaFin) ?></strong></p>
    </div>
    <p class="item-vuelo-precio"><?= $escapar($precio) ?></p>
</article>