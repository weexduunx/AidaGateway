<?php

namespace Weexduunx\AidaGateway\Gateways;

use Weexduunx\AidaGateway\PaymentResponse;

class OrangeMoneyGateway extends AbstractGateway
{
    protected string $gatewayName = 'orange_money';

    /**
     * Initialize a payment transaction.
     */
    public function pay(string $phoneNumber, float $amount, string $description = '', array $metadata = []): PaymentResponse
    {
        try {
            $phoneNumber = $this->formatPhoneNumber($phoneNumber);
            $reference = $this->generateTransactionReference();

            $payload = [
                'merchant_key' => $this->config['merchant_key'],
                'currency' => $this->config['currency'],
                'order_id' => $reference,
                'amount' => $amount,
                'return_url' => $metadata['return_url'] ?? config('app.url') . '/payment/return',
                'cancel_url' => $metadata['cancel_url'] ?? config('app.url') . '/payment/cancel',
                'notif_url' => config('app.url') . '/' . config('aida-gateway.webhook.route_prefix') . '/orange-money',
                'lang' => $metadata['lang'] ?? 'fr',
                'reference' => $phoneNumber,
            ];

            $response = $this->makeRequest('POST', '/webpayment/v3/pay', $payload, [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
            ]);

            if (isset($response['payment_url'])) {
                return $this->successResponse(
                    PaymentResponse::STATUS_PENDING,
                    $reference,
                    $response['payment_token'] ?? null,
                    $amount,
                    'Payment initiated successfully. Customer should complete payment.',
                    [
                        'payment_url' => $response['payment_url'],
                        'payment_token' => $response['payment_token'] ?? null,
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
            $response = $this->makeRequest('GET', "/webpayment/v3/transaction/{$transactionId}", [], [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
            ]);

            $status = $this->mapStatus($response['status'] ?? 'unknown');

            return $this->successResponse(
                $status,
                $transactionId,
                $response['payment_token'] ?? null,
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
                'order_id' => $transactionId,
            ];

            if ($amount !== null) {
                $payload['amount'] = $amount;
            }

            $response = $this->makeRequest('POST', '/webpayment/v3/refund', $payload, [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
            ]);

            if (isset($response['status']) && $response['status'] === 'SUCCESS') {
                return $this->successResponse(
                    PaymentResponse::STATUS_REFUNDED,
                    $transactionId,
                    $response['refund_id'] ?? null,
                    $amount,
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
        $secret = config('aida-gateway.webhook.secret');
        $computedSignature = hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * Get OAuth access token for API authentication.
     */
    protected function getAccessToken(): string
    {
        // Cache the token to avoid requesting it on every API call
        $cacheKey = "aida_gateway_orange_money_token";

        if ($token = cache($cacheKey)) {
            return $token;
        }

        try {
            $credentials = base64_encode(
                $this->config['api_username'] . ':' . $this->config['api_password']
            );

            $response = $this->makeRequest('POST', '/oauth/v3/token', [
                'grant_type' => 'client_credentials',
            ], [
                'Authorization' => 'Basic ' . $credentials,
            ]);

            $token = $response['access_token'] ?? null;
            $expiresIn = $response['expires_in'] ?? 3600;

            if ($token) {
                cache([$cacheKey => $token], now()->addSeconds($expiresIn - 60));
                return $token;
            }

            throw new \Exception('Failed to obtain access token');

        } catch (\Exception $e) {
            $this->log('error', 'Token retrieval failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Map Orange Money status to internal status.
     */
    protected function mapStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'SUCCESS', 'SUCCESSFUL', 'COMPLETED' => PaymentResponse::STATUS_SUCCESS,
            'PENDING', 'INITIATED' => PaymentResponse::STATUS_PENDING,
            'FAILED', 'EXPIRED' => PaymentResponse::STATUS_FAILED,
            'CANCELLED' => PaymentResponse::STATUS_CANCELLED,
            default => PaymentResponse::STATUS_FAILED,
        };
    }
}
