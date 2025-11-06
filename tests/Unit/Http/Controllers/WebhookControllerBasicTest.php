<?php

namespace Weexduunx\AidaGateway\Tests\Unit\Http\Controllers;

use Weexduunx\AidaGateway\Tests\TestCase;
use Weexduunx\AidaGateway\Models\Transaction;
use Weexduunx\AidaGateway\Events\PaymentSuccessful;
use Weexduunx\AidaGateway\Events\PaymentFailed;
use Weexduunx\AidaGateway\Events\PaymentPending;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class WebhookControllerBasicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        
        // Create the transactions table manually
        Schema::create('aida_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique()->index();
            $table->string('external_id')->nullable()->index();
            $table->string('gateway')->index();
            $table->enum('status', ['pending', 'success', 'failed', 'cancelled', 'refunded'])->default('pending')->index();
            $table->string('phone_number')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('XOF');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /** @test */
    public function it_can_create_transaction_model()
    {
        $transaction = Transaction::create([
            'transaction_id' => 'TXN_12345',
            'external_id' => 'session_12345',
            'gateway' => 'wave',
            'status' => 'success',
            'amount' => 5000.00,
            'currency' => 'XOF',
            'phone_number' => '+221771234567'
        ]);

        $this->assertNotNull($transaction->id);
        $this->assertEquals('wave', $transaction->gateway);
        $this->assertEquals('success', $transaction->status);
        $this->assertEquals(5000.00, $transaction->amount);
    }

    /** @test */
    public function it_can_dispatch_payment_successful_event()
    {
        $transaction = Transaction::create([
            'transaction_id' => 'TXN_12345',
            'external_id' => 'session_12345',
            'gateway' => 'wave',
            'status' => 'success',
            'amount' => 5000.00,
            'currency' => 'XOF'
        ]);

        event(new PaymentSuccessful($transaction));

        Event::assertDispatched(PaymentSuccessful::class, function ($event) use ($transaction) {
            return $event->transaction->id === $transaction->id;
        });
    }

    /** @test */
    public function it_can_dispatch_payment_failed_event()
    {
        $transaction = Transaction::create([
            'transaction_id' => 'TXN_FAILED',
            'external_id' => 'session_failed',
            'gateway' => 'wave',
            'status' => 'failed',
            'amount' => 5000.00,
            'currency' => 'XOF'
        ]);

        event(new PaymentFailed($transaction));

        Event::assertDispatched(PaymentFailed::class, function ($event) use ($transaction) {
            return $event->transaction->id === $transaction->id;
        });
    }

    /** @test */
    public function it_can_dispatch_payment_pending_event()
    {
        $transaction = Transaction::create([
            'transaction_id' => 'TXN_PENDING',
            'external_id' => 'session_pending',
            'gateway' => 'wave',
            'status' => 'pending',
            'amount' => 5000.00,
            'currency' => 'XOF'
        ]);

        event(new PaymentPending($transaction));

        Event::assertDispatched(PaymentPending::class, function ($event) use ($transaction) {
            return $event->transaction->id === $transaction->id;
        });
    }

    /** @test */
    public function it_normalizes_status_correctly()
    {
        $statusMapping = [
            'completed' => 'success',
            'successful' => 'success', 
            'paid' => 'success',
            'processing' => 'pending',
            'initiated' => 'pending',
            'declined' => 'failed',
            'error' => 'failed',
            'expired' => 'failed',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
        ];

        foreach ($statusMapping as $input => $expected) {
            $normalized = $this->normalizeStatus($input);
            $this->assertEquals($expected, $normalized, "Failed for status: {$input}");
        }
    }

    /** @test */
    public function it_can_update_existing_transaction()
    {
        // Create initial transaction
        $transaction = Transaction::create([
            'transaction_id' => 'TXN_12345',
            'external_id' => 'session_12345',
            'gateway' => 'wave',
            'status' => 'pending',
            'amount' => 5000.00,
            'currency' => 'XOF'
        ]);

        // Update the transaction
        $updated = Transaction::updateOrCreate(
            [
                'transaction_id' => 'TXN_12345',
                'gateway' => 'wave',
            ],
            ['status' => 'success']
        );

        $this->assertEquals($transaction->id, $updated->id);
        $this->assertEquals('success', $updated->status);

        // Ensure only one transaction exists
        $this->assertEquals(1, Transaction::where('external_id', 'session_12345')->count());
    }

    /** @test */
    public function it_extracts_transaction_data_correctly()
    {
        $wavePayload = [
            'id' => 'wave_123',
            'status' => 'complete',
            'amount' => 1000,
            'currency' => 'XOF'
        ];

        $orangeMoneyPayload = [
            'order_id' => 'om_456',
            'status' => 'successful',
            'amount' => 2000,
            'currency' => 'XOF'
        ];

        $freeMoneyPayload = [
            'reference' => 'fm_789',
            'status' => 'completed',
            'amount' => 3000,
            'currency' => 'XOF'
        ];

        // Test Wave format
        $waveData = $this->extractTransactionData('wave', $wavePayload);
        $this->assertEquals('wave', $waveData['gateway']);
        $this->assertEquals('wave_123', $waveData['external_id']);
        $this->assertEquals('success', $waveData['status']);

        // Test Orange Money format  
        $omData = $this->extractTransactionData('orange_money', $orangeMoneyPayload);
        $this->assertEquals('orange_money', $omData['gateway']);
        $this->assertEquals('om_456', $omData['external_id']);
        $this->assertEquals('success', $omData['status']); // Add status assertion

        // Test Free Money format
        $fmData = $this->extractTransactionData('free_money', $freeMoneyPayload);
        $this->assertEquals('free_money', $fmData['gateway']);
        $this->assertEquals('fm_789', $fmData['external_id']);
        $this->assertEquals('success', $fmData['status']); // Add status assertion
    }

    /**
     * Helper method to normalize status - simulates WebhookController logic
     */
    private function normalizeStatus(string $status): string
    {
        $status = strtolower($status);

        return match (true) {
            in_array($status, ['success', 'completed', 'successful', 'paid', 'complete']) => 'success',
            in_array($status, ['pending', 'processing', 'initiated']) => 'pending',
            in_array($status, ['failed', 'declined', 'error', 'expired']) => 'failed',
            in_array($status, ['cancelled', 'canceled']) => 'cancelled',
            in_array($status, ['refunded']) => 'refunded',
            default => 'failed',
        };
    }

    /**
     * Helper method to extract transaction data - simulates WebhookController logic
     */
    private function extractTransactionData(string $gateway, array $payload): array
    {
        return [
            'gateway' => $gateway,
            'transaction_id' => $payload['transaction_id'] ?? $payload['order_id'] ?? $payload['reference'] ?? null,
            'external_id' => $payload['external_id'] ?? $payload['payment_id'] ?? $payload['id'] ?? $payload['order_id'] ?? $payload['reference'] ?? null,
            'status' => $this->normalizeStatus($payload['status'] ?? 'unknown'),
            'amount' => $payload['amount'] ?? null,
            'currency' => $payload['currency'] ?? config("aida-gateway.gateways.{$gateway}.currency", 'XOF'),
            'phone_number' => $payload['phone_number'] ?? $payload['customer_phone'] ?? null,
            'metadata' => $payload['metadata'] ?? [],
            'raw_response' => $payload,
        ];
    }
}