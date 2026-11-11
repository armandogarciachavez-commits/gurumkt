<?php
/**
 * Guru Marketing — Crea una suscripción recurrente en Mercado Pago.
 *
 * El navegador envía SOLO: plan, periodo y correo del cliente.
 * El monto se resuelve en el servidor contra mp-catalogo.php, así que no es
 * manipulable desde el navegador.
 *
 * Responde {ok: true, init_point: "..."} y el frontend redirige a esa URL, donde
 * Mercado Pago recibe los datos de la tarjeta. Ningún dato de tarjeta pasa por
 * este servidor.
 */

require_once __DIR__ . '/mp-lib.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mp_json(405, ['ok' => false, 'error' => 'Método no permitido']);
}

$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    mp_json(400, ['ok' => false, 'error' => 'Solicitud inválida']);
}

$planKey    = isset($entrada['plan'])    ? trim((string) $entrada['plan'])    : '';
$periodoKey = isset($entrada['periodo']) ? trim((string) $entrada['periodo']) : '';
$email      = isset($entrada['email'])   ? trim((string) $entrada['email'])   : '';
$negocio    = isset($entrada['negocio']) ? trim((string) $entrada['negocio']) : '';

// --- Validación de entrada -------------------------------------------------

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    mp_json(400, ['ok' => false, 'error' => 'Necesitamos un correo electrónico válido.']);
}

$catalogo = require __DIR__ . '/mp-catalogo.php';

if (!isset($catalogo[$planKey])) {
    mp_log('warn', 'Plan inexistente solicitado', ['plan' => $planKey]);
    mp_json(400, ['ok' => false, 'error' => 'El plan seleccionado no existe.']);
}

$plan = $catalogo[$planKey];

if (!isset($plan['periodos'][$periodoKey])) {
    mp_log('warn', 'Periodo inexistente solicitado', ['plan' => $planKey, 'periodo' => $periodoKey]);
    mp_json(400, ['ok' => false, 'error' => 'La forma de pago seleccionada no existe.']);
}

$periodo = $plan['periodos'][$periodoKey];

// --- Construcción de la suscripción ----------------------------------------

// Referencia propia para conciliar después: plan, periodo y marca de tiempo.
$referencia = sprintf('%s|%s|%s', $planKey, $periodoKey, date('YmdHis'));

$concepto = sprintf(
    'Guru Conecta · %s (%s)',
    $plan['nombre'],
    $periodo['etiqueta']
);

$payload = [
    'reason'             => $concepto,
    'external_reference' => $referencia,
    'payer_email'        => $email,
    'back_url'           => 'https://gurumkt.com.mx/suscripcion-confirmada.html',
    'status'             => 'pending', // el cliente autoriza en el checkout de Mercado Pago
    'auto_recurring'     => [
        'frequency'          => (int) $periodo['frequency'],
        'frequency_type'     => $periodo['frequency_type'],
        'transaction_amount' => (float) $periodo['monto'],
        'currency_id'        => 'MXN',
    ],
];

$respuesta = mp_request('POST', '/preapproval', $payload);

// --- Resultado --------------------------------------------------------------

if ($respuesta['status'] === 0) {
    mp_json(502, ['ok' => false, 'error' => 'No pudimos conectar con Mercado Pago. Intenta de nuevo.']);
}

if ($respuesta['status'] < 200 || $respuesta['status'] >= 300) {
    // El detalle real queda en el log; al cliente no se le exponen datos internos.
    mp_log('error', 'Mercado Pago rechazó la creación de la suscripción', [
        'http'    => $respuesta['status'],
        'plan'    => $planKey,
        'periodo' => $periodoKey,
        'detalle' => $respuesta['body'],
    ]);
    mp_json(502, ['ok' => false, 'error' => 'No pudimos crear la suscripción. Escríbenos por WhatsApp y lo resolvemos.']);
}

$cuerpo     = $respuesta['body'] ?? [];
$initPoint  = $cuerpo['init_point'] ?? null;
$suscripcion = $cuerpo['id'] ?? null;

if (!$initPoint) {
    mp_log('error', 'Mercado Pago no devolvió init_point', ['respuesta' => $cuerpo]);
    mp_json(502, ['ok' => false, 'error' => 'No pudimos iniciar el pago. Escríbenos por WhatsApp y lo resolvemos.']);
}

mp_log('info', 'Suscripción creada, pendiente de autorización', [
    'id'         => $suscripcion,
    'plan'       => $planKey,
    'periodo'    => $periodoKey,
    'monto'      => $periodo['monto'],
    'referencia' => $referencia,
    'prueba'     => mp_es_prueba(),
]);

mp_json(200, [
    'ok'         => true,
    'init_point' => $initPoint,
    'id'         => $suscripcion,
]);
