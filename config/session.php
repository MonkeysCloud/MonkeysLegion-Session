<?php

return [

    /*━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━*
     * Session Driver
     *━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━*/
    // The default session driver to use.
    'default' => 'file',

    // Supported session drivers and their configurations.
    'drivers' => [
        'file' => [
            // Directory where session files will be stored.
            'path' => base_path('var/sessions'),
            // Session lifetime in seconds (2 hours).
            'lifetime' => 7200,
        ],
        'database' => [
            // Database table to store sessions.
            'table' => 'sessions',
        ],
        'redis' => [
            // Session lifetime in seconds (2 hours).
            'lifetime' => 7200,
        ],
    ],

    /*━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━*
     * Session Cookie Configuration
     *━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━*/

    // Name of the session cookie.
    'cookie_name' => 'ml_session',

    // Lifetime of the session cookie in seconds (2 hours).
    'cookie_lifetime' => 7200,
    // Path for which the session cookie is available.
    'cookie_path' => '/',
    // Domain that the session cookie is available to.
    'cookie_domain' => '',
    // Whether the session cookie should only be sent over secure connections.
    'cookie_secure' => true,
    // Whether the session cookie is accessible only through the HTTP protocol.
    'cookie_httponly' => true,
    // SameSite setting for the session cookie ('Lax', 'Strict', or 'None').
    'cookie_samesite' => 'Lax',
];
