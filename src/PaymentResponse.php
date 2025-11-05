<?php

namespace Weexduunx\AidaGateway;

use JsonSerializable;

class PaymentResponse implements JsonSerializable
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected bool $success;
    protected string $status;
    protected ?string $transactionId;
    protected ?string $externalId;
    protected ?float $amount;
    protected ?string $currency;
    protected ?string $message;
    protected array $data;

    public function __construct(
        bool $success,
        string $status,
        ?string $transactionId = null,
        ?string $externalId = null,
        ?float $amount = null,
        ?string $currency = null,
        ?string $message = null,
        array $data = []
    ) {
        $this->success = $success;
        $this->status = $status;
        $this->transactionId = $transactionId;
        $this->externalId = $externalId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->message = $message;
        $this->data = $data;
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'transaction_id' => $this->transactionId,
            'external_id' => $this->externalId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return json_encode($this->toArray());
    }
}
