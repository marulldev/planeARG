# planeARG

## Recuperación de contraseña

1. Ejecutá `PRUEBA/migraciones/20261025_recuperacion_contrasena.sql` una vez sobre la base de datos activa.
2. Ejecutá `composer install` en la raíz del proyecto para instalar PHPMailer.
3. Copiá `Paginas/includes/correo_config.example.php` como `Paginas/includes/correo_config.local.php` y completá `password` con la contraseña de la mailbox y `base_url` con la URL HTTPS pública del sitio. El archivo local está excluido de Git.
4. En Hostinger, verificá los datos SMTP de la mailbox. La configuración inicial usa `smtp.hostinger.com`, puerto `465` y SSL; si hPanel indica otros datos, actualizá `host`, `port` y `encryption`.

El enlace vence a los 30 minutos, el token se almacena como hash y solo se permite una solicitud por cuenta por minuto. El formulario responde con el mismo mensaje exista o no la cuenta.
