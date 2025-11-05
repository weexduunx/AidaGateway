<?php

namespace Weexduunx\AidaGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Weexduunx\AidaGateway\Facades\Aida;
use Weexduunx\AidaGateway\Models\Transaction;
use Weexduunx\AidaGateway\Events\PaymentSuccessful;
use Weexduunx\AidaGateway\Events\PaymentFailed;
use Weexduunx\AidaGateway\Events\PaymentPending;

class WebhookController extends Controller
{
    /**
     * Handle Orange Money webhook.
     */
    public function orangeMoney(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'orange_money');
    }

    /**
     * Handle Wave webhook.
     */
    public function wave(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'wave');
    }

    /**
     * Handle Free Money webhook.
     */
    public function freeMoney(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'free_money');
    }

    /**
     * Handle E-money webhook.
     */
    public function emoney(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'emoney');
    }

    /**
     * Generic webhook handler.
     */
    protected function handleWebhook(Request $request, string $gateway): JsonResponse
    {
        try {
            $payload = $request->all();
            $signature = $request->header('X-Signature') ?? $request->header('Signature') ?? '';

            // Log incoming webhook
            $this->logWebhook($gateway, $payload);

            // Verify webhook signature
            if (!$this->verifyWebhookSignature($gateway, $payload, $signature)) {
                Log::warning("Invalid webhook signature for {$gateway}", ['payload' => $payload]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Extract transaction data from payload
            $transactionData = $this->extractTransactionData($gateway, $payload);

            // Update or create transaction
            $transaction = $this->updateTransaction($transactionData);

            // Dispatch events based on status
            $this->dispatchEvents($transaction);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error("Webhook processing failed for {$gateway}: " . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Webhook processing failed',
            ], 500);
        }
    }

    /**
     * Verify webhook signature.
     */
    protected function verifyWebhookSignature(string $gateway, array $payload, string $signature): bool
    {
        try {
            return Aida::gateway($gateway)->verifyWebhook($payload, $signature);
        } catch (\Exception $e) {
            Log::error("Signature verification failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract transaction data from webhook payload.
     */
    protected function extractTransactionData(string $gateway, array $payload): array
    {
        // This is a generic implementation. Each gateway may have different payload structure.
        return [
            'gateway' => $gateway,
            'transaction_id' => $payload['transaction_id'] ?? $payload['order_id'] ?? $payload['reference'] ?? null,
            'external_id' => $payload['external_id'] ?? $payload['payment_id'] ?? $payload['id'] ?? null,
            'status' => $this->normalizeStatus($payload['status'] ?? 'unknown'),
            'amount' => $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? config("aida-gateway.gateways.{$gateway}.currency"),
            'phone_number' => $payload['phone_number'] ?? $payload['customer_phone'] ?? null,
            'metadata' => $payload['metadata'] ?? [],
            'raw_response' => $payload,
        ];
    }

    /**
     * Update or create transaction record.
     */
    protected function updateTransaction(array $data): Transaction
    {
        return Transaction::updateOrCreate(
            [
                'transaction_id' => $data['transaction_id'],
                'gateway' => $data['gateway'],
            ],
            $data
        );
    }

    /**
     * Dispatch events based on transaction status.
     */
    protected function dispatchEvents(Transaction $transaction): void
    {
        match ($transaction->status) {
            'success' => event(new PaymentSuccessful($transaction)),
            'failed' => event(new PaymentFailed($transaction)),
            'pending' => event(new PaymentPending($transaction)),
            default => null,
        };
    }

    /**
     * Normalize status from different gateways.
     */
    protected function normalizeStatus(string $status): string
    {
        $status = strtolower($status);

        return match (true) {
            in_array($status, ['success', 'completed', 'successful', 'paid']) => 'success',
            in_array($status, ['pending', 'processing', 'initiated']) => 'pending',
            in_array($status, ['failed', 'declined', 'error', 'expired']) => 'failed',
            in_array($status, ['cancelled', 'canceled']) => 'cancelled',
            in_array($status, ['refunded']) => 'refunded',
            default => 'failed',
        };
    }

    /**
     * Log webhook data.
     */
    protected function logWebhook(string $gateway, array $payload): void
    {
        if (config('aida-gateway.logging.enabled', true)) {
            Log::channel(config('aida-gateway.logging.channel', 'stack'))
                ->info("Webhook received from {$gateway}", [
                    'gateway' => $gateway,
                    'payload' => $payload,
                ]);
        }
    }
}
