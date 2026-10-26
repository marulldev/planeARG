# PlaneARG

PlaneARG es el trabajo final de la materia **Entornos Gráficos**, realizado en la **Universidad Tecnológica Nacional (UTN), Facultad Regional Rosario**, durante el ciclo lectivo 2026.

## ¿Qué es PlaneARG?

PlaneARG es un sitio web para consultar vuelos y organizar reservas. En un mismo espacio, los pasajeros pueden explorar próximas salidas, comparar rutas, horarios, aerolíneas, asientos disponibles y precios, incluidas las promociones vigentes. También pueden crear una cuenta, reservar vuelos, gestionar sus reservas y consultar su historial de compras.

El sitio incluye una página de inicio con vuelos, promociones y novedades, además de secciones informativas de ayuda y presentación del proyecto. Las funciones disponibles cambian según el tipo de cuenta:

- **Pasajeros:** consultan vuelos, realizan reservas, gestionan su estado y revisan el historial de compras.
- **CEO de aerolínea:** administra los vuelos y las promociones de su aerolínea, y consulta reportes de ventas y ocupación.
- **Administración:** gestiona aerolíneas, solicitudes para ser CEO y novedades; revisa promociones y consulta reportes de ventas, vuelos y usuarios.

Las promociones deben ser aprobadas por administración para mostrarse como ofertas vigentes. El precio asociado a una reserva queda guardado para conservar el importe aplicado al momento de reservar.

## Tecnologías

- PHP 8.2 o superior.
- MySQL o MariaDB.
- HTML y CSS.
- Composer para instalar las dependencias de correo.

La hoja de estilos principal está en `publico/css/style.css`. Las dependencias PHP se declaran en `composer.json`; se utilizan PHPMailer y el SDK de Mail API de Hostinger para las funciones de correo.