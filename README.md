# planeARG

## Recuperación de contraseña

1. Ejecutá `PRUEBA/migraciones/20261025_cod_aerolinea_nullable.sql` una vez sobre la base de datos activa para permitir cuentas que no estén asociadas a una aerolínea.
2. Ejecutá `PRUEBA/migraciones/20261025_recuperacion_contrasena.sql` una vez sobre la base de datos activa.
3. Ejecutá `composer install` en la raíz del proyecto para instalar las dependencias.
4. En Hostinger, generá un token de Mail API con acceso a la mailbox.
5. Copiá `Paginas/includes/correo_config.example.php` como `Paginas/includes/correo_config.local.php` y completá `api_access_token` con el token privado y `base_url` con la URL HTTPS pública del sitio. El archivo local está excluido de Git.
6. Confirmá que `mailbox_email` coincide con una dirección autorizada para ese token. La aplicación consulta la API para obtener automáticamente el identificador de la mailbox.

El enlace vence a los 30 minutos, el token se almacena como hash y solo se permite una solicitud por cuenta por minuto. El formulario responde con el mismo mensaje exista o no la cuenta. El SDK requiere PHP 8.2 o superior.

## Imágenes de novedades y promociones

Ejecutá `PRUEBA/migraciones/20261025_imagenes_novedades_promociones.sql` una vez sobre la base de datos activa. Las imágenes son opcionales; se aceptan JPG, PNG y WebP de hasta 5 MB y se guardan en `publico/imagenes/contenido`. Si no se carga una imagen, se muestra `publico/imagenes/novedad/promo_generica.png`.
