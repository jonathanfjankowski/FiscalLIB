<?php

return [
    'base_url' => env('FISCAL_API_BASE_URL', 'http://localhost:8080'),
    'api_key' => env('FISCAL_API_KEY'),
    // producao|homologacao — deve bater com o ambiente da API key (fk_live_/fk_test_)
    'ambiente' => env('FISCAL_AMBIENTE', 'homologacao'),
    'timeout' => (int) env('FISCAL_TIMEOUT_HTTP', 30),
];
