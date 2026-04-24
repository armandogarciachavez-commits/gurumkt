<?php
// backend/payment.php

// 1. Validar Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

// 2. Configuración (PEGA TUS CLAVES AQUÍ)
// ⚠️ IMPORTANTE: 'sk_test_...' es la CLAVE SECRETA de tu Dashboard de Stripe
$stripeSecretKey = 'sk_test_tu_clave_secreta_aqui'; 

// 3. Leer datos del Frontend
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Precio por defecto (si no se envía) es $3,500.00 MXN
$amount = isset($data['amount']) ? $data['amount'] : 350000; // En centavos

try {
    // 4. Llamada a Stripe API (Sin librerías externas para compatibilidad total)
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, "https://api.stripe.com/v1/payment_intents");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $stripeSecretKey . ":");
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'amount' => $amount,
        'currency' => 'mxn',
        'automatic_payment_methods[enabled]' => 'true',
        'description' => 'Servicio Guru Marketing'
    ]));
    
    $headers = array();
    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $result = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    
    curl_close($ch);
    
    echo $result; // Devuelve el client_secret al frontend

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
