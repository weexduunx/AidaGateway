<?php

namespace Weexduunx\AidaGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SecureTransaction
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verify the request contains required transaction parameters
        $this->validateTransactionParameters($request);

        // Check for duplicate transaction attempts
        if ($this->isDuplicateTransaction($request)) {
            Log::warning('Duplicate transaction attempt detected', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'transaction_data' => $request->only(['phone_number', 'amount']),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Duplicate transaction detected. Please wait before retrying.',
            ], 429);
        }

        // Rate limiting per IP address
        if ($this->isRateLimitExceeded($request)) {
            Log::warning('Rate limit exceeded for transaction request', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Too many transaction requests. Please try again later.',
            ], 429);
        }

        // Validate transaction amount
        if (!$this->isValidAmount($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid transaction amount.',
            ], 400);
        }

        // Log transaction attempt
        $this->logTransactionAttempt($request);

        // Mark transaction as attempted (for duplicate detection)
        $this->markTransactionAttempt($request);

        return $next($request);
    }

    /**
     * Validate required transaction parameters.
     */
    protected function validateTransactionParameters(Request $request): void
    {
        $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);
    }

    /**
     * Check if this is a duplicate transaction.
     */
    protected function isDuplicateTransaction(Request $request): bool
    {
        $cacheKey = $this->getTransactionCacheKey($request);
        return cache()->has($cacheKey);
    }

    /**
     * Mark transaction as attempted.
     */
    protected function markTransactionAttempt(Request $request): void
    {
        $cacheKey = $this->getTransactionCacheKey($request);
        $timeout = config('aida-gateway.transaction.timeout', 300); // 5 minutes default

        cache()->put($cacheKey, true, now()->addSeconds($timeout));
    }

    /**
     * Get cache key for transaction.
     */
    protected function getTransactionCacheKey(Request $request): string
    {
        $phoneNumber = $request->input('phone_number');
        $amount = $request->input('amount');
        $userId = $request->user()?->id ?? 'guest';

        return "aida_transaction_{$userId}_{$phoneNumber}_{$amount}";
    }

    /**
     * Check if rate limit is exceeded.
     */
    protected function isRateLimitExceeded(Request $request): bool
    {
        $key = 'aida_rate_limit_' . $request->ip();
        $maxAttempts = 10; // Maximum 10 attempts
        $decayMinutes = 10; // Per 10 minutes

        $attempts = cache()->get($key, 0);

        if ($attempts >= $maxAttempts) {
            return true;
        }

        cache()->put($key, $attempts + 1, now()->addMinutes($decayMinutes));

        return false;
    }

    /**
     * Validate transaction amount.
     */
    protected function isValidAmount(Request $request): bool
    {
        $amount = $request->input('amount');

        if (!is_numeric($amount) || $amount <= 0) {
            return false;
        }

        // Optional: Add maximum amount limit
        $maxAmount = config('aida-gateway.transaction.max_amount', 10000000);
        if ($amount > $maxAmount) {
            return false;
        }

        return true;
    }

    /**
     * Log transaction attempt.
     */
    protected function logTransactionAttempt(Request $request): void
    {
        if (config('aida-gateway.logging.enabled', true)) {
            Log::channel(config('aida-gateway.logging.channel', 'stack'))
                ->info('Transaction attempt', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'phone_number' => $request->input('phone_number'),
                    'amount' => $request->input('amount'),
                    'gateway' => $request->input('gateway'),
                ]);
        }
    }
}
