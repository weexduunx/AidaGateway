<?php

namespace Weexduunx\AidaGateway\Gateways;

use Weexduunx\AidaGateway\PaymentResponse;

class FreeMoneyGateway extends AbstractGateway
{
    protected string $gatewayName = 'free_money';

    /**
     * Initialize a payment transaction.
     */
    public function pay(string $phoneNumber, float $amount, string $description = '', array $metadata = []): PaymentResponse
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
            $reference = $this->generateTransactionReference();

            $payload = [
                'merchant_id' => $this->config['merchant_id'],
                'amount' => $amount,
                'currency' => $this->config['currency'],
                'transaction_ref' => $reference,
                'phone_number' => $phoneNumber,
                'description' => $description ?: 'Payment via Free Money',
                'callback_url' => config('app.url') . '/' . config('aida-gateway.webhook.route_prefix') . '/free-money',
                'metadata' => $metadata,
            ];

            $signature = $this->generateSignature($payload);

            $response = $this->makeRequest('POST', '/api/v1/payments/initiate', $payload, [
                'X-API-Key' => $this->config['api_key'],
                'X-Signature' => $signature,
            ]);

            if (isset($response['transaction_id']) && $response['status'] === 'pending') {
                return $this->successResponse(
                    PaymentResponse::STATUS_PENDING,
                    $reference,
                    $response['transaction_id'],
                    $amount,
                    'Payment initiated successfully',
                    [
                        'transaction_id' => $response['transaction_id'],
                        'ussd_code' => $response['ussd_code'] ?? null,
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
            $response = $this->makeRequest('GET', "/api/v1/payments/{$transactionId}", [], [
                'X-API-Key' => $this->config['api_key'],
            ]);

            $status = $this->mapStatus($response['status'] ?? 'unknown');

            return $this->successResponse(
                $status,
                $transactionId,
                $response['transaction_id'] ?? null,
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
                'transaction_id' => $transactionId,
                'merchant_id' => $this->config['merchant_id'],
            ];

            if ($amount !== null) {
                $payload['amount'] = $amount;
            }

            $signature = $this->generateSignature($payload);

            $response = $this->makeRequest('POST', '/api/v1/payments/refund', $payload, [
                'X-API-Key' => $this->config['api_key'],
                'X-Signature' => $signature,
            ]);

            if (isset($response['refund_id']) && $response['status'] === 'success') {
                return $this->successResponse(
                    PaymentResponse::STATUS_REFUNDED,
                    $transactionId,
                    $response['refund_id'],
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
        $computedSignature = $this->generateSignature($payload);
        return hash_equals($computedSignature, $signature);
    }

    /**
     * Generate signature for API requests.
     */
    protected function generateSignature(array $data): string
    {
        $secret = $this->config['api_secret'];

        // Sort array by keys
        ksort($data);

        // Create string from array
        $stringToSign = http_build_query($data);

        // Generate HMAC SHA256 signature
        return hash_hmac('sha256', $stringToSign, $secret);
    }

    /**
     * Map Free Money status to internal status.
     */
    protected function mapStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success', 'completed', 'paid' => PaymentResponse::STATUS_SUCCESS,
            'pending', 'processing' => PaymentResponse::STATUS_PENDING,
            'failed', 'declined', 'expired' => PaymentResponse::STATUS_FAILED,
            'cancelled', 'canceled' => PaymentResponse::STATUS_CANCELLED,
            default => PaymentResponse::STATUS_FAILED,
        };
    }
}
