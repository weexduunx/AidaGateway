<?php

use Illuminate\Support\Facades\Route;
use Weexduunx\AidaGateway\Http\Controllers\WebhookController;

$prefix = config('aida-gateway.webhook.route_prefix', 'aida/webhooks');
$middleware = config('aida-gateway.webhook.middleware', ['api']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () {
        Route::post('/orange-money', [WebhookController::class, 'orangeMoney'])
            ->name('aida.webhook.orange-money');

        Route::post('/wave', [WebhookController::class, 'wave'])
            ->name('aida.webhook.wave');

        Route::post('/free-money', [WebhookController::class, 'freeMoney'])
            ->name('aida.webhook.free-money');

        Route::post('/emoney', [WebhookController::class, 'emoney'])
            ->name('aida.webhook.emoney');
    });
