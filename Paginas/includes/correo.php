<?php
function obtenerConfiguracionCorreo(): array
{
    $rutaConfiguracion = __DIR__ . '/correo_config.local.php';
    if (!is_file($rutaConfiguracion)) {
        throw new RuntimeException(
            'Falta Paginas/includes/correo_config.local.php. Copiá el archivo de ejemplo y completá la configuración Mail API.'
        );
    }

    $configuracion = require $rutaConfiguracion;
    if (!is_array($configuracion)) {
        throw new RuntimeException('La configuración Mail API debe devolver un arreglo.');
    }

    foreach (['api_access_token', 'mailbox_email', 'from_name', 'base_url'] as $clave) {
        if (!isset($configuracion[$clave]) || $configuracion[$clave] === '') {
            throw new RuntimeException('Falta el valor de configuración Mail API: ' . $clave);
        }
    }

    if (!filter_var($configuracion['mailbox_email'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('La dirección de la mailbox configurada no es válida.');
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
    $urlSegura = htmlspecialchars($urlRestablecimiento, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    enviarCorreoMailApi(
        $destinatario,
        'Recuperar contraseña de PlaneARG',
        '<p>Recibimos una solicitud para cambiar la contraseña de tu cuenta PlaneARG.</p>'
            . '<p><a href="' . $urlSegura . '">Crear una contraseña nueva</a></p>'
            . '<p>El enlace vence en 30 minutos. Si no pediste este cambio, podés ignorar este mensaje.</p>',
        "Recibimos una solicitud para cambiar la contraseña de tu cuenta PlaneARG.\n\n"
            . "Abrí este enlace para crear una contraseña nueva (vence en 30 minutos):\n"
            . $urlRestablecimiento
            . "\n\nSi no pediste este cambio, podés ignorar este mensaje."
    );
}

function enviarCorreoAyuda(string $nombre, string $asunto, string $descripcion): void
{
    $configuracion = obtenerConfiguracionCorreo();
    $nombreSeguro = htmlspecialchars($nombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $asuntoSeguro = htmlspecialchars($asunto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $descripcionSegura = nl2br(
        htmlspecialchars($descripcion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    );

    enviarCorreoMailApi(
        (string) $configuracion['mailbox_email'],
        'Consulta de ayuda: ' . $asunto,
        '<p><strong>Nombre:</strong> ' . $nombreSeguro . '</p>'
            . '<p><strong>Asunto:</strong> ' . $asuntoSeguro . '</p>'
            . '<p><strong>Descripción:</strong><br>' . $descripcionSegura . '</p>',
        "Nombre: {$nombre}\nAsunto: {$asunto}\n\nDescripción:\n{$descripcion}"
    );
}

function enviarCorreoMailApi(string $destinatario, string $asunto, string $html, string $texto): void
{
    $rutaAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!is_file($rutaAutoload)) {
        throw new RuntimeException('No se encontró el SDK de Hostinger. Ejecutá composer install en el proyecto.');
    }

    require_once $rutaAutoload;
    if (!class_exists(\Hostinger\Api\SendApi::class)) {
        throw new RuntimeException('El SDK Mail API de Hostinger no está disponible después de cargar Composer.');
    }

    $configuracion = obtenerConfiguracionCorreo();

    try {
        $apiConfiguracion = \Hostinger\Configuration::getDefaultConfiguration()
            ->setAccessToken((string) $configuracion['api_access_token']);
        $cuentaApi = new \Hostinger\Api\AccountApi(config: $apiConfiguracion);
        $cuenta = $cuentaApi->getCurrentAccount();
        $mailboxId = '';

        foreach ($cuenta->getData()->getMailboxes() as $mailbox) {
            if (strcasecmp($mailbox->getAddress(), (string) $configuracion['mailbox_email']) === 0) {
                $mailboxId = $mailbox->getResourceId();
                break;
            }
        }

        if ($mailboxId === '') {
            throw new RuntimeException('La mailbox configurada no está disponible para el token Mail API.');
        }

        $solicitud = (new \Hostinger\Model\V1SendRequest())
            ->setTo([$destinatario])
            ->setDisplayName((string) $configuracion['from_name'])
            ->setSubject($asunto)
            ->setHtml($html)
            ->setText($texto);

        $apiEnvio = new \Hostinger\Api\SendApi(config: $apiConfiguracion);
        $apiEnvio->sendEmail($mailboxId, $solicitud);
    } catch (RuntimeException $error) {
        throw $error;
    } catch (\Throwable $error) {
        error_log('Error en Hostinger Mail API: ' . get_class($error) . ': ' . $error->getMessage());
        throw new RuntimeException('No se pudo enviar el correo mediante Hostinger Mail API.', 0, $error);
    }
}
