<?php
function conectarBaseDeDatos(): mysqli
{
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

	$conexion = new mysqli('localhost', 'root', '', 'planearg');
	$conexion->set_charset('utf8mb4');

	return $conexion;
}