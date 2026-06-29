<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Laravel\Ai\AiServiceProvider;

return [
    AppServiceProvider::class,
    AiServiceProvider::class,
    FortifyServiceProvider::class,
];
