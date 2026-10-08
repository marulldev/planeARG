<?php
require_once __DIR__ . '/../../includes/conexion.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$_SESSION['token_novedades'] ??= bin2hex(random_bytes(32));
$tokenNovedades = $_SESSION['token_novedades'];
$idSolicitado = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$modoEdicion = $idSolicitado !== null && $idSolicitado !== false && $idSolicitado > 0;
$idNovedadInvalido = array_key_exists('id', $_GET) && !$modoEdicion;
$zonaHoraria = new DateTimeZone('America/Argentina/Buenos_Aires');
$fechaHoy = (new DateTimeImmutable('today', $zonaHoraria))->format('Y-m-d');
$fechaPublicacion = $fechaHoy;
$fechaExpiracion = '';
$textoNovedad = '';
$novedadEdicion = null;
$errorFormulario = '';
$conexion = null;

try {
	$conexion = conectarBaseDeDatos();
	if ($idNovedadInvalido) {
		$errorFormulario = 'El identificador de la novedad no es válido.';
	} elseif ($modoEdicion) {
		$consultaEdicion = $conexion->prepare(
			'SELECT textoNovedad, fechaPublicacionNovedad, fechaExpiracionNovedad FROM novedades WHERE codNovedad = ?'
		);
		$consultaEdicion->bind_param('i', $idSolicitado);
		$consultaEdicion->execute();
		$novedadEdicion = $consultaEdicion->get_result()->fetch_assoc() ?: null;
		$consultaEdicion->close();

		if ($novedadEdicion === null) {
			$errorFormulario = 'No se encontró la novedad que querés editar.';
		} else {
			$textoNovedad = $novedadEdicion['textoNovedad'];
			$fechaPublicacion = $novedadEdicion['fechaPublicacionNovedad'];
			$fechaExpiracion = $novedadEdicion['fechaExpiracionNovedad'] ?? '';
		}
	}
} catch (Throwable $error) {
	$errorFormulario = 'No se pudo conectar con la base planearg. Verificá MySQL y la importación del esquema.';
}

$longitudTexto = static function (string $texto): int {
	return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $conexion instanceof mysqli) {
	if (!hash_equals($tokenNovedades, (string) ($_POST['tokenNovedades'] ?? ''))) {
		$errorFormulario = 'La sesión del formulario venció. Recargá la página e intentá nuevamente.';
	} elseif ($idNovedadInvalido || ($modoEdicion && $novedadEdicion === null)) {
		$errorFormulario = 'No se encontró la novedad que querés editar.';
	} else {
		$textoNovedad = trim($_POST['textoNovedad'] ?? '');
		$fechaExpiracion = trim($_POST['fechaExpiracionNovedad'] ?? '');
	}

	if ($errorFormulario === '' && ($textoNovedad === '' || $longitudTexto($textoNovedad) > 200)) {
		$errorFormulario = 'El texto es obligatorio y debe tener como máximo 200 caracteres.';
	} elseif ($errorFormulario === '' && $fechaExpiracion !== '') {
		$fechaParseada = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaExpiracion, $zonaHoraria);
		$erroresFecha = DateTimeImmutable::getLastErrors();
		$fechaInvalida = $fechaParseada === false
			|| ($erroresFecha !== false && ($erroresFecha['warning_count'] > 0 || $erroresFecha['error_count'] > 0))
			|| $fechaParseada->format('Y-m-d') !== $fechaExpiracion;

		if ($fechaInvalida) {
			$errorFormulario = 'Ingresá una fecha de expiración válida.';
		} elseif ($fechaExpiracion < $fechaPublicacion) {
			$errorFormulario = 'La fecha de expiración no puede ser anterior a la publicación.';
		}
	}

	if ($errorFormulario === '') {
		$fechaExpiracionParaGuardar = $fechaExpiracion !== '' ? $fechaExpiracion : null;
		try {
			if ($modoEdicion) {
				$consulta = $conexion->prepare(
					'UPDATE novedades SET textoNovedad = ?, fechaExpiracionNovedad = ? WHERE codNovedad = ?'
				);
				$consulta->bind_param('ssi', $textoNovedad, $fechaExpiracionParaGuardar, $idSolicitado);
			} else {
				$consulta = $conexion->prepare(
					'INSERT INTO novedades (textoNovedad, fechaPublicacionNovedad, fechaExpiracionNovedad) VALUES (?, ?, ?)'
				);
				$consulta->bind_param('sss', $textoNovedad, $fechaPublicacion, $fechaExpiracionParaGuardar);
			}
			$consulta->execute();
			$consulta->close();
			$conexion->close();

			header('Location: gestionar_novedades.php?' . ($modoEdicion ? 'actualizada=1' : 'creada=1'));
			exit;
		} catch (Throwable $error) {
			$errorFormulario = 'No se pudo guardar la novedad. Revisá los datos e intentá nuevamente.';
		}
	}
}

if ($conexion instanceof mysqli) {
	$conexion->close();
}

$escapar = static function ($valor): string {
	return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= $modoEdicion ? 'Editar novedad' : 'Crear novedad' ?> | PlaneARG</title>
	<link rel="stylesheet" href="../../../publico/css/style.css">
</head>

<body>
	<?php include __DIR__ . '/../../plantilla/encabezado.php'; ?>

	<main class="contenido-principal formulario-novedad-pagina">
		<div class="encabezado-formulario-aerolinea">
			<p class="etiqueta-gestion-aerolineas">Administración</p>
			<h1><?= $modoEdicion ? 'Editar novedad' : 'Crear novedad' ?></h1>
			<p>Completá el texto y definí, si hace falta, cuándo deja de estar vigente.</p>
		</div>

		<?php if ($errorFormulario !== ''): ?>
			<p class="mensaje-formulario-aerolinea error-formulario-aerolinea" role="alert"><?= $escapar($errorFormulario) ?></p>
		<?php endif; ?>

		<form class="formulario-aerolinea formulario-novedad" method="post" action="crear_novedad.php<?= $modoEdicion ? '?id=' . (int) $idSolicitado : '' ?>" accept-charset="UTF-8">
			<input type="hidden" name="tokenNovedades" value="<?= $escapar($tokenNovedades) ?>">
			<div class="campo-aerolinea">
				<label for="textoNovedad">Texto de la novedad <span>(máximo 200 caracteres)</span></label>
				<textarea id="textoNovedad" name="textoNovedad" maxlength="200" rows="6" placeholder="Escribí el texto de la novedad." required><?= $escapar($textoNovedad) ?></textarea>
				<p class="contador-descripcion-aerolinea"><span id="cantidadTextoNovedad"><?= $longitudTexto($textoNovedad) ?></span>/200</p>
			</div>

			<div class="campo-aerolinea">
				<label for="fechaPublicacionNovedad">Fecha de publicación</label>
				<p class="fecha-publicacion-automatica">
					Se asigna automáticamente: <time datetime="<?= $escapar($fechaPublicacion) ?>"><?= (new DateTimeImmutable($fechaPublicacion, $zonaHoraria))->format('d/m/Y') ?></time>
				</p>
			</div>

			<div class="campo-aerolinea campo-fecha-expiracion-novedad">
				<label for="fechaExpiracionNovedad">Fecha de expiración <span>(opcional)</span></label>
				<input id="fechaExpiracionNovedad" name="fechaExpiracionNovedad" type="date" min="<?= $escapar($fechaPublicacion) ?>" value="<?= $escapar($fechaExpiracion) ?>">
				<p class="ayuda-fecha-novedad">Si no elegís una fecha, la novedad no tendrá vencimiento.</p>
			</div>

			<div class="acciones-formulario-aerolinea">
				<a class="cancelar-aerolinea" href="gestionar_novedades.php">Cancelar</a>
				<button class="boton-guardar-aerolinea" type="submit" <?= $conexion instanceof mysqli && (!$modoEdicion || $novedadEdicion !== null) && !$idNovedadInvalido ? '' : 'disabled' ?>><?= $modoEdicion ? 'Guardar cambios' : 'Confirmar' ?></button>
			</div>
		</form>
	</main>

	<?php include __DIR__ . '/../../plantilla/pie_pagina.php'; ?>
	<script>
		const textoNovedad = document.querySelector('#textoNovedad');
		const cantidadTextoNovedad = document.querySelector('#cantidadTextoNovedad');

		textoNovedad.addEventListener('input', () => {
			cantidadTextoNovedad.textContent = Array.from(textoNovedad.value).length;
		});
	</script>
</body>

</html>
