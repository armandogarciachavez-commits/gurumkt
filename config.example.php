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

    // Stripe — pasarela ACTIVA (suscripciones y pagos únicos)
    // Credenciales en: https://dashboard.stripe.com/apikeys
    // Las de prueba empiezan con sk_test_, las de producción con sk_live_
    'stripe' => [
        'secret_key'      => 'sk_test_...',  // privada, NUNCA en el HTML
        'publishable_key' => 'pk_test_...',  // pública
        // Signing secret del endpoint de webhooks (empieza con whsec_).
        // Developers > Webhooks > tu endpoint > Signing secret.
        // Sin esto, stripe-webhook.php rechaza TODOS los eventos.
        'webhook_secret'  => 'whsec_...',
    ],

    // Mercado Pago — EN PAUSA hasta completar la verificación de la cuenta.
    // El sitio hoy cobra con Stripe; este bloque queda listo para reactivarlo.
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
