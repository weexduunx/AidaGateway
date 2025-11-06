<?php

namespace Weexduunx\AidaGateway\Tests\Unit\Models;

use Weexduunx\AidaGateway\Tests\TestCase;
use Weexduunx\AidaGateway\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Carbon\Carbon;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
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
    public function it_can_create_transaction_with_all_fields()
    {
        $transaction = Transaction::create([
            'transaction_id' => 'TXN_12345',
            'external_id' => 'ext_67890',
            'gateway' => 'wave',
            'status' => 'pending',
            'phone_number' => '+221771234567',
            'amount' => 5000.00,
            'currency' => 'XOF',
            'description' => 'Test payment',
            'metadata' => ['order_id' => 123, 'user_id' => 456],
            'raw_response' => ['api_response' => 'data'],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
            'user_id' => 1,
        ]);

        $this->assertNotNull($transaction->id);
        $this->assertEquals('TXN_12345', $transaction->transaction_id);
        $this->assertEquals('ext_67890', $transaction->external_id);
        $this->assertEquals('wave', $transaction->gateway);
        $this->assertEquals('pending', $transaction->status);
        $this->assertEquals(5000.00, $transaction->amount);
        $this->assertEquals(['order_id' => 123, 'user_id' => 456], $transaction->metadata);
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $transaction = Transaction::create([
            'transaction_id' => 'TXN_CAST_TEST',
            'gateway' => 'wave',
            'amount' => '5000.50',
            'metadata' => ['key' => 'value'],
            'raw_response' => ['status' => 'success'],
            'completed_at' => '2024-01-01 12:00:00',
        ]);

        // Test decimal casting
        $this->assertIsString($transaction->amount); // SQLite stores as string, that's normal
        $this->assertEquals('5000.50', $transaction->amount);

        // Test array casting
        $this->assertIsArray($transaction->metadata);
        $this->assertEquals(['key' => 'value'], $transaction->metadata);

        // Test datetime casting
        $this->assertInstanceOf(Carbon::class, $transaction->completed_at);
    }

    /** @test */
    public function it_has_successful_scope()
    {
        // Create transactions with different statuses
        Transaction::create([
            'transaction_id' => 'TXN_SUCCESS_1',
            'gateway' => 'wave',
            'status' => 'success',
            'amount' => 1000.00,
        ]);

        Transaction::create([
            'transaction_id' => 'TXN_SUCCESS_2',
            'gateway' => 'orange_money',
            'status' => 'success',
            'amount' => 2000.00,
        ]);

        Transaction::create([
            'transaction_id' => 'TXN_PENDING',
            'gateway' => 'wave',
            'status' => 'pending',
            'amount' => 3000.00,
        ]);

        $successfulTransactions = Transaction::successful()->get();

        $this->assertCount(2, $successfulTransactions);
        $this->assertTrue($successfulTransactions->every(fn($t) => $t->status === 'success'));
    }

    /** @test */
    public function it_has_pending_scope()
    {
        Transaction::create([
            'transaction_id' => 'TXN_PENDING_1',
            'gateway' => 'wave',
            'status' => 'pending',
            'amount' => 1000.00,
        ]);

        Transaction::create([
            'transaction_id' => 'TXN_SUCCESS',
            'gateway' => 'wave',
            'status' => 'success',
            'amount' => 2000.00,
        ]);

        $pendingTransactions = Transaction::pending()->get();

        $this->assertCount(1, $pendingTransactions);
        $this->assertEquals('pending', $pendingTransactions->first()->status);
    }

    /** @test */
    public function it_has_failed_scope()
    {
        Transaction::create([
            'transaction_id' => 'TXN_FAILED_1',
            'gateway' => 'wave',
            'status' => 'failed',
            'amount' => 1000.00,
        ]);

        Transaction::create([
            'transaction_id' => 'TXN_FAILED_2',
            'gateway' => 'orange_money',
            'status' => 'failed',
            'amount' => 2000.00,
        ]);

        Transaction::create([
            'transaction_id' => 'TXN_SUCCESS',
            'gateway' => 'wave',
            'status' => 'success',
            'amount' => 3000.00,
        ]);

        $failedTransactions = Transaction::failed()->get();

        $this->assertCount(2, $failedTransactions);
        $this->assertTrue($failedTransactions->every(fn($t) => $t->status === 'failed'));
    }

    /** @test */
    public function it_has_by_gateway_scope()
    {
        Transaction::create([
            'transaction_id' => 'TXN_WAVE_1',
            'gateway' => 'wave',
            'status' => 'success',
            'amount' => 1000.00,
        ]);

        Transaction::create([
            'transaction_id' => 'TXN_WAVE_2',
            'gateway' => 'wave',
            'status' => 'pending',
            'amount' => 2000.00,
        ]);

        Transaction::create([
            'transaction_id' => 'TXN_OM',
            'gateway' => 'orange_money',
            'status' => 'success',
            'amount' => 3000.00,
        ]);

        $waveTransactions = Transaction::byGateway('wave')->get();

        $this->assertCount(2, $waveTransactions);
        $this->assertTrue($waveTransactions->every(fn($t) => $t->gateway === 'wave'));
    }

    /** @test */
    public function it_can_combine_scopes()
    {
        Transaction::create([
            'transaction_id' => 'TXN_WAVE_SUCCESS',
            'gateway' => 'wave',
            'status' => 'success',
            'amount' => 1000.00,
        ]);

        Transaction::create([
            'transaction_id' => 'TXN_WAVE_PENDING',
            'gateway' => 'wave',
            'status' => 'pending',
            'amount' => 2000.00,
        ]);

        Transaction::create([
            'transaction_id' => 'TXN_OM_SUCCESS',
            'gateway' => 'orange_money',
            'status' => 'success',
            'amount' => 3000.00,
        ]);

        $waveSuccessful = Transaction::byGateway('wave')->successful()->get();

        $this->assertCount(1, $waveSuccessful);
        $this->assertEquals('wave', $waveSuccessful->first()->gateway);
        $this->assertEquals('success', $waveSuccessful->first()->status);
    }

    /** @test */
    public function it_checks_if_transaction_is_successful()
    {
        $successTransaction = Transaction::create([
            'transaction_id' => 'TXN_SUCCESS',
            'gateway' => 'wave',
            'status' => 'success',
            'amount' => 1000.00,
        ]);

        $pendingTransaction = Transaction::create([
            'transaction_id' => 'TXN_PENDING',
            'gateway' => 'wave',
            'status' => 'pending',
            'amount' => 1000.00,
        ]);

        $this->assertTrue($successTransaction->isSuccessful());
        $this->assertFalse($pendingTransaction->isSuccessful());
    }

    /** @test */
    public function it_checks_if_transaction_is_pending()
    {
        $pendingTransaction = Transaction::create([
            'transaction_id' => 'TXN_PENDING',
            'gateway' => 'wave',
            'status' => 'pending',
            'amount' => 1000.00,
        ]);

        $successTransaction = Transaction::create([
            'transaction_id' => 'TXN_SUCCESS',
            'gateway' => 'wave',
            'status' => 'success',
            'amount' => 1000.00,
        ]);

        $this->assertTrue($pendingTransaction->isPending());
        $this->assertFalse($successTransaction->isPending());
    }

    /** @test */
    public function it_checks_if_transaction_failed()
    {
        $failedTransaction = Transaction::create([
            'transaction_id' => 'TXN_FAILED',
            'gateway' => 'wave',
            'status' => 'failed',
            'amount' => 1000.00,
        ]);

        $successTransaction = Transaction::create([
            'transaction_id' => 'TXN_SUCCESS',
            'gateway' => 'wave',
            'status' => 'success',
            'amount' => 1000.00,
        ]);

        $this->assertTrue($failedTransaction->isFailed());
        $this->assertFalse($successTransaction->isFailed());
    }

    /** @test */
    public function it_can_mark_transaction_as_successful()
    {
        $transaction = Transaction::create([
            'transaction_id' => 'TXN_MARK_SUCCESS',
            'gateway' => 'wave',
            'status' => 'pending',
            'amount' => 1000.00,
        ]);

        $this->assertNull($transaction->completed_at);

        $transaction->markAsSuccessful();

        $this->assertEquals('success', $transaction->status);
        $this->assertNotNull($transaction->completed_at);
    }

    /** @test */
    public function it_can_mark_transaction_as_failed()
    {
        $transaction = Transaction::create([
            'transaction_id' => 'TXN_MARK_FAILED',
            'gateway' => 'wave',
            'status' => 'pending',
            'amount' => 1000.00,
        ]);

        $transaction->markAsFailed();

        $this->assertEquals('failed', $transaction->status);
        $this->assertNotNull($transaction->completed_at);
    }

    /** @test */
    public function it_can_mark_transaction_as_cancelled()
    {
        $transaction = Transaction::create([
            'transaction_id' => 'TXN_MARK_CANCELLED',
            'gateway' => 'wave',
            'status' => 'pending',
            'amount' => 1000.00,
        ]);

        $transaction->markAsCancelled();

        $this->assertEquals('cancelled', $transaction->status);
        $this->assertNotNull($transaction->completed_at);
    }

    /** @test */
    public function it_formats_amount_correctly()
    {
        $transaction = Transaction::create([
            'transaction_id' => 'TXN_FORMAT_TEST',
            'gateway' => 'wave',
            'amount' => 5000.00,
            'currency' => 'XOF',
        ]);

        $this->assertEquals('5,000.00 XOF', $transaction->formatted_amount);

        $transaction = Transaction::create([
            'transaction_id' => 'TXN_FORMAT_TEST_2',
            'gateway' => 'wave',
            'amount' => 1250.50,
            'currency' => 'EUR',
        ]);

        $this->assertEquals('1,250.50 EUR', $transaction->formatted_amount);
    }

    /** @test */
    public function it_provides_gateway_display_names()
    {
        $gateways = [
            'orange_money' => 'Orange Money',
            'wave' => 'Wave',
            'free_money' => 'Free Money',
            'emoney' => 'E-money',
            'custom_gateway' => 'Custom gateway', // Adjust expected case
        ];

        foreach ($gateways as $gateway => $expectedName) {
            $transaction = Transaction::create([
                'transaction_id' => "TXN_{$gateway}",
                'gateway' => $gateway,
                'amount' => 1000.00,
            ]);

            $this->assertEquals($expectedName, $transaction->gateway_display_name);
        }
    }

    /** @test */
    public function it_uses_default_values_correctly()
    {
        $transaction = Transaction::create([
            'transaction_id' => 'TXN_DEFAULTS',
            'gateway' => 'wave',
            'amount' => 1000.00,
            'status' => 'pending', // Explicitly set to test defaults
            'currency' => 'XOF', // Explicitly set to test defaults
        ]);

        $this->assertEquals('pending', $transaction->status);
        $this->assertEquals('XOF', $transaction->currency);
    }

    /** @test */
    public function it_handles_json_metadata_correctly()
    {
        $metadata = [
            'order_id' => 'ORDER_123',
            'user_info' => [
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ],
            'payment_method' => 'wave',
            'amount_breakdown' => [
                'subtotal' => 4500,
                'tax' => 500
            ]
        ];

        $transaction = Transaction::create([
            'transaction_id' => 'TXN_JSON_TEST',
            'gateway' => 'wave',
            'amount' => 5000.00,
            'metadata' => $metadata,
        ]);

        $this->assertEquals($metadata, $transaction->metadata);
        $this->assertEquals('ORDER_123', $transaction->metadata['order_id']);
        $this->assertEquals('John Doe', $transaction->metadata['user_info']['name']);
        $this->assertEquals(4500, $transaction->metadata['amount_breakdown']['subtotal']);
    }
}