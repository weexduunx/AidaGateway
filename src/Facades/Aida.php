<?php

namespace Weexduunx\AidaGateway\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Weexduunx\AidaGateway\PaymentResponse pay(string $phoneNumber, float $amount, string $description = '', array $metadata = [])
 * @method static \Weexduunx\AidaGateway\PaymentResponse checkStatus(string $transactionId)
 * @method static \Weexduunx\AidaGateway\AidaGateway gateway(string $gateway = null)
 * @method static array getSupportedGateways()
 * @method static \Weexduunx\AidaGateway\PaymentResponse refund(string $transactionId, float $amount = null)
 *
 * @see \Weexduunx\AidaGateway\AidaGateway
 */
class Aida extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'aida-gateway';
    }
}
