<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The React SPA is served by Vite on its own port, so the browser sends
    | cross-origin XHR requests to this API. `supports_credentials` is true
    | because Sanctum's stateful cookie authentication is the mechanism in use:
    | the session cookie and the XSRF-TOKEN cookie both have to survive a
    | cross-origin request. The allowed origins come from `FRONTEND_URL` and
    | `FRONTEND_URL_ALT`, so a deployment changes configuration, not code.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        env('FRONTEND_URL_ALT', 'http://127.0.0.1:5173'),
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
