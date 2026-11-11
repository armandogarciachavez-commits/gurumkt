<?php
/**
 * Guru Marketing — Crea una sesión de Stripe Checkout.
 *
 * Atiende los dos tipos de cobro:
 *   - Suscripción recurrente (Guru Conecta): {tipo:"suscripcion", plan, periodo}
 *   - Pago único (invitaciones, paquetes iniciales): {tipo:"unico", producto}
 *
 * El navegador envía SOLO claves de catálogo. El monto se resuelve en el
 * servidor contra catalogo.php, así que no es manipulable desde el navegador.
 *
 * Responde {ok:true, url:"..."} y el frontend redirige. Stripe aloja la página
 * de pago: ningún dato de tarjeta pasa por este servidor.
 */

require_once __DIR__ . '/stripe-lib.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stripe_json(405, ['ok' => false, 'error' => 'Método no permitido']);
}

$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    stripe_json(400, ['ok' => false, 'error' => 'Solicitud inválida']);
}

$tipo    = isset($entrada['tipo'])    ? trim((string) $entrada['tipo'])    : '';
$email   = isset($entrada['email'])   ? trim((string) $entrada['email'])   : '';
$negocio = isset($entrada['negocio']) ? trim((string) $entrada['negocio']) : '';

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    stripe_json(400, ['ok' => false, 'error' => 'El correo electrónico no es válido.']);
}

$catalogo = require __DIR__ . '/catalogo.php';
$base     = 'https://gurumkt.com.mx';

// ---------------------------------------------------------------------------
// Construcción del line item según el tipo de cobro
// ---------------------------------------------------------------------------

if ($tipo === 'suscripcion') {

    $planKey    = isset($entrada['plan'])    ? trim((string) $entrada['plan'])    : '';
    $periodoKey = isset($entrada['periodo']) ? trim((string) $entrada['periodo']) : '';

    if (!isset($catalogo['suscripciones'][$planKey])) {
        stripe_log('warn', 'Plan inexistente solicitado', ['plan' => $planKey]);
        stripe_json(400, ['ok' => false, 'error' => 'El plan seleccionado no existe.']);
    }
    $plan = $catalogo['suscripciones'][$planKey];

    if (!isset($plan['periodos'][$periodoKey])) {
        stripe_log('warn', 'Periodo inexistente solicitado', ['plan' => $planKey, 'periodo' => $periodoKey]);
        stripe_json(400, ['ok' => false, 'error' => 'La forma de pago seleccionada no existe.']);
    }
    $periodo = $plan['periodos'][$periodoKey];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        stripe_json(400, ['ok' => false, 'error' => 'Necesitamos un correo electrónico válido.']);
    }

    // Stripe acepta intervalos de hasta 3 años. Semestral = 6 meses.
    $intervalo = ($periodo['frequency_type'] === 'months' && (int) $periodo['frequency'] === 12)
        ? ['interval' => 'year',  'interval_count' => 1]
        : ['interval' => 'month', 'interval_count' => (int) $periodo['frequency']];

    $referencia = sprintf('%s|%s|%s', $planKey, $periodoKey, date('YmdHis'));

    $params = [
        'mode'                => 'subscription',
        'locale'              => 'es',
        'success_url'         => $base . '/suscripcion-confirmada.html?sesion={CHECKOUT_SESSION_ID}',
        'cancel_url'          => $base . '/suscribirse.php?plan=' . rawurlencode($planKey) . '&periodo=' . rawurlencode($periodoKey),
        'customer_email'      => $email,
        'client_reference_id' => $referencia,
        'line_items'          => [[
            'quantity'   => 1,
            'price_data' => [
                'currency'     => 'mxn',
                'unit_amount'  => stripe_centavos($periodo['monto']),
                'recurring'    => $intervalo,
                'product_data' => [
                    'name'        => 'Guru Conecta · ' . $plan['nombre'],
                    'description' => $plan['descripcion'],
                ],
            ],
        ]],
        'metadata' => [
            'plan'    => $planKey,
            'periodo' => $periodoKey,
            'negocio' => mb_substr($negocio, 0, 200),
        ],
    ];

    $descripcionLog = ['plan' => $planKey, 'periodo' => $periodoKey, 'monto' => $periodo['monto']];

} elseif ($tipo === 'unico') {

    $productoKey = isset($entrada['producto']) ? trim((string) $entrada['producto']) : '';

    if (!isset($catalogo['unicos'][$productoKey])) {
        stripe_log('warn', 'Producto inexistente solicitado', ['producto' => $productoKey]);
        stripe_json(400, ['ok' => false, 'error' => 'El producto seleccionado no existe.']);
    }
    $producto = $catalogo['unicos'][$productoKey];

    $params = [
        'mode'                => 'payment',
        'locale'              => 'es',
        'success_url'         => $base . '/confirmacion-compra.html?sesion={CHECKOUT_SESSION_ID}',
        'cancel_url'          => $base . '/',
        'client_reference_id' => $productoKey . '|' . date('YmdHis'),
        'line_items'          => [[
            'quantity'   => 1,
            'price_data' => [
                'currency'     => 'mxn',
                'unit_amount'  => stripe_centavos($producto['monto']),
                'product_data' => [
                    'name'        => $producto['nombre'],
                    'description' => $producto['descripcion'],
                ],
            ],
        ]],
        'metadata' => ['producto' => $productoKey],
    ];

    if ($email !== '') {
        $params['customer_email'] = $email;
    }

    $descripcionLog = ['producto' => $productoKey, 'monto' => $producto['monto']];

} else {
    stripe_json(400, ['ok' => false, 'error' => 'Tipo de cobro no reconocido.']);
}

// ---------------------------------------------------------------------------
// Llamada a Stripe
// ---------------------------------------------------------------------------

// Clave de idempotencia: si el cliente da doble clic, no se crean dos sesiones.
$idem = 'guru-' . hash('sha256', json_encode($descripcionLog) . '|' . $email . '|' . floor(time() / 60));

$respuesta = stripe_request('POST', '/checkout/sessions', $params, $idem);

if ($respuesta['status'] === 0) {
    stripe_json(502, ['ok' => false, 'error' => 'No pudimos conectar con Stripe. Intenta de nuevo.']);
}

if ($respuesta['status'] < 200 || $respuesta['status'] >= 300) {
    // El detalle real queda en el log; al cliente no se le exponen datos internos.
    stripe_log('error', 'Stripe rechazó la creación de la sesión', [
        'http'    => $respuesta['status'],
        'detalle' => $respuesta['body']['error'] ?? $respuesta['body'],
    ] + $descripcionLog);
    stripe_json(502, ['ok' => false, 'error' => 'No pudimos iniciar el pago. Escríbenos por WhatsApp y lo resolvemos.']);
}

$cuerpo = $respuesta['body'] ?? [];
$url    = $cuerpo['url'] ?? null;

if (!$url) {
    stripe_log('error', 'Stripe no devolvió la URL de la sesión', ['respuesta' => $cuerpo]);
    stripe_json(502, ['ok' => false, 'error' => 'No pudimos iniciar el pago. Escríbenos por WhatsApp y lo resolvemos.']);
}

stripe_log('info', 'Sesión de pago creada', [
    'sesion' => $cuerpo['id'] ?? null,
    'tipo'   => $tipo,
    'prueba' => stripe_es_prueba(),
] + $descripcionLog);

stripe_json(200, ['ok' => true, 'url' => $url]);
