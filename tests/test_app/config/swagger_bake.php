<?php
return [
    'SwaggerBake' => [
        'prefix' => '/api',
        'yml' => '/config/swagger.yml',
        'json' => '/webroot/swagger.json',
        'webPath' => '/swagger.json',
        'hotReload' => false,
        'namespaces' => [
            'controllers' => ['\App\\'],
            'entities' => ['\App\\'],
        ]
    ]
];
