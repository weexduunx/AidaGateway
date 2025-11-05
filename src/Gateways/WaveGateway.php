<?php

namespace Weexduunx\AidaGateway\Gateways;

use Weexduunx\AidaGateway\PaymentResponse;

class WaveGateway extends AbstractGateway
{
    protected string $gatewayName = 'wave';

    /**
     * Initialize a payment transaction.
     */
    public function pay(string $phoneNumber, float $amount, string $description = '', array $metadata = []): PaymentResponse
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
            $reference = $this->generateTransactionReference();

            $payload = [
                'amount' => $amount,
                'currency' => $this->config['currency'],
                'client_reference' => $reference,
                'description' => $description ?: 'Payment via Wave',
                'metadata' => $metadata,
            ];

            $response = $this->makeRequest('POST', '/v1/checkout/sessions', $payload, [
                'Authorization' => 'Bearer ' . $this->config['api_key'],
            ]);

            if (isset($response['id']) && isset($response['wave_launch_url'])) {
                return $this->successResponse(
                    PaymentResponse::STATUS_PENDING,
                    $reference,
                    $response['id'],
                    $amount,
                    'Payment session created successfully',
                    [
                        'checkout_url' => $response['wave_launch_url'],
                        'session_id' => $response['id'],
                        'qr_code' => $response['qr_code'] ?? null,
                    ]
                );
            }

            return $this->failureResponse('Failed to create payment session', $response);

        } catch (\Exception $e) {
            $this->log('error', 'Payment initiation failed', ['error' => $e->getMessage()]);
            return $this->failureResponse($e->getMessage());
        }
    }

    /**
     * Check the status of a transaction.
     */
    public function checkStatus(string $transactionId): PaymentResponse
    {
        try {
            $response = $this->makeRequest('GET', "/v1/checkout/sessions/{$transactionId}", [], [
                'Authorization' => 'Bearer ' . $this->config['api_key'],
            ]);

            $status = $this->mapStatus($response['status'] ?? 'unknown');

            return $this->successResponse(
                $status,
                $transactionId,
                $response['id'] ?? null,
                $response['amount'] ?? null,
                'Transaction status retrieved',
                $response
            );

        } catch (\Exception $e) {
            $this->log('error', 'Status check failed', ['error' => $e->getMessage()]);
            return $this->failureResponse($e->getMessage());
        }
    }

    /**
     * Refund a transaction.
     */
    public function refund(string $transactionId, ?float $amount = null): PaymentResponse
    {
        try {
            $payload = [
                'checkout_session_id' => $transactionId,
            ];

            if ($amount !== null) {
                $payload['amount'] = $amount;
            }

            $response = $this->makeRequest('POST', '/v1/refunds', $payload, [
                'Authorization' => 'Bearer ' . $this->config['api_key'],
            ]);

            if (isset($response['id'])) {
                return $this->successResponse(
                    PaymentResponse::STATUS_REFUNDED,
                    $transactionId,
                    $response['id'],
                    $response['amount'] ?? $amount,
                    'Refund processed successfully',
                    $response
                );
            }

            return $this->failureResponse('Refund failed', $response);

        } catch (\Exception $e) {
            $this->log('error', 'Refund failed', ['error' => $e->getMessage()]);
            return $this->failureResponse($e->getMessage());
        }
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhook(array $payload, string $signature): bool
    {
        $secret = $this->config['api_secret'] ?? config('aida-gateway.webhook.secret');

        // Wave uses HMAC SHA256 for webhook signatures
        $computedSignature = hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * Get default headers for API requests.
     */
    protected function getDefaultHeaders(): array
    {
        return array_merge(parent::getDefaultHeaders(), [
            'Authorization' => 'Bearer ' . $this->config['api_key'],
        ]);
    }

    /**
     * Map Wave status to internal status.
     */
    protected function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'complete', 'completed', 'success' => PaymentResponse::STATUS_SUCCESS,
            'pending', 'open' => PaymentResponse::STATUS_PENDING,
            'failed', 'expired' => PaymentResponse::STATUS_FAILED,
            'cancelled' => PaymentResponse::STATUS_CANCELLED,
            default => PaymentResponse::STATUS_FAILED,
        };
    }
}
