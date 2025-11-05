<?php

namespace Weexduunx\AidaGateway\Gateways;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Weexduunx\AidaGateway\Contracts\GatewayInterface;
use Weexduunx\AidaGateway\PaymentResponse;

abstract class AbstractGateway implements GatewayInterface
{
    protected Client $httpClient;
    protected array $config;
    protected string $gatewayName;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);
    }

    /**
     * Make an HTTP request to the gateway API.
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array
     * @throws \Exception
     */
    protected function makeRequest(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        try {
            $url = rtrim($this->config['api_url'], '/') . '/' . ltrim($endpoint, '/');

            $options = [
                'headers' => array_merge($this->getDefaultHeaders(), $headers),
            ];

            if ($method === 'GET') {
                $options['query'] = $data;
            } else {
                $options['json'] = $data;
            }

            $this->log('info', "Making {$method} request to {$url}", $data);

            $response = $this->httpClient->request($method, $url, $options);
            $body = json_decode($response->getBody()->getContents(), true);

            $this->log('info', 'Request successful', ['response' => $body]);

            return $body;

        } catch (GuzzleException $e) {
            $this->log('error', 'Request failed: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'code' => $e->getCode(),
            ]);

            throw new \Exception("Gateway request failed: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get default headers for API requests.
     *
     * @return array
     */
    protected function getDefaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Log a message if logging is enabled.
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Log context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (config('aida-gateway.logging.enabled', true)) {
            $channel = config('aida-gateway.logging.channel', 'stack');
            Log::channel($channel)->$level("[{$this->gatewayName}] {$message}", $context);
        }
    }

    /**
     * Format phone number to international format.
     *
     * @param string $phoneNumber
     * @return string
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove spaces, dashes, and other non-numeric characters
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);

        // If the number doesn't start with +, add country code
        if (!str_starts_with($phoneNumber, '+')) {
            $countryCode = $this->config['country_code'] ?? '221'; // Default to Senegal
            $phoneNumber = '+' . ltrim($countryCode, '+') . ltrim($phoneNumber, '0');
        }

        return $phoneNumber;
    }

    /**
     * Generate a unique transaction reference.
     *
     * @return string
     */
    protected function generateTransactionReference(): string
    {
        return strtoupper(uniqid($this->gatewayName . '_', true));
    }

    /**
     * Get the gateway name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->gatewayName;
    }

    /**
     * Create a success response.
     *
     * @param string $status
     * @param string|null $transactionId
     * @param string|null $externalId
     * @param float|null $amount
     * @param string|null $message
     * @param array $data
     * @return PaymentResponse
     */
    protected function successResponse(
        string $status,
        ?string $transactionId = null,
        ?string $externalId = null,
        ?float $amount = null,
        ?string $message = null,
        array $data = []
    ): PaymentResponse {
        return new PaymentResponse(
            true,
            $status,
            $transactionId,
            $externalId,
            $amount,
            $this->config['currency'] ?? null,
            $message,
            $data
        );
    }

    /**
     * Create a failure response.
     *
     * @param string $message
     * @param array $data
     * @return PaymentResponse
     */
    protected function failureResponse(string $message, array $data = []): PaymentResponse
    {
        return new PaymentResponse(
            false,
            PaymentResponse::STATUS_FAILED,
            null,
            null,
            null,
            null,
            $message,
            $data
        );
    }
}
