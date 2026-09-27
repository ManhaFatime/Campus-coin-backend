<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

jsonResponse(true, 'Campus Coin API is online.', [
    'name' => $appConfig['name'],
    'environment' => $appConfig['env'],
    'version' => '1.0.0',
]);
