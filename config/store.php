<?php

return [

    'phone' => env('STORE_PHONE', '+590690112233'),

    'phone_display' => env('STORE_PHONE_DISPLAY', '+590 690 11 22 33'),

    'whatsapp' => env('STORE_WHATSAPP', '33600000000'),

    'hours' => env('STORE_HOURS', 'Lun – Sam  8h – 19h'),

    'billing' => [
        'line_one' => env('STORE_BILLING_LINE', 'Royal Detailing 971'),
        'city' => env('STORE_BILLING_CITY', 'Les Abymes'),
        'postcode' => env('STORE_BILLING_POSTCODE', '97139'),
        'country_iso' => env('STORE_BILLING_COUNTRY', 'GP'),
    ],

    'admin' => [
        'email' => env('LUNAR_ADMIN_EMAIL', 'admin@royal-detailing.test'),
        'password' => env('LUNAR_ADMIN_PASSWORD', 'password'),
    ],

];
