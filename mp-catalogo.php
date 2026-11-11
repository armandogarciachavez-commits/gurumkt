<?php
/**
 * Guru Marketing — Catálogo de planes de Guru Conecta.
 *
 * ESTE ARCHIVO ES LA ÚNICA FUENTE DE VERDAD DE LOS PRECIOS.
 *
 * El navegador nunca envía montos: solo manda las claves `plan` y `periodo`,
 * y el servidor resuelve el precio aquí. Así nadie puede manipular el importe
 * desde la consola del navegador.
 *
 * Para cambiar un precio, cámbialo aquí y vuelve a desplegar. Las suscripciones
 * ya activas conservan el monto con el que fueron creadas; para modificarlas hay
 * que actualizarlas en Mercado Pago.
 */

return [

    'conecta-esencial' => [
        'nombre'                     => 'Conecta Esencial',
        'descripcion'                => 'WhatsApp automatizado, captura de prospectos y agenda de citas.',
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
        'implementacion'             => 5000.00,
        'implementacion_fundadores'  => 2500.00,
        'periodos' => [
            'mensual'   => ['monto' =>  3000.00, 'frequency' =>  1, 'frequency_type' => 'months', 'etiqueta' => 'cada mes'],
            'semestral' => ['monto' => 15300.00, 'frequency' =>  6, 'frequency_type' => 'months', 'etiqueta' => 'cada 6 meses'],
            'anual'     => ['monto' => 28800.00, 'frequency' => 12, 'frequency_type' => 'months', 'etiqueta' => 'cada año'],
        ],
    ],

];
