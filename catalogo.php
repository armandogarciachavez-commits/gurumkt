<?php
/**
 * Guru Marketing — Catálogo de precios.
 *
 * ESTE ARCHIVO ES LA ÚNICA FUENTE DE VERDAD DE LOS PRECIOS.
 *
 * El navegador nunca envía montos: solo manda una clave de producto, y el
 * servidor resuelve aquí el precio. Así nadie puede cambiar el importe desde
 * la consola del navegador.
 *
 * Lo usan por igual la integración de Stripe (activa) y la de Mercado Pago
 * (lista, en pausa hasta que se complete la verificación de la cuenta).
 *
 * Para cambiar un precio, cámbialo aquí y vuelve a desplegar. Las suscripciones
 * ya activas conservan el monto con el que fueron creadas.
 */

return [

// =============================================================
// SUSCRIPCIONES — Guru Conecta (cobro recurrente)
// La implementación NO va aquí: se cobra por transferencia bancaria.
// =============================================================
'suscripciones' => [

    'conecta-esencial' => [
        'nombre'                     => 'Conecta Esencial',
        'descripcion'                => 'WhatsApp automatizado, captura de prospectos y agenda de citas.',
        'respuestas_ia'              => 250,   // tope mensual de respuestas generadas con IA
        'implementacion'             => 2000.00,
        'implementacion_fundadores'  => 1000.00,
        'periodos' => [
            'mensual'   => ['monto' =>  1500.00, 'frequency' =>  1, 'frequency_type' => 'months', 'etiqueta' => 'cada mes'],
            'semestral' => ['monto' =>  7650.00, 'frequency' =>  6, 'frequency_type' => 'months', 'etiqueta' => 'cada 6 meses'],
            'anual'     => ['monto' => 14400.00, 'frequency' => 12, 'frequency_type' => 'months', 'etiqueta' => 'cada año'],
        ],
    ],

    'conecta-crecimiento' => [
        'nombre'                     => 'Conecta Crecimiento',
        'descripcion'                => 'Landing page de captación, flujos de automatización y seguimiento de prospectos.',
        'respuestas_ia'              => 350,   // tope mensual de respuestas generadas con IA
        'implementacion'             => 3500.00,
        'implementacion_fundadores'  => 1750.00,
        'periodos' => [
            'mensual'   => ['monto' =>  2500.00, 'frequency' =>  1, 'frequency_type' => 'months', 'etiqueta' => 'cada mes'],
            'semestral' => ['monto' => 12750.00, 'frequency' =>  6, 'frequency_type' => 'months', 'etiqueta' => 'cada 6 meses'],
            'anual'     => ['monto' => 24000.00, 'frequency' => 12, 'frequency_type' => 'months', 'etiqueta' => 'cada año'],
        ],
    ],

    'conecta-posicionamiento' => [
        'nombre'                     => 'Conecta Posicionamiento',
        'descripcion'                => 'Redes sociales, SEO local y automatización comercial completa.',
        'respuestas_ia'              => 400,   // tope mensual de respuestas generadas con IA
        'implementacion'             => 5000.00,
        'implementacion_fundadores'  => 2500.00,
        'periodos' => [
            'mensual'   => ['monto' =>  3000.00, 'frequency' =>  1, 'frequency_type' => 'months', 'etiqueta' => 'cada mes'],
            'semestral' => ['monto' => 15300.00, 'frequency' =>  6, 'frequency_type' => 'months', 'etiqueta' => 'cada 6 meses'],
            'anual'     => ['monto' => 28800.00, 'frequency' => 12, 'frequency_type' => 'months', 'etiqueta' => 'cada año'],
        ],
    ],
],

// =============================================================
// PAGOS ÚNICOS — se cobran una sola vez
// =============================================================
'unicos' => [

    'invitacion-starter' => [
        'nombre'      => 'Invitación Digital · Starter',
        'descripcion' => 'Invitación digital interactiva, paquete Starter.',
        'monto'       => 600.00,
    ],
    'invitacion-smart' => [
        'nombre'      => 'Invitación Digital · Smart',
        'descripcion' => 'Invitación digital interactiva, paquete Smart.',
        'monto'       => 1300.00,
    ],
    'invitacion-premium' => [
        'nombre'      => 'Invitación Digital · Premium',
        'descripcion' => 'Invitación digital interactiva, paquete Premium.',
        'monto'       => 2500.00,
    ],

    'redes-inicial' => [
        'nombre'      => 'Manejo de Redes · Paquete inicial',
        'descripcion' => 'Paquete inicial de manejo de redes sociales.',
        'monto'       => 3500.00,
    ],
    'branding-inicial' => [
        'nombre'      => 'Branding y Diseño · Costo inicial',
        'descripcion' => 'Imagen gráfica, manual de identidad y aplicaciones.',
        'monto'       => 5000.00,
    ],

],

];
