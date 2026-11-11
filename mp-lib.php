<?php
/**
 * Guru Marketing — Utilidades compartidas para la integración con Mercado Pago.
 *
 * No expone ningún endpoint: solo lo incluyen mp-suscripcion.php y mp-webhook.php.
 */

if (!defined('MP_API')) {
    define('MP_API', 'https://api.mercadopago.com');
}

/**
 * Carga config.php (no versionado) y devuelve el bloque de Mercado Pago.
 * Termina la ejecución con 500 si falta la configuración.
 */
function mp_config() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $configFile = __DIR__ . '/config.php';
    if (!file_exists($configFile)) {
        mp_log('error', 'Falta config.php en el servidor');
        mp_json(500, ['ok' => false, 'error' => 'Configuración no disponible']);
    }

    $config = require $configFile;
    $mp = $config['mercadopago'] ?? null;

    if (empty($mp['access_token'])) {
        mp_log('error', 'Falta mercadopago.access_token en config.php');
        mp_json(500, ['ok' => false, 'error' => 'Configuración no disponible']);
    }

    $cache = $mp;
    return $cache;
}

/** Devuelve true si las credenciales cargadas son de prueba. */
function mp_es_prueba() {
    $mp = mp_config();
    return strpos($mp['access_token'], 'TEST-') === 0;
}

/**
 * Llama a la API de Mercado Pago.
 *
 * @return array ['status' => int, 'body' => array|null, 'raw' => string]
 */
function mp_request($method, $path, $payload = null) {
    $mp = mp_config();

    $ch = curl_init(MP_API . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $headers = [
        'Authorization: Bearer ' . $mp['access_token'],
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    if ($err !== null) {
        mp_log('error', "cURL $method $path: $err");
        return ['status' => 0, 'body' => null, 'raw' => '', 'error' => $err];
    }

    return [
        'status' => $status,
        'body'   => json_decode($raw, true),
        'raw'    => $raw,
    ];
}

/**
 * Valida la firma `x-signature` de un webhook.
 *
 * El manifest que Mercado Pago firma NO está publicado en su documentación
 * oficial; el formato usado aquí ("id:...;request-id:...;ts:...;") proviene de
 * implementaciones de la comunidad y podría cambiar sin aviso.
 *
 * Por eso la seguridad del webhook NO depende de esta función: mp-webhook.php
 * siempre vuelve a consultar el recurso directamente a la API de Mercado Pago
 * antes de actuar. Esta validación es una capa adicional, no la única.
 *
 * @return bool|null true válida, false inválida, null si no se pudo evaluar
 *                   (sin secreto configurado o sin cabeceras).
 */
function mp_firma_valida($dataId) {
    $mp     = mp_config();
    $secret = $mp['webhook_secret'] ?? '';

    if ($secret === '') {
        return null; // sin secreto configurado: no se puede evaluar
    }

    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';

    if ($signature === '') {
        return null;
    }

    // Formato: "ts=1704908010,v1=<hex>". Puede traer varios pares.
    $ts = null;
    $v1 = null;
    foreach (preg_split('/[,\s]+/', $signature) as $parte) {
        $kv = explode('=', $parte, 2);
        if (count($kv) !== 2) {
            continue;
        }
        $clave = trim($kv[0]);
        $valor = trim($kv[1]);
        if ($clave === 'ts' && $ts === null) {
            $ts = $valor;
        } elseif ($clave === 'v1' && $v1 === null) {
            $v1 = $valor;
        }
    }

    if ($ts === null || $v1 === null) {
        return false;
    }

    // Rechaza notificaciones con más de 5 minutos: protege contra reenvíos.
    if (ctype_digit($ts) && abs(time() - (int) $ts) > 300) {
        mp_log('warn', "Firma con timestamp fuera de ventana (ts=$ts)");
        return false;
    }

    $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
    $esperada = hash_hmac('sha256', $manifest, $secret);

    return hash_equals($esperada, strtolower($v1));
}

/** Escribe una línea en el log de Mercado Pago. */
function mp_log($nivel, $mensaje, $contexto = null) {
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

    @file_put_contents($dir . '/mercadopago.log', $linea, FILE_APPEND | LOCK_EX);
}

/** Responde JSON y termina. */
function mp_json($code, $payload) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Envía un aviso por correo al equipo. Nunca interrumpe el flujo si falla. */
function mp_avisar($asunto, $cuerpo) {
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
