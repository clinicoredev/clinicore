<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Identidad comercial de CliniCore
    |--------------------------------------------------------------------------
    */
    'brand' => [
        'name' => env('CLINICORE_BRAND_NAME', 'CliniCore'),
        'domain' => env('CLINICORE_DOMAIN', 'clinicore.es'),
        'url' => env('CLINICORE_URL', 'https://clinicore.es'),
        'email' => env('CLINICORE_CONTACT_EMAIL', 'clinicore.dev@gmail.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de marcas de agua en exportaciones y vistas imprimibles
    |--------------------------------------------------------------------------
    |
    | Control centralizado para activar o desactivar la marca de agua de
    | CliniCore en PDFs, archivos Excel, feeds .ics y vistas de impresión.
    | Preparado para vincularse en el futuro al plan de suscripción del servicio.
    |
    */
    'exports' => [
        'watermark_enabled' => (bool) env('CLINICORE_EXPORT_WATERMARK', true),
        'pdf_diagonal_watermark' => (bool) env('CLINICORE_PDF_DIAGONAL_WATERMARK', true),
    ],
];
