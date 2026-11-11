<?php
/**
 * Guru Marketing — Receptor de eventos (webhooks) de Stripe.
 *
 * Configura esta URL en Stripe (Developers > Webhooks):
 *   https://gurumkt.com.mx/stripe-webhook.php
 * con los eventos:
 *   checkout.session.completed
 *   customer.subscription.updated
 *   customer.subscription.deleted
 *   invoice.payment_succeeded
 *   invoice.payment_failed
 *
 * SEGURIDAD: se rechaza todo evento cuya firma no se pueda verificar. A
 * diferencia de otras pasarelas, Stripe documenta el procedimiento de firma,
 * así que aquí sí es la barrera principal (ver stripe_firma_valida).
 */

require_once __DIR__ . '/stripe-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// El cuerpo CRUDO es imprescindible: la firma se calcula sobre estos bytes
// exactos, antes de cualquier parseo.
$crudo = file_get_contents('php://input');

if (!stripe_firma_valida($crudo)) {
    stripe_log('warn', 'Webhook con firma inválida: descartado');
    http_response_code(400);
    exit;
}

$evento = json_decode($crudo, true);
if (!is_array($evento)) {
    http_response_code(400);
    exit;
}

$tipo   = $evento['type'] ?? '';
$objeto = $evento['data']['object'] ?? [];

switch ($tipo) {

    // El cliente completó el checkout. Es el momento de dar por iniciado el
    // servicio: aquí llegan tanto suscripciones como pagos únicos.
    case 'checkout.session.completed':
        $esSuscripcion = ($objeto['mode'] ?? '') === 'subscription';
        $monto   = isset($objeto['amount_total']) ? $objeto['amount_total'] / 100 : null;
        $correo  = $objeto['customer_details']['email'] ?? ($objeto['customer_email'] ?? null);
        $meta    = $objeto['metadata'] ?? [];

        stripe_log('info', 'Checkout completado', [
            'sesion'      => $objeto['id'] ?? null,
            'modo'        => $objeto['mode'] ?? null,
            'monto'       => $monto,
            'email'       => $correo,
            'referencia'  => $objeto['client_reference_id'] ?? null,
            'metadata'    => $meta,
        ]);

        if ($esSuscripcion) {
            stripe_avisar(
                'Guru Conecta: nueva suscripción activa',
                "Se activó una suscripción.\n\n"
                . 'Plan: '     . ($meta['plan'] ?? 'sin dato') . "\n"
                . 'Periodo: '  . ($meta['periodo'] ?? 'sin dato') . "\n"
                . 'Negocio: '  . ($meta['negocio'] ?? 'sin dato') . "\n"
                . 'Correo: '   . ($correo ?? 'sin correo') . "\n"
                . 'Monto: $'   . ($monto ?? '?') . " MXN\n"
                . 'Suscripción en Stripe: ' . ($objeto['subscription'] ?? 'sin id') . "\n\n"
                . "Pendientes: cobrar la implementación por transferencia y arrancar el onboarding."
            );
        } else {
            stripe_avisar(
                'Guru Marketing: nuevo pago recibido',
                "Se recibió un pago único.\n\n"
                . 'Producto: ' . ($meta['producto'] ?? 'sin dato') . "\n"
                . 'Correo: '   . ($correo ?? 'sin correo') . "\n"
                . 'Monto: $'   . ($monto ?? '?') . " MXN\n"
                . 'Sesión: '   . ($objeto['id'] ?? 'sin id') . "\n"
            );
        }
        break;

    // Cobro recurrente exitoso: solo se registra, no requiere acción.
    case 'invoice.payment_succeeded':
        stripe_log('info', 'Cobro recurrente exitoso', [
            'factura'     => $objeto['id'] ?? null,
            'suscripcion' => $objeto['subscription'] ?? null,
            'monto'       => isset($objeto['amount_paid']) ? $objeto['amount_paid'] / 100 : null,
            'email'       => $objeto['customer_email'] ?? null,
        ]);
        break;

    // Cobro rechazado: es lo único que necesita atención inmediata.
    case 'invoice.payment_failed':
        $monto  = isset($objeto['amount_due']) ? $objeto['amount_due'] / 100 : null;
        $correo = $objeto['customer_email'] ?? null;

        stripe_log('warn', 'Cobro recurrente rechazado', [
            'factura'     => $objeto['id'] ?? null,
            'suscripcion' => $objeto['subscription'] ?? null,
            'monto'       => $monto,
            'email'       => $correo,
            'intento'     => $objeto['attempt_count'] ?? null,
        ]);

        stripe_avisar(
            'Guru Conecta: cobro mensual rechazado',
            "Falló un cobro recurrente.\n\n"
            . 'Cliente: '     . ($correo ?? 'sin correo') . "\n"
            . 'Monto: $'      . ($monto ?? '?') . " MXN\n"
            . 'Suscripción: ' . ($objeto['subscription'] ?? 'sin id') . "\n"
            . 'Intento: '     . ($objeto['attempt_count'] ?? '?') . "\n\n"
            . "Stripe reintentará, pero conviene contactar al cliente."
        );
        break;

    // Cambios de estado: interesa sobre todo past_due y unpaid.
    case 'customer.subscription.updated':
        $estado = $objeto['status'] ?? 'desconocido';
        stripe_log('info', 'Suscripción actualizada', [
            'suscripcion' => $objeto['id'] ?? null,
            'estado'      => $estado,
            'metadata'    => $objeto['metadata'] ?? [],
        ]);
        if (in_array($estado, ['past_due', 'unpaid'], true)) {
            stripe_avisar(
                'Guru Conecta: suscripción con pago vencido',
                "Una suscripción quedó en estado \"$estado\".\n\n"
                . 'Suscripción: ' . ($objeto['id'] ?? 'sin id') . "\n"
                . "Conviene contactar al cliente antes de que se cancele sola."
            );
        }
        break;

    case 'customer.subscription.deleted':
        stripe_log('info', 'Suscripción cancelada', [
            'suscripcion' => $objeto['id'] ?? null,
            'metadata'    => $objeto['metadata'] ?? [],
        ]);
        stripe_avisar(
            'Guru Conecta: suscripción cancelada',
            "Se canceló una suscripción.\n\n"
            . 'Suscripción: ' . ($objeto['id'] ?? 'sin id') . "\n"
            . 'Plan: '        . ($objeto['metadata']['plan'] ?? 'sin dato') . "\n"
        );
        break;

    default:
        stripe_log('info', 'Evento ignorado', ['tipo' => $tipo]);
        break;
}

// Stripe reintenta hasta 3 días si no recibe 2xx.
http_response_code(200);
echo 'OK';
