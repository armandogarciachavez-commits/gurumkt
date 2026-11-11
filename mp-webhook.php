<?php
/**
 * Guru Marketing — Receptor de notificaciones (webhooks) de Mercado Pago.
 *
 * Configura esta URL en Mercado Pago:
 *   https://gurumkt.com.mx/mp-webhook.php
 * con los temas: subscription_preapproval y subscription_authorized_payment.
 *
 * PRINCIPIO DE SEGURIDAD: el contenido de la notificación NUNCA se cree.
 * Solo se toma el ID y se vuelve a consultar el recurso directamente a la API
 * de Mercado Pago con nuestro access token. Así, aunque alguien falsifique una
 * notificación, no puede inventar un pago: la verdad siempre viene de la API.
 *
 * La firma x-signature se valida como capa adicional, pero el flujo no depende
 * de ella (ver mp_firma_valida en mp-lib.php).
 */

require_once __DIR__ . '/mp-lib.php';

// Mercado Pago reintenta si no recibe 2xx. Respondemos 200 cuanto antes salvo
// que la solicitud sea claramente inválida.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$crudo  = file_get_contents('php://input');
$cuerpo = json_decode($crudo, true);

if (!is_array($cuerpo)) {
    mp_log('warn', 'Webhook con cuerpo no interpretable');
    http_response_code(400);
    exit;
}

// El tema llega como `type` o como `topic` según el origen de la notificación.
$tipo = $cuerpo['type'] ?? $cuerpo['topic'] ?? '';

// El id del recurso llega en data.id (cuerpo) y también en la query string.
$dataId = $cuerpo['data']['id']
    ?? $_GET['data.id']
    ?? $_GET['data_id']
    ?? $_GET['id']
    ?? null;

if (!$dataId) {
    mp_log('warn', 'Webhook sin id de recurso', ['tipo' => $tipo]);
    http_response_code(400);
    exit;
}

// --- Capa 1: firma (informativa, no bloqueante) -----------------------------

$firma = mp_firma_valida($dataId);
if ($firma === false) {
    // Se registra pero no se descarta: la verdad la da la consulta a la API.
    mp_log('warn', 'Webhook con firma inválida', ['tipo' => $tipo, 'data_id' => $dataId]);
}

// --- Capa 2: consulta autoritativa a la API ---------------------------------

switch ($tipo) {

    case 'subscription_preapproval':
    case 'preapproval':
        $r = mp_request('GET', '/preapproval/' . rawurlencode($dataId));
        if ($r['status'] < 200 || $r['status'] >= 300) {
            mp_log('error', 'No se pudo consultar la suscripción notificada', [
                'data_id' => $dataId,
                'http'    => $r['status'],
            ]);
            // 200 de todos modos: reintentar no va a cambiar un 404.
            http_response_code(200);
            exit;
        }

        $s      = $r['body'] ?? [];
        $estado = $s['status'] ?? 'desconocido';
        $monto  = $s['auto_recurring']['transaction_amount'] ?? null;

        mp_log('info', 'Suscripción actualizada', [
            'id'         => $s['id'] ?? $dataId,
            'estado'     => $estado,
            'monto'      => $monto,
            'referencia' => $s['external_reference'] ?? null,
            'email'      => $s['payer_email'] ?? null,
            'proximo'    => $s['next_payment_date'] ?? null,
        ]);

        // Avisos al equipo en los cambios que requieren acción humana.
        if ($estado === 'authorized') {
            mp_avisar(
                'Guru Conecta: nueva suscripción activa',
                "Se activó una suscripción.\n\n"
                . 'Plan / referencia: ' . ($s['external_reference'] ?? 'sin referencia') . "\n"
                . 'Correo del cliente: ' . ($s['payer_email'] ?? 'sin correo') . "\n"
                . 'Monto: $' . ($monto ?? '?') . " MXN\n"
                . 'Próximo cobro: ' . ($s['next_payment_date'] ?? 'sin fecha') . "\n"
                . 'ID en Mercado Pago: ' . ($s['id'] ?? $dataId) . "\n\n"
                . "Hay que arrancar la implementación con este cliente."
            );
        } elseif ($estado === 'cancelled' || $estado === 'canceled') {
            mp_avisar(
                'Guru Conecta: suscripción cancelada',
                "Se canceló una suscripción.\n\n"
                . 'Plan / referencia: ' . ($s['external_reference'] ?? 'sin referencia') . "\n"
                . 'Correo del cliente: ' . ($s['payer_email'] ?? 'sin correo') . "\n"
                . 'ID en Mercado Pago: ' . ($s['id'] ?? $dataId) . "\n"
            );
        }
        break;

    case 'subscription_authorized_payment':
        $r = mp_request('GET', '/authorized_payments/' . rawurlencode($dataId));
        if ($r['status'] < 200 || $r['status'] >= 300) {
            mp_log('error', 'No se pudo consultar el cobro recurrente notificado', [
                'data_id' => $dataId,
                'http'    => $r['status'],
            ]);
            http_response_code(200);
            exit;
        }

        $p      = $r['body'] ?? [];
        $estado = $p['status'] ?? 'desconocido';

        mp_log('info', 'Cobro recurrente', [
            'id'           => $p['id'] ?? $dataId,
            'suscripcion'  => $p['preapproval_id'] ?? null,
            'estado'       => $estado,
            'detalle'      => $p['payment']['status_detail'] ?? ($p['status_detail'] ?? null),
            'monto'        => $p['transaction_amount'] ?? null,
        ]);

        // Un cobro rechazado es lo único que necesita atención inmediata:
        // Mercado Pago reintenta, pero conviene hablar con el cliente.
        if ($estado === 'rejected' || $estado === 'cancelled') {
            mp_avisar(
                'Guru Conecta: cobro mensual rechazado',
                "Falló un cobro recurrente.\n\n"
                . 'Suscripción: ' . ($p['preapproval_id'] ?? 'sin id') . "\n"
                . 'Monto: $' . ($p['transaction_amount'] ?? '?') . " MXN\n"
                . 'Estado: ' . $estado . "\n"
                . 'Motivo: ' . ($p['payment']['status_detail'] ?? 'sin detalle') . "\n\n"
                . "Mercado Pago reintentará, pero conviene contactar al cliente."
            );
        }
        break;

    case 'subscription_preapproval_plan':
        mp_log('info', 'Notificación de plan de suscripción', ['data_id' => $dataId]);
        break;

    default:
        mp_log('info', 'Notificación ignorada', ['tipo' => $tipo, 'data_id' => $dataId]);
        break;
}

http_response_code(200);
echo 'OK';
