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

    // Mercado Pago — suscripciones de Guru Conecta
    // Credenciales en: https://www.mercadopago.com.mx/developers/panel
    // Las de prueba empiezan con TEST-, las de producción con APP_USR-
    'mercadopago' => [
        'access_token'   => 'TU_ACCESS_TOKEN',   // privado, NUNCA en el HTML
        'public_key'     => 'TU_PUBLIC_KEY',     // público
        // Clave secreta del webhook: panel > Tus integraciones > Webhooks >
        // Configurar notificación > revelar clave. Si se deja vacía, la firma
        // no se valida (el webhook sigue siendo seguro porque reconsulta la API).
        'webhook_secret' => '',
    ],

    // Destino de correos del formulario de contacto
    'mail' => [
        'to'      => 'destino@gurumkt.com.mx',
        'from'    => 'no-reply@gurumkt.com.mx',
        'subject' => 'Nuevo Mensaje de Sitio Web Guru Marketing',
    ],
];
