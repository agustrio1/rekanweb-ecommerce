<?php

return [
    'api_key' => \App\Models\Setting::credential('biteship_api_key', (string) env('BITESHIP_API_KEY', '')),
    'base_url' => rtrim((string) env('BITESHIP_BASE_URL', 'https://api.biteship.com'), '/'),
    'couriers' => preg_replace('/\s+/', '', (string) env('BITESHIP_COURIERS', 'jne,sicepat,jnt,anteraja,tiki,pos')),
    'timeout' => 10,
];