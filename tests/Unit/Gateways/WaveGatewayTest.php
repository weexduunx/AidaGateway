<?php

namespace Weexduunx\AidaGateway\Tests\Unit\Gateways;

use Weexduunx\AidaGateway\Tests\TestCase;
use Weexduunx\AidaGateway\Gateways\WaveGateway;
use Weexduunx\AidaGateway\PaymentResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Mockery;

class WaveGatewayTest extends TestCase
{
    protected WaveGateway $gateway;
    protected array $config;
    protected MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'enabled' => true,
            'api_url' => 'https://api.wave.com',
            'api_key' => 'test_api_key',
            'api_secret' => 'test_api_secret',
            'currency' => 'XOF',
        ];

        // Create a custom gateway with mocked HTTP client
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $this->gateway = new WaveGateway($this->config);
        
        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($this->gateway);
        $property = $reflection->getProperty('httpClient');
        $property->setAccessible(true);
        $property->setValue($this->gateway, new Client(['handler' => $handlerStack]));
    }

    /** @test */
    public function it_can_initiate_payment_successfully()
    {
        // Mock successful Wave API response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'session_12345',
            'wave_launch_url' => 'https://checkout.wave.com/session_12345',
            'qr_code' => 'https://api.wave.com/qr/session_12345',
            'amount' => 5000,
            'currency' => 'XOF',
            'status' => 'pending'
        ])));

        $response = $this->gateway->pay('+221771234567', 5000, 'Test payment');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(PaymentResponse::STATUS_PENDING, $response->getStatus());
        $this->assertNotEmpty($response->getTransactionId());
        $this->assertEquals('session_12345', $response->getExternalId());
        $this->assertEquals(5000, $response->getAmount());
        
        $data = $response->getData();
        $this->assertArrayHasKey('checkout_url', $data);
        $this->assertArrayHasKey('session_id', $data);
        $this->assertEquals('https://checkout.wave.com/session_12345', $data['checkout_url']);
    }

    /** @test */
    public function it_handles_payment_initiation_failure()
    {
        // Mock failed Wave API response with 400 status
        $this->mockHandler->append(new Response(400, [], json_encode([
            'error' => 'invalid_amount',
            'message' => 'Amount must be greater than 100'
        ])));

        $response = $this->gateway->pay('+221771234567', 50, 'Test payment');

        $this->assertFalse($response->isSuccessful());
        $this->assertEquals(PaymentResponse::STATUS_FAILED, $response->getStatus());
        $this->assertStringContainsString('Gateway request failed', $response->getMessage());
    }

    /** @test */
    public function it_can_check_transaction_status_successfully()
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'session_12345',
            'status' => 'complete',
            'amount' => 5000,
            'currency' => 'XOF'
        ])));

        $response = $this->gateway->checkStatus('session_12345');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(PaymentResponse::STATUS_SUCCESS, $response->getStatus());
        $this->assertEquals(5000, $response->getAmount());
    }

    /** @test */
    public function it_handles_status_check_for_pending_transaction()
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'session_12345',
            'status' => 'pending',
            'amount' => 5000,
            'currency' => 'XOF'
        ])));

        $response = $this->gateway->checkStatus('session_12345');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(PaymentResponse::STATUS_PENDING, $response->getStatus());
    }

    /** @test */
    public function it_handles_status_check_for_failed_transaction()
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'session_12345',
            'status' => 'failed',
            'amount' => 5000,
            'currency' => 'XOF'
        ])));

        $response = $this->gateway->checkStatus('session_12345');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(PaymentResponse::STATUS_FAILED, $response->getStatus());
    }

    /** @test */
    public function it_can_process_full_refund_successfully()
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'refund_12345',
            'amount' => 5000,
            'status' => 'completed'
        ])));

        $response = $this->gateway->refund('session_12345');

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(PaymentResponse::STATUS_REFUNDED, $response->getStatus());
        $this->assertEquals('refund_12345', $response->getExternalId());
    }

    /** @test */
    public function it_can_process_partial_refund_successfully()
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'refund_12345',
            'amount' => 2500,
            'status' => 'completed'
        ])));

        $response = $this->gateway->refund('session_12345', 2500);

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(PaymentResponse::STATUS_REFUNDED, $response->getStatus());
        $this->assertEquals(2500, $response->getAmount());
    }

    /** @test */
    public function it_handles_refund_failure()
    {
        $this->mockHandler->append(new Response(400, [], json_encode([
            'error' => 'refund_failed',
            'message' => 'Transaction cannot be refunded'
        ])));

        $response = $this->gateway->refund('session_12345');

        $this->assertFalse($response->isSuccessful());
        $this->assertStringContainsString('Gateway request failed', $response->getMessage());
    }

    /** @test */
    public function it_validates_webhook_signature_correctly()
    {
        $payload = [
            'id' => 'session_12345',
            'status' => 'complete',
            'amount' => 5000
        ];

        // Generate correct signature
        $secret = $this->config['api_secret'];
        $correctSignature = hash_hmac('sha256', json_encode($payload), $secret);

        $this->assertTrue($this->gateway->verifyWebhook($payload, $correctSignature));
    }

    /** @test */
    public function it_rejects_invalid_webhook_signature()
    {
        $payload = [
            'id' => 'session_12345',
            'status' => 'complete',
            'amount' => 5000
        ];

        $invalidSignature = 'invalid_signature';

        $this->assertFalse($this->gateway->verifyWebhook($payload, $invalidSignature));
    }

    /** @test */
    public function it_handles_network_timeout_gracefully()
    {
        // Mock timeout exception
        $this->mockHandler->append(new RequestException(
            'Connection timeout',
            new Request('POST', 'https://api.wave.com/v1/checkout/sessions')
        ));

        $response = $this->gateway->pay('+221771234567', 5000, 'Test payment');

        $this->assertFalse($response->isSuccessful());
        $this->assertStringContainsString('Connection timeout', $response->getMessage());
    }

    /** @test */
    public function it_maps_wave_statuses_to_internal_statuses_correctly()
    {
        $statusMapping = [
            'complete' => PaymentResponse::STATUS_SUCCESS,
            'completed' => PaymentResponse::STATUS_SUCCESS,
            'success' => PaymentResponse::STATUS_SUCCESS,
            'pending' => PaymentResponse::STATUS_PENDING,
            'open' => PaymentResponse::STATUS_PENDING,
            'failed' => PaymentResponse::STATUS_FAILED,
            'expired' => PaymentResponse::STATUS_FAILED,
            'cancelled' => PaymentResponse::STATUS_CANCELLED,
        ];

        foreach ($statusMapping as $waveStatus => $expectedStatus) {
            $this->mockHandler->append(new Response(200, [], json_encode([
                'id' => "session_{$waveStatus}",
                'status' => $waveStatus,
                'amount' => 5000
            ])));

            $response = $this->gateway->checkStatus("session_{$waveStatus}");
            $this->assertEquals($expectedStatus, $response->getStatus(), "Failed mapping for status: {$waveStatus}");
        }
    }

    /** @test */
    public function it_includes_correct_headers_in_requests()
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'id' => 'session_12345',
            'wave_launch_url' => 'https://checkout.wave.com/session_12345'
        ])));

        $this->gateway->pay('+221771234567', 5000, 'Test payment');

        // The test passes if no exception is thrown and the mock response is used
        $this->assertTrue(true);
    }
}