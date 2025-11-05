<?php

namespace Weexduunx\AidaGateway\Gateways;

use Weexduunx\AidaGateway\PaymentResponse;

class EmoneyGateway extends AbstractGateway
{
    protected string $gatewayName = 'emoney';

    /**
     * Initialize a payment transaction.
     */
    public function pay(string $phoneNumber, float $amount, string $description = '', array $metadata = []): PaymentResponse
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
            $reference = $this->generateTransactionReference();

            $payload = [
                'merchant_code' => $this->config['merchant_code'],
                'amount' => $amount,
                'currency' => $this->config['currency'],
                'reference' => $reference,
                'customer_phone' => $phoneNumber,
                'description' => $description ?: 'Payment via E-money',
                'notification_url' => config('app.url') . '/' . config('aida-gateway.webhook.route_prefix') . '/emoney',
                'return_url' => $metadata['return_url'] ?? config('app.url') . '/payment/return',
                'metadata' => json_encode($metadata),
            ];

            $response = $this->makeRequest('POST', '/api/payment/initiate', $payload, [
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'X-Merchant-Code' => $this->config['merchant_code'],
            ]);

            if (isset($response['payment_id']) && isset($response['status'])) {
                $status = $this->mapStatus($response['status']);

                return $this->successResponse(
                    $status,
                    $reference,
                    $response['payment_id'],
                    $amount,
                    $response['message'] ?? 'Payment initiated successfully',
                    [
                        'payment_id' => $response['payment_id'],
                        'payment_url' => $response['payment_url'] ?? null,
                        'qr_code' => $response['qr_code'] ?? null,
                    ]
                );
            }

            return $this->failureResponse('Failed to initiate payment', $response);

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
            $response = $this->makeRequest('GET', "/api/payment/status/{$transactionId}", [], [
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'X-Merchant-Code' => $this->config['merchant_code'],
            ]);

            $status = $this->mapStatus($response['status'] ?? 'unknown');

            return $this->successResponse(
                $status,
                $transactionId,
                $response['payment_id'] ?? null,
                $response['amount'] ?? null,
                $response['message'] ?? 'Transaction status retrieved',
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
                'merchant_code' => $this->config['merchant_code'],
                'payment_id' => $transactionId,
                'refund_reason' => 'Customer refund request',
            ];

            if ($amount !== null) {
                $payload['amount'] = $amount;
            }

            $response = $this->makeRequest('POST', '/api/payment/refund', $payload, [
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'X-Merchant-Code' => $this->config['merchant_code'],
            ]);

            if (isset($response['refund_id']) && $response['status'] === 'success') {
                return $this->successResponse(
                    PaymentResponse::STATUS_REFUNDED,
                    $transactionId,
                    $response['refund_id'],
                    $response['refund_amount'] ?? $amount,
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
        $secret = $this->config['api_secret'];

        // E-money uses SHA256 HMAC for webhook signatures
        $payloadString = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $computedSignature = hash_hmac('sha256', $payloadString, $secret);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * Get default headers for API requests.
     */
    protected function getDefaultHeaders(): array
    {
        return array_merge(parent::getDefaultHeaders(), [
            'X-Merchant-Code' => $this->config['merchant_code'],
        ]);
    }

    /**
     * Map E-money status to internal status.
     */
    protected function mapStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'SUCCESS', 'COMPLETED', 'VALIDATED' => PaymentResponse::STATUS_SUCCESS,
            'PENDING', 'PROCESSING', 'INITIATED' => PaymentResponse::STATUS_PENDING,
            'FAILED', 'DECLINED', 'ERROR', 'EXPIRED' => PaymentResponse::STATUS_FAILED,
            'CANCELLED', 'CANCELED' => PaymentResponse::STATUS_CANCELLED,
            'REFUNDED' => PaymentResponse::STATUS_REFUNDED,
            default => PaymentResponse::STATUS_FAILED,
        };
    }
}
