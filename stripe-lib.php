<?php
/**
 * Guru Marketing — Utilidades compartidas para la integración con Stripe.
 *
 * No expone ningún endpoint: solo lo incluyen stripe-checkout.php y
 * stripe-webhook.php.
 */

if (!defined('STRIPE_API')) {
    define('STRIPE_API', 'https://api.stripe.com/v1');
}

/**
 * Carga config.php (no versionado) y devuelve el bloque de Stripe.
 * Termina la ejecución con 500 si falta la configuración.
 */
function stripe_config() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $configFile = __DIR__ . '/config.php';
    if (!file_exists($configFile)) {
        stripe_log('error', 'Falta config.php en el servidor');
        stripe_json(500, ['ok' => false, 'error' => 'Configuración no disponible']);
    }

    $config = require $configFile;
    $s = $config['stripe'] ?? null;

    if (empty($s['secret_key'])) {
        stripe_log('error', 'Falta stripe.secret_key en config.php');
        stripe_json(500, ['ok' => false, 'error' => 'Configuración no disponible']);
    }

    $cache = $s;
    return $cache;
}

/** Devuelve true si las credenciales cargadas son de prueba. */
function stripe_es_prueba() {
    return strpos(stripe_config()['secret_key'], 'sk_test_') === 0;
}

/**
 * Llama a la API de Stripe.
 *
 * Stripe no acepta JSON: el cuerpo va como application/x-www-form-urlencoded
 * con la convención de corchetes para objetos anidados, que http_build_query
 * genera correctamente.
 *
 * @return array ['status' => int, 'body' => array|null, 'raw' => string]
 */
function stripe_request($method, $path, $params = null, $idempotencyKey = null) {
    $cfg = stripe_config();

    $ch = curl_init(STRIPE_API . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    // Autenticación: la clave secreta como usuario, sin contraseña.
    curl_setopt($ch, CURLOPT_USERPWD, $cfg['secret_key'] . ':');

    $headers = ['Content-Type: application/x-www-form-urlencoded'];
    if ($idempotencyKey !== null) {
        // Evita cobros duplicados si el cliente da doble clic o hay un reintento.
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($params !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    if ($err !== null) {
        stripe_log('error', "cURL $method $path: $err");
        return ['status' => 0, 'body' => null, 'raw' => '', 'error' => $err];
    }

    return [
        'status' => $status,
        'body'   => json_decode($raw, true),
        'raw'    => $raw,
    ];
}

/**
 * Verifica la cabecera Stripe-Signature según el procedimiento documentado:
 *   1. Separar la cabecera por comas y cada parte por "=".
 *   2. signed_payload = "{timestamp}.{cuerpo crudo}".
 *   3. HMAC-SHA256 con el signing secret del endpoint.
 *   4. Comparar en tiempo constante contra cada firma v1 (puede haber varias
 *      mientras se rota el secreto). Se ignoran esquemas distintos de v1.
 *
 * @param string $payload Cuerpo CRUDO, sin parsear ni reformatear.
 * @return bool
 */
function stripe_firma_valida($payload, $tolerancia = 300) {
    $cfg    = stripe_config();
    $secret = $cfg['webhook_secret'] ?? '';
    $header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    if ($secret === '' || $header === '') {
        return false;
    }

    $ts  = null;
    $v1s = [];
    foreach (explode(',', $header) as $parte) {
        $kv = explode('=', trim($parte), 2);
        if (count($kv) !== 2) {
            continue;
        }
        if ($kv[0] === 't') {
            $ts = $kv[1];
        } elseif ($kv[0] === 'v1') {
            $v1s[] = $kv[1];
        }
        // v0 es solo de sandbox y debe ignorarse; cualquier otro esquema también.
    }

    if ($ts === null || !$v1s) {
        return false;
    }

    // Rechaza reenvíos antiguos. Stripe recomienda 5 minutos y nunca 0.
    if (!ctype_digit($ts) || abs(time() - (int) $ts) > $tolerancia) {
        stripe_log('warn', "Webhook fuera de la ventana de tolerancia (ts=$ts)");
        return false;
    }

    $esperada = hash_hmac('sha256', $ts . '.' . $payload, $secret);

    foreach ($v1s as $firma) {
        if (hash_equals($esperada, $firma)) {
            return true;
        }
    }

    return false;
}

/** Convierte pesos a la unidad mínima que espera Stripe (centavos). */
function stripe_centavos($pesos) {
    return (int) round(((float) $pesos) * 100);
}

/** Escribe una línea en el log de pagos. */
function stripe_log($nivel, $mensaje, $contexto = null) {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $linea = sprintf(
        "[%s] %-5s %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($nivel),
        $mensaje,
        $contexto !== null ? ' | ' . json_encode($contexto, JSON_UNESCAPED_UNICODE) : ''
    );

    @file_put_contents($dir . '/stripe.log', $linea, FILE_APPEND | LOCK_EX);
}

/** Responde JSON y termina. */
function stripe_json($code, $payload) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Envía un aviso por correo al equipo. Nunca interrumpe el flujo si falla. */
function stripe_avisar($asunto, $cuerpo) {
    $configFile = __DIR__ . '/config.php';
    if (!file_exists($configFile)) {
        return;
    }
    $config = require $configFile;

    $to   = $config['mail']['to']   ?? null;
    $from = $config['mail']['from'] ?? null;
    if (!$to || !$from) {
        return;
    }

    $headers = "From: $from\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "MIME-Version: 1.0\r\n";

    @mail($to, $asunto, $cuerpo, $headers);
}
