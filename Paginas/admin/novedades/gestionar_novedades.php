<?php
require_once __DIR__ . '/../../includes/conexion.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$_SESSION['token_novedades'] ??= bin2hex(random_bytes(32));
$tokenNovedades = $_SESSION['token_novedades'];
$errorAccion = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
	$tokenEnviado = (string) ($_POST['tokenNovedades'] ?? '');
	$idEliminar = filter_var($_POST['codNovedad'] ?? null, FILTER_VALIDATE_INT);

	if (!hash_equals($tokenNovedades, $tokenEnviado)) {
		$errorAccion = 'La sesión venció. Recargá la página e intentá nuevamente.';
	} elseif ($idEliminar === false || $idEliminar === null || $idEliminar < 1) {
		$errorAccion = 'No se pudo identificar la novedad que querés eliminar.';
	} else {
		$conexionEliminar = null;
		try {
			$conexionEliminar = conectarBaseDeDatos();
			$consultaEliminar = $conexionEliminar->prepare('DELETE FROM novedades WHERE codNovedad = ?');
			$consultaEliminar->bind_param('i', $idEliminar);
			$consultaEliminar->execute();
			$filasEliminadas = $consultaEliminar->affected_rows;
			$consultaEliminar->close();

			if ($filasEliminadas > 0) {
				$conexionEliminar->close();
				header('Location: gestionar_novedades.php?eliminada=1');
				exit;
			}
			$errorAccion = 'La novedad ya no existe o ya fue eliminada.';
		} catch (Throwable $error) {
			$errorAccion = 'No se pudo eliminar la novedad. Intentá nuevamente.';
		} finally {
			if ($conexionEliminar instanceof mysqli) {
				$conexionEliminar->close();
			}
		}
	}
}

$elementosPorPagina = 3;
$paginaSolicitada = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT);
$paginaListado = max(1, (int) $paginaSolicitada);
$cantidadNovedades = 0;
$cantidadPaginas = 1;
$novedades = [];
$errorBaseDatos = '';
$conexion = null;

try {
	$conexion = conectarBaseDeDatos();
	$resultadoCantidad = $conexion->query('SELECT COUNT(*) AS total FROM novedades');
	$cantidadNovedades = (int) $resultadoCantidad->fetch_assoc()['total'];
	$cantidadPaginas = max(1, (int) ceil($cantidadNovedades / $elementosPorPagina));
	$paginaListado = min($paginaListado, $cantidadPaginas);
	$desplazamiento = ($paginaListado - 1) * $elementosPorPagina;

	$consulta = $conexion->prepare(
		'SELECT codNovedad, textoNovedad, fechaPublicacionNovedad, fechaExpiracionNovedad FROM novedades ORDER BY codNovedad DESC LIMIT ? OFFSET ?'
	);
	$consulta->bind_param('ii', $elementosPorPagina, $desplazamiento);
	$consulta->execute();
	$novedades = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);
	$consulta->close();
} catch (Throwable $error) {
	$errorBaseDatos = 'No se pudo conectar con la base planearg. Verificá MySQL y la importación del esquema.';
} finally {
	if ($conexion instanceof mysqli) {
		$conexion->close();
	}
}

$escapar = static function ($valor): string {
	return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};

$formatearFecha = static function (?string $fecha): string {
	return $fecha ? (new DateTimeImmutable($fecha))->format('d/m/Y') : 'Sin vencimiento';
};
?>
<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Gestionar novedades | PlaneARG</title>
	<link rel="stylesheet" href="../../../publico/css/style.css">
</head>

<body>
	<?php include __DIR__ . '/../../plantilla/encabezado.php'; ?>

	<main class="contenido-principal gestionar-novedades">
		<div class="encabezado-gestion-novedades">
			<div>
				<p class="etiqueta-gestion-aerolineas">Administración</p>
				<h1>Gestionar novedades</h1>
			</div>
			<a class="boton-agregar-aerolinea" href="crear_novedad.php">
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d="M12 5v14M5 12h14"></path>
				</svg>
				<span>Crear novedad</span>
			</a>
		</div>

		<?php if (isset($_GET['creada'])): ?>
			<p class="mensaje-formulario-aerolinea exito-formulario-aerolinea" role="status">La novedad se creó correctamente.</p>
		<?php endif; ?>
		<?php if (isset($_GET['actualizada'])): ?>
			<p class="mensaje-formulario-aerolinea exito-formulario-aerolinea" role="status">La novedad se actualizó correctamente.</p>
		<?php endif; ?>
		<?php if (isset($_GET['eliminada'])): ?>
			<p class="mensaje-formulario-aerolinea exito-formulario-aerolinea" role="status">La novedad se eliminó correctamente.</p>
		<?php endif; ?>
		<?php if ($errorAccion !== ''): ?>
			<p class="mensaje-formulario-aerolinea error-formulario-aerolinea" role="alert"><?= $escapar($errorAccion) ?></p>
		<?php endif; ?>
		<?php if ($errorBaseDatos !== ''): ?>
			<p class="mensaje-formulario-aerolinea error-formulario-aerolinea" role="alert"><?= $escapar($errorBaseDatos) ?></p>
		<?php endif; ?>

		<section class="lista-novedades" aria-label="Listado de novedades">
			<?php if ($errorBaseDatos !== ''): ?>
				<div class="estado-vacio-aerolineas">
					<h2>No se pudo cargar el listado</h2>
					<p>Revisá la conexión con la base de datos para ver las novedades.</p>
				</div>
			<?php elseif ($novedades !== []): ?>
				<?php foreach ($novedades as $novedad): ?>
					<article class="fila-novedad">
						<div class="fechas-novedad">
							<p>Publicación <time datetime="<?= $escapar($novedad['fechaPublicacionNovedad']) ?>"><?= $escapar($formatearFecha($novedad['fechaPublicacionNovedad'])) ?></time></p>
							<p>Expiración <time datetime="<?= $escapar($novedad['fechaExpiracionNovedad'] ?? '') ?>"><?= $escapar($formatearFecha($novedad['fechaExpiracionNovedad'])) ?></time></p>
						</div>
						<div class="contenido-novedad">
							<p><?= nl2br($escapar($novedad['textoNovedad'])) ?></p>
							<span>Código #<?= (int) $novedad['codNovedad'] ?></span>
						</div>
						<div class="acciones-novedad">
							<form class="formulario-eliminar-aerolinea" method="post" onsubmit="return confirm('¿Seguro que querés eliminar esta novedad? Esta acción no se puede deshacer.');">
								<input type="hidden" name="accion" value="eliminar">
								<input type="hidden" name="codNovedad" value="<?= (int) $novedad['codNovedad'] ?>">
								<input type="hidden" name="tokenNovedades" value="<?= $escapar($tokenNovedades) ?>">
								<button class="boton-icono boton-eliminar-aerolinea" type="submit" aria-label="Eliminar novedad <?= (int) $novedad['codNovedad'] ?>" title="Eliminar novedad">
									<img src="../../../publico/icon/trash-alt-svgrepo-com.svg" alt="" aria-hidden="true">
								</button>
							</form>
							<a class="boton-icono boton-editar-aerolinea" href="crear_novedad.php?id=<?= (int) $novedad['codNovedad'] ?>" aria-label="Editar novedad <?= (int) $novedad['codNovedad'] ?>" title="Editar novedad">
								<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
									<path d="m4 16.5-.8 4.3 4.3-.8L20 7.5 16.5 4 4 16.5Z"></path>
									<path d="m14.8 5.7 3.5 3.5M3 21h18"></path>
								</svg>
							</a>
						</div>
					</article>
				<?php endforeach; ?>
			<?php else: ?>
				<div class="estado-vacio-aerolineas">
					<span class="icono-vacio-aerolineas" aria-hidden="true">
						<img src="../../../publico/icon/megaphone-solid-svgrepo-com.svg" alt="">
					</span>
					<h2>Todavía no hay novedades</h2>
					<p>Cuando crees una novedad aparecerá en esta lista.</p>
				</div>
			<?php endif; ?>
		</section>

		<?php if ($errorBaseDatos === ''): ?>
			<nav class="paginacion-aerolineas" aria-label="Paginación de novedades">
				<a class="control-paginacion-aerolineas <?= $paginaListado <= 1 ? 'deshabilitado' : '' ?>" href="?pagina=<?= max(1, $paginaListado - 1) ?>" <?= $paginaListado <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Anterior</a>
				<span class="estado-paginacion-aerolineas">Página <?= $paginaListado ?> de <?= $cantidadPaginas ?><span><?= $cantidadNovedades ?> <?= $cantidadNovedades === 1 ? 'novedad' : 'novedades' ?></span></span>
				<a class="control-paginacion-aerolineas <?= $paginaListado >= $cantidadPaginas ? 'deshabilitado' : '' ?>" href="?pagina=<?= min($cantidadPaginas, $paginaListado + 1) ?>" <?= $paginaListado >= $cantidadPaginas ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Siguiente</a>
			</nav>
		<?php endif; ?>
	</main>

	<?php include __DIR__ . '/../../plantilla/pie_pagina.php'; ?>
</body>

</html>
