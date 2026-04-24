<?php
/**
 * Plantilla de configuración.
 * Copia este archivo a `config.php` en el servidor y rellena con los valores reales.
 * `config.php` está en .gitignore y NO debe commitearse.
 */

return [
    // reCAPTCHA Enterprise
    'recaptcha' => [
        'site_key'    => 'TU_SITE_KEY_PUBLICA',      // la que va en el HTML
        'secret_key'  => 'TU_SECRET_KEY_PRIVADA',    // clave secreta heredada
        'action'      => 'contact',
        'min_score'   => 0.5,
    ],

    // Destino de correos del formulario de contacto
    'mail' => [
        'to'      => 'destino@gurumkt.com.mx',
        'from'    => 'no-reply@gurumkt.com.mx',
        'subject' => 'Nuevo Mensaje de Sitio Web Guru Marketing',
    ],
];
