<?php

namespace Weexduunx\AidaGateway;

use Weexduunx\AidaGateway\Contracts\GatewayInterface;
use Weexduunx\AidaGateway\Gateways\OrangeMoneyGateway;
use Weexduunx\AidaGateway\Gateways\WaveGateway;
use Weexduunx\AidaGateway\Gateways\FreeMoneyGateway;
use Weexduunx\AidaGateway\Gateways\EmoneyGateway;
use Weexduunx\AidaGateway\Exceptions\GatewayNotFoundException;
use Weexduunx\AidaGateway\Exceptions\GatewayNotEnabledException;

class AidaGateway
{
    protected ?string $currentGateway = null;
    protected array $gateways = [];

    public function __construct()
    {
        $this->currentGateway = config('aida-gateway.default');
    }

    /**
     * Set the gateway to use for the next operation.
     *
     * @param string $gateway Gateway name
     * @return $this
     * @throws GatewayNotFoundException
     * @throws GatewayNotEnabledException
     */
    public function gateway(string $gateway): self
    {
        if (!$this->isGatewaySupported($gateway)) {
            throw new GatewayNotFoundException("Gateway '{$gateway}' is not supported.");
        }

        if (!$this->isGatewayEnabled($gateway)) {
            throw new GatewayNotEnabledException("Gateway '{$gateway}' is not enabled.");
        }

        $this->currentGateway = $gateway;

        return $this;
    }

    /**
     * Initialize a payment transaction.
     *
     * @param string $phoneNumber Customer phone number
     * @param float $amount Amount to charge
     * @param string $description Transaction description
     * @param array $metadata Additional metadata
     * @return PaymentResponse
     */
    public function pay(string $phoneNumber, float $amount, string $description = '', array $metadata = []): PaymentResponse
    {
        return $this->getGatewayInstance()->pay($phoneNumber, $amount, $description, $metadata);
    }

    /**
     * Check the status of a transaction.
     *
     * @param string $transactionId Transaction ID
     * @return PaymentResponse
     */
    public function checkStatus(string $transactionId): PaymentResponse
    {
        return $this->getGatewayInstance()->checkStatus($transactionId);
    }

    /**
     * Refund a transaction.
     *
     * @param string $transactionId Transaction ID to refund
     * @param float|null $amount Amount to refund (null for full refund)
     * @return PaymentResponse
     */
    public function refund(string $transactionId, ?float $amount = null): PaymentResponse
    {
        return $this->getGatewayInstance()->refund($transactionId, $amount);
    }

    /**
     * Verify webhook signature.
     *
     * @param array $payload Webhook payload
     * @param string $signature Webhook signature
     * @return bool
     */
    public function verifyWebhook(array $payload, string $signature): bool
    {
        return $this->getGatewayInstance()->verifyWebhook($payload, $signature);
    }

    /**
     * Get all supported gateways.
     *
     * @return array
     */
    public function getSupportedGateways(): array
    {
        return [
            'orange_money' => OrangeMoneyGateway::class,
            'wave' => WaveGateway::class,
            'free_money' => FreeMoneyGateway::class,
            'emoney' => EmoneyGateway::class,
        ];
    }

    /**
     * Get all enabled gateways.
     *
     * @return array
     */
    public function getEnabledGateways(): array
    {
        return array_filter($this->getSupportedGateways(), function ($gateway) {
            return $this->isGatewayEnabled($gateway);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Check if a gateway is supported.
     *
     * @param string $gateway Gateway name
     * @return bool
     */
    public function isGatewaySupported(string $gateway): bool
    {
        return array_key_exists($gateway, $this->getSupportedGateways());
    }

    /**
     * Check if a gateway is enabled in configuration.
     *
     * @param string $gateway Gateway name
     * @return bool
     */
    public function isGatewayEnabled(string $gateway): bool
    {
        return config("aida-gateway.gateways.{$gateway}.enabled", false);
    }

    /**
     * Get the current gateway instance.
     *
     * @return GatewayInterface
     * @throws GatewayNotFoundException
     */
    protected function getGatewayInstance(): GatewayInterface
    {
        $gateway = $this->currentGateway ?? config('aida-gateway.default');

        if (!isset($this->gateways[$gateway])) {
            $this->gateways[$gateway] = $this->createGatewayInstance($gateway);
        }

        return $this->gateways[$gateway];
    }

    /**
     * Create a new gateway instance.
     *
     * @param string $gateway Gateway name
     * @return GatewayInterface
     * @throws GatewayNotFoundException
     */
    protected function createGatewayInstance(string $gateway): GatewayInterface
    {
        $supportedGateways = $this->getSupportedGateways();

        if (!isset($supportedGateways[$gateway])) {
            throw new GatewayNotFoundException("Gateway '{$gateway}' is not supported.");
        }

        $gatewayClass = $supportedGateways[$gateway];
        $config = config("aida-gateway.gateways.{$gateway}", []);

        return new $gatewayClass($config);
    }
}
