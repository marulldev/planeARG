<?php
$servidor = 'localhost';
$usuario = 'root';
$password = '';
$baseDatos = 'planearg_prueba';

$mensaje = '';
$error = '';
$resultadoConsulta = [];
$consultaActual = "SELECT * FROM usuarios;";
$metodoHttp = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodoHttp === 'POST') {
    $consultaActual = trim($_POST['consulta'] ?? '');

    if ($consultaActual === '') {
        $error = 'Debes escribir una consulta SQL para ejecutar.';
    } else {
        $conexion = new mysqli($servidor, $usuario, $password, $baseDatos);

        if ($conexion->connect_error) {
            $error = 'No se pudo conectar a la base de datos: ' . $conexion->connect_error;
        } else {
            $resultado = $conexion->query($consultaActual);

            if ($resultado === false) {
                $error = 'Error en la consulta: ' . $conexion->error;
            } else {
                if ($resultado instanceof mysqli_result) {
                    $resultadoConsulta = $resultado->fetch_all(MYSQLI_ASSOC);
                    $mensaje = 'Consulta ejecutada correctamente. Se encontraron ' . count($resultadoConsulta) . ' fila(s).';
                } else {
                    $mensaje = 'Consulta ejecutada correctamente. Filas afectadas: ' . $conexion->affected_rows;
                }
            }

            $conexion->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de conexión PHP</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1c2b3a;
        }

        .contenedor {
            max-width: 1000px;
            margin: 40px auto;
            padding: 24px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
            color: #123b66;
        }

        .mensaje {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: #eaf8ee;
            color: #1d6b43;
            border: 1px solid #b9e4c8;
        }

        .error {
            background: #fdecec;
            color: #a72828;
            border-color: #f0b8b8;
        }

        form {
            display: grid;
            gap: 16px;
        }

        textarea {
            width: 100%;
            min-height: 160px;
            padding: 14px;
            border: 1px solid #cdd9ea;
            border-radius: 10px;
            font-family: Consolas, monospace;
            font-size: 14px;
            resize: vertical;
        }

        .botones {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        button {
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
            background: #0d5aa8;
            color: white;
            cursor: pointer;
            font-weight: 600;
        }

        button.secundario {
            background: #6c7a89;
        }

        .tabla-contenedor {
            margin-top: 28px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th, td {
            padding: 12px 14px;
            border: 1px solid #dfe7f2;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #edf4ff;
        }

        .instrucciones {
            background: #f8fafc;
            border: 1px solid #dfe7f2;
            border-radius: 10px;
            padding: 16px;
            margin-top: 20px;
        }

        code {
            background: #eef2f7;
            padding: 2px 6px;
            border-radius: 6px;
            font-family: Consolas, monospace;
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1>Prueba de conexión a MySQL con PHP</h1>

        <?php if ($mensaje !== ''): ?>
            <div class="mensaje"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="mensaje error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="consulta"><strong>Consulta SQL:</strong></label>
            <textarea id="consulta" name="consulta" spellcheck="false"><?php echo htmlspecialchars($consultaActual); ?></textarea>

            <div class="botones">
                <button type="submit">Ejecutar consulta</button>
                <button type="button" class="secundario" onclick="document.getElementById('consulta').value='SELECT * FROM usuarios;'">Consulta de usuarios</button>
                <button type="button" class="secundario" onclick="document.getElementById('consulta').value='SELECT * FROM vuelos;'">Consulta de vuelos</button>
                <button type="button" class="secundario" onclick="document.getElementById('consulta').value='SHOW TABLES;'">Ver tablas</button>
            </div>
        </form>

        <?php if (!empty($resultadoConsulta)): ?>
            <div class="tabla-contenedor">
                <h2>Resultado</h2>
                <table>
                    <thead>
                        <tr>
                            <?php foreach (array_keys($resultadoConsulta[0]) as $columna): ?>
                                <th><?php echo htmlspecialchars($columna); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultadoConsulta as $fila): ?>
                            <tr>
                                <?php foreach ($fila as $valor): ?>
                                    <td><?php echo htmlspecialchars((string) $valor); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="instrucciones">
            <h3>Cómo probarlo localmente</h3>
            <ol>
                <li>Instalá XAMPP o similar y levantá <strong>Apache</strong> y <strong>MySQL</strong>.</li>
                <li>Entrá a <strong>phpMyAdmin</strong> y creá la base de datos <code>planearg_prueba</code>.</li>
                <li>Importá el archivo <code>schema_prueba.sql</code> que está en esta misma carpeta.</li>
                <li>Abri este archivo con tu navegador y probá consultas de ejemplo.</li>
            </ol>
        </div>
    </div>
</body>
</html>
