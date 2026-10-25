<?php
require_once __DIR__ . '/../../includes/conexion.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$_SESSION['token_aerolineas'] ??= bin2hex(random_bytes(32));
$tokenAerolineas = $_SESSION['token_aerolineas'];
$idAerolinea = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$modoEdicion = $idAerolinea !== null && $idAerolinea !== false && $idAerolinea > 0;
$nombreAerolinea = '';
$codigoIATA = '';
$codPais = '';
$descripcionAerolinea = '';
$aerolineaEdicion = null;
$errorFormulario = '';
$conexion = null;

try {
	$conexion = conectarBaseDeDatos();
	if ($modoEdicion) {
		$consultaEdicion = $conexion->prepare(
			'SELECT nombreAerolinea, codigoIATA, codPais, descripcionAerolinea FROM aerolineas WHERE codAerolinea = ?'
		);
		$consultaEdicion->bind_param('i', $idAerolinea);
		$consultaEdicion->execute();
		$aerolineaEdicion = $consultaEdicion->get_result()->fetch_assoc() ?: null;
		$consultaEdicion->close();

		if ($aerolineaEdicion === null) {
			$errorFormulario = 'No se encontró la aerolínea que querés editar.';
		} else {
			$nombreAerolinea = $aerolineaEdicion['nombreAerolinea'];
			$codigoIATA = $aerolineaEdicion['codigoIATA'];
			$codPais = $aerolineaEdicion['codPais'] ?? '';
			$descripcionAerolinea = $aerolineaEdicion['descripcionAerolinea'] ?? '';
		}
	}
} catch (Throwable $error) {
	$errorFormulario = 'No se pudo conectar con la base planearg. Verificá que MySQL esté iniciado y que hayas importado PRUEBA/basepruba2.sql.';
}

$longitudTexto = static function (string $texto): int {
	return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $conexion instanceof mysqli) {
	if (!hash_equals($tokenAerolineas, (string) ($_POST['tokenAerolineas'] ?? ''))) {
		$errorFormulario = 'La sesión del formulario venció. Recargá la página e intentá nuevamente.';
	} elseif ($modoEdicion && $aerolineaEdicion === null) {
		$errorFormulario = 'No se encontró la aerolínea que querés editar.';
	} else {
		$nombreAerolinea = trim($_POST['nombreAerolinea'] ?? '');
		$codigoIATA = strtoupper(trim($_POST['codigoIATA'] ?? ''));
		$codPais = strtoupper(trim($_POST['codPais'] ?? ''));
		$descripcionAerolinea = trim($_POST['descripcionAerolinea'] ?? '');
	}

	if ($errorFormulario === '' && ($nombreAerolinea === '' || $longitudTexto($nombreAerolinea) > 100)) {
		$errorFormulario = 'El nombre es obligatorio y debe tener como máximo 100 caracteres.';
	} elseif ($errorFormulario === '' && !preg_match('/^[A-Z0-9]{3}$/', $codigoIATA)) {
		$errorFormulario = 'El código IATA debe tener exactamente 3 letras o números.';
	} elseif ($errorFormulario === '' && !preg_match('/^[A-Z]{3}$/', $codPais)) {
		$errorFormulario = 'El código de país debe tener exactamente 3 letras.';
	} elseif ($errorFormulario === '' && $longitudTexto($descripcionAerolinea) > 200) {
		$errorFormulario = 'La descripción puede tener como máximo 200 caracteres.';
	} elseif ($errorFormulario === '') {
		$descripcionParaGuardar = $descripcionAerolinea !== '' ? $descripcionAerolinea : null;

		try {
			if ($modoEdicion) {
				$consulta = $conexion->prepare(
					'UPDATE aerolineas SET nombreAerolinea = ?, codigoIATA = ?, descripcionAerolinea = ?, codPais = ? WHERE codAerolinea = ?'
				);
				$consulta->bind_param('ssssi', $nombreAerolinea, $codigoIATA, $descripcionParaGuardar, $codPais, $idAerolinea);
			} else {
				$consulta = $conexion->prepare(
					'INSERT INTO aerolineas (nombreAerolinea, codigoIATA, descripcionAerolinea, codPais) VALUES (?, ?, ?, ?)'
				);
				$consulta->bind_param('ssss', $nombreAerolinea, $codigoIATA, $descripcionParaGuardar, $codPais);
			}
			$consulta->execute();
			$consulta->close();
			$conexion->close();

			header('Location: gestionar_aerolineas.php?' . ($modoEdicion ? 'actualizada=1' : 'creada=1'));
			exit;
		} catch (Throwable $error) {
			$errorFormulario = 'No se pudo guardar la aerolínea. Revisá los datos e intentá nuevamente.';
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
	<title>Agregar aerolínea | PlaneARG</title>
	<link rel="stylesheet" href="/publico/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/publico/css/style.css') ?>">
</head>

<body>
	<?php include __DIR__ . '/../../plantilla/encabezado.php'; ?>

	<main class="contenido-principal formulario-aerolinea-pagina">
		<div class="encabezado-formulario-aerolinea">
			<p class="etiqueta-gestion-aerolineas">Administración</p>
			<h1><?= $modoEdicion ? 'Editar aerolínea' : 'Agregar aerolínea' ?></h1>
			<p><?= $modoEdicion ? 'Actualizá los datos de la aerolínea.' : 'Completá los datos para registrar una nueva aerolínea.' ?></p>
		</div>

		<?php if ($errorFormulario !== ''): ?>
			<p class="mensaje-formulario-aerolinea error-formulario-aerolinea" role="alert"><?= $escapar($errorFormulario) ?></p>
		<?php endif; ?>

		<form class="formulario-aerolinea" method="post" action="crear_aerolinea.php<?= $modoEdicion ? '?id=' . (int) $idAerolinea : '' ?>" accept-charset="UTF-8">
			<input type="hidden" name="tokenAerolineas" value="<?= $escapar($tokenAerolineas) ?>">
			<div class="campo-aerolinea">
				<label for="nombreAerolinea">Nombre de la aerolínea</label>
				<input id="nombreAerolinea" name="nombreAerolinea" type="text" maxlength="100" autocomplete="organization" value="<?= $escapar($nombreAerolinea) ?>" placeholder="Ej.: Aero Sur" required>
			</div>

			<div class="campo-aerolinea campo-aerolinea-corto">
				<label for="codigoIATA">Código IATA</label>
				<input id="codigoIATA" name="codigoIATA" type="text" maxlength="3" minlength="3" pattern="[A-Za-z0-9]{3}" autocomplete="off" value="<?= $escapar($codigoIATA) ?>" placeholder="Ej.: ARG" required>
			</div>

			<div class="campo-aerolinea campo-aerolinea-pais">
				<label for="codPais">Código de país</label>
				<input id="codPais" name="codPais" type="text" maxlength="3" minlength="3" pattern="[A-Za-z]{3}" autocomplete="country" value="<?= $escapar($codPais) ?>" placeholder="Ej.: ARG" required>
			</div>

			<div class="campo-aerolinea campo-aerolinea-descripcion">
				<label for="descripcionAerolinea">Descripción <span>(opcional, máximo 200 caracteres)</span></label>
				<textarea id="descripcionAerolinea" name="descripcionAerolinea" maxlength="200" rows="5" placeholder="Contá brevemente sobre la aerolínea."><?= $escapar($descripcionAerolinea) ?></textarea>
				<p class="contador-descripcion-aerolinea"><span id="cantidadDescripcion"><?= $longitudTexto($descripcionAerolinea) ?></span>/200</p>
			</div>

			<div class="acciones-formulario-aerolinea">
				<a class="cancelar-aerolinea" href="gestionar_aerolineas.php">Cancelar</a>
				<button class="boton-guardar-aerolinea" type="submit" <?= $conexion instanceof mysqli && (!$modoEdicion || $aerolineaEdicion !== null) ? '' : 'disabled' ?>><?= $modoEdicion ? 'Guardar cambios' : 'Guardar aerolínea' ?></button>
			</div>
		</form>
	</main>

	<?php include __DIR__ . '/../../plantilla/pie_pagina.php'; ?>
	<script>
		const descripcion = document.querySelector('#descripcionAerolinea');
		const cantidadDescripcion = document.querySelector('#cantidadDescripcion');

		descripcion.addEventListener('input', () => {
			cantidadDescripcion.textContent = Array.from(descripcion.value).length;
		});
	</script>
</body>

</html>
