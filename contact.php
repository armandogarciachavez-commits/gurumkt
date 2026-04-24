<?php
/**
 * Guru Marketing - Contact form endpoint
 * Valida reCAPTCHA Enterprise y envía el correo al destinatario.
 */

header('Content-Type: application/json; charset=utf-8');

// ============================================================
// CONFIGURACIÓN - EDITA ESTOS VALORES
// ============================================================
$RECAPTCHA_SITE_KEY    = '6Le74cQsAAAAAIBQIFI6HBUgjLUFAYhRt32-juwB';
$RECAPTCHA_PROJECT_ID  = 'TU_PROJECT_ID_DE_GOOGLE_CLOUD';   // <-- pendiente
$RECAPTCHA_API_KEY     = 'TU_API_KEY_DE_GOOGLE_CLOUD';      // <-- pendiente
$RECAPTCHA_ACTION      = 'contact';
$RECAPTCHA_MIN_SCORE   = 0.5;

$MAIL_TO       = 'a.garcia@gurumkt.com.mx';
$MAIL_FROM     = 'no-reply@gurumkt.com.mx';
$MAIL_SUBJECT  = 'Nuevo Mensaje de Sitio Web Guru Marketing';
// ============================================================

function json_response($code, $payload) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['ok' => false, 'error' => 'Método no permitido']);
}

// Honeypot: si un bot llenó el campo oculto, descartamos silenciosamente
if (!empty($_POST['_honey'])) {
    json_response(200, ['ok' => true]);
}

$nombre   = trim($_POST['nombre']   ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email    = trim($_POST['email']    ?? '');
$servicio = trim($_POST['servicio'] ?? '');
$mensaje  = trim($_POST['mensaje']  ?? '');
$token    = trim($_POST['recaptcha_token'] ?? '');

if ($nombre === '' || $telefono === '' || $email === '') {
    json_response(400, ['ok' => false, 'error' => 'Faltan campos obligatorios']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(400, ['ok' => false, 'error' => 'Correo inválido']);
}
if ($token === '') {
    json_response(400, ['ok' => false, 'error' => 'Falta token de verificación']);
}

// ============================================================
// Validación reCAPTCHA Enterprise contra Google Cloud
// ============================================================
$endpoint = sprintf(
    'https://recaptchaenterprise.googleapis.com/v1/projects/%s/assessments?key=%s',
    urlencode($RECAPTCHA_PROJECT_ID),
    urlencode($RECAPTCHA_API_KEY)
);

$payload = json_encode([
    'event' => [
        'token'          => $token,
        'expectedAction' => $RECAPTCHA_ACTION,
        'siteKey'        => $RECAPTCHA_SITE_KEY,
    ],
]);

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    error_log("reCAPTCHA HTTP $httpCode: $response");
    json_response(502, ['ok' => false, 'error' => 'No se pudo verificar el captcha']);
}

$result = json_decode($response, true);
$valid  = $result['tokenProperties']['valid'] ?? false;
$action = $result['tokenProperties']['action'] ?? '';
$score  = $result['riskAnalysis']['score'] ?? 0;

if (!$valid || $action !== $RECAPTCHA_ACTION || $score < $RECAPTCHA_MIN_SCORE) {
    error_log('reCAPTCHA rechazado: ' . $response);
    json_response(403, ['ok' => false, 'error' => 'Verificación de seguridad fallida']);
}

// ============================================================
// Envío del correo
// ============================================================
$body  = "Nombre:   $nombre\n";
$body .= "Teléfono: $telefono\n";
$body .= "Correo:   $email\n";
$body .= "Servicio: $servicio\n\n";
$body .= "Mensaje:\n$mensaje\n\n";
$body .= "---\nreCAPTCHA score: $score | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'n/a');

$headers  = "From: Guru Marketing <$MAIL_FROM>\r\n";
$headers .= "Reply-To: $nombre <$email>\r\n";
$headers .= "Content-Type: text/plain; charset=utf-8\r\n";

if (!mail($MAIL_TO, $MAIL_SUBJECT, $body, $headers)) {
    json_response(500, ['ok' => false, 'error' => 'No se pudo enviar el correo']);
}

json_response(200, ['ok' => true]);
