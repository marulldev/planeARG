<?php
function conectarBaseDeDatos(): mysqli
{
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

	$conexion = new mysqli('localhost', 'Mallorquin', 'Perica_10', 'u293872833_planearg');
	$conexion->set_charset('utf8mb4');

	return $conexion;
}