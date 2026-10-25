<?php
function obtenerConfiguracionCorreo(): array
{
    $rutaConfiguracion = __DIR__ . '/correo_config.local.php';
    if (!is_file($rutaConfiguracion)) {
        throw new RuntimeException(
            'Falta Paginas/includes/correo_config.local.php. Copiá el archivo de ejemplo y completá la configuración SMTP.'
        );
    }

    $configuracion = require $rutaConfiguracion;
    if (!is_array($configuracion)) {
        throw new RuntimeException('La configuración SMTP debe devolver un arreglo.');
    }

    foreach (['host', 'username', 'password', 'port', 'encryption', 'from_email', 'from_name', 'base_url'] as $clave) {
        if (!isset($configuracion[$clave]) || $configuracion[$clave] === '') {
            throw new RuntimeException('Falta el valor de configuración SMTP: ' . $clave);
        }
    }

    if (
        !filter_var($configuracion['from_email'], FILTER_VALIDATE_EMAIL)
        || !filter_var($configuracion['username'], FILTER_VALIDATE_EMAIL)
        || filter_var($configuracion['port'], FILTER_VALIDATE_INT) === false
        || (int) $configuracion['port'] < 1
        || (int) $configuracion['port'] > 65535
        || !in_array($configuracion['encryption'], ['ssl', 'tls'], true)
    ) {
        throw new RuntimeException('La configuración de correo contiene valores inválidos.');
    }

    $baseUrl = rtrim((string) $configuracion['base_url'], '/');
    $partesUrl = parse_url($baseUrl);
    $hostLocal = is_array($partesUrl)
        && in_array($partesUrl['host'] ?? '', ['localhost', '127.0.0.1'], true);
    if (
        !filter_var($baseUrl, FILTER_VALIDATE_URL)
        || (!str_starts_with($baseUrl, 'https://') && !($hostLocal && str_starts_with($baseUrl, 'http://')))
    ) {
        throw new RuntimeException('La URL base debe usar HTTPS (excepto localhost en desarrollo).');
    }

    $configuracion['base_url'] = $baseUrl;
    return $configuracion;
}

function obtenerUrlBaseAplicacion(): string
{
    return (string) obtenerConfiguracionCorreo()['base_url'];
}

function enviarCorreoRecuperacion(string $destinatario, string $urlRestablecimiento): void
{
    $rutaAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!is_file($rutaAutoload)) {
        throw new RuntimeException('No se encontró PHPMailer. Ejecutá composer install en el proyecto.');
    }

    require_once $rutaAutoload;
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        throw new RuntimeException('PHPMailer no está disponible después de cargar Composer.');
    }

    $configuracion = obtenerConfiguracionCorreo();
    $correo = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $correo->isSMTP();
        $correo->Host = (string) $configuracion['host'];
        $correo->SMTPAuth = true;
        $correo->Username = (string) $configuracion['username'];
        $correo->Password = (string) $configuracion['password'];
        $correo->Port = (int) $configuracion['port'];
        $correo->SMTPSecure = $configuracion['encryption'] === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $correo->Timeout = 15;
        $correo->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $correo->setFrom((string) $configuracion['from_email'], (string) $configuracion['from_name']);
        $correo->addAddress($destinatario);
        $correo->isHTML(true);
        $correo->Subject = 'Recuperar contraseña de PlaneARG';
        $correo->Body = '<p>Recibimos una solicitud para cambiar la contraseña de tu cuenta PlaneARG.</p>'
            . '<p><a href="' . htmlspecialchars($urlRestablecimiento, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">Crear una contraseña nueva</a></p>'
            . '<p>El enlace vence en 30 minutos. Si no pediste este cambio, podés ignorar este mensaje.</p>';
        $correo->AltBody = "Recibimos una solicitud para cambiar la contraseña de tu cuenta PlaneARG.\n\n"
            . "Abrí este enlace para crear una contraseña nueva (vence en 30 minutos):\n"
            . $urlRestablecimiento
            . "\n\nSi no pediste este cambio, podés ignorar este mensaje.";
        $correo->send();
    } catch (\PHPMailer\PHPMailer\Exception $error) {
        error_log('Error SMTP al enviar recuperación: ' . $error->getMessage());
        throw new RuntimeException('No se pudo enviar el correo de recuperación por SMTP.', 0, $error);
    }
}
