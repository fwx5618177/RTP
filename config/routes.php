<?php

return [
    'default_routes' => [
        [
            'method' => 'GET',
            'path' => '/',
            'handler' => [\App\Controllers\HomeController::class, 'index'],
            'middleware' => []
        ]
    ],
    'user_routes' => [
        [
            'method' => 'POST',
            'path' => '/users',
            'handler' => [\App\Controllers\UserController::class, 'create'],
            'middleware' => []
        ],
        [
            'method' => 'GET',
            'path' => '/users/{id}',
            'handler' => [\App\Controllers\UserController::class, 'get'],
            'middleware' => []
        ],
        [
            'method' => 'PUT',
            'path' => '/users/{id}',
            'handler' => [\App\Controllers\UserController::class, 'update'],
            'middleware' => []
        ],
        [
            'method' => 'DELETE',
            'path' => '/users/{id}',
            'handler' => [\App\Controllers\UserController::class, 'delete'],
            'middleware' => []
        ]
    ]
];
