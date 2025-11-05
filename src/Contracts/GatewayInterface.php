<?php

namespace Weexduunx\AidaGateway\Contracts;

use Weexduunx\AidaGateway\PaymentResponse;

interface GatewayInterface
{
    /**
     * Initialize a payment transaction.
     *
     * @param string $phoneNumber Customer phone number
     * @param float $amount Amount to charge
     * @param string $description Transaction description
     * @param array $metadata Additional metadata
     * @return PaymentResponse
     */
    public function pay(string $phoneNumber, float $amount, string $description = '', array $metadata = []): PaymentResponse;

    /**
     * Check the status of a transaction.
     *
     * @param string $transactionId Transaction ID
     * @return PaymentResponse
     */
    public function checkStatus(string $transactionId): PaymentResponse;

    /**
     * Refund a transaction.
     *
     * @param string $transactionId Transaction ID to refund
     * @param float|null $amount Amount to refund (null for full refund)
     * @return PaymentResponse
     */
    public function refund(string $transactionId, ?float $amount = null): PaymentResponse;

    /**
     * Verify webhook signature.
     *
     * @param array $payload Webhook payload
     * @param string $signature Webhook signature
     * @return bool
     */
    public function verifyWebhook(array $payload, string $signature): bool;

    /**
     * Get the gateway name.
     *
     * @return string
     */
    public function getName(): string;
}
