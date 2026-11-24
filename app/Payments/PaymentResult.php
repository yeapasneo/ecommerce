<?php

namespace App\Payments;

final class PaymentResult
{
    private function __construct(
        public readonly string $status, // paid | failed | cancelled
        public readonly ?string $transactionId = null,
        public readonly ?string $reference = null,
        public readonly ?string $message = null,
    ) {}

    public static function paid(?string $transactionId, ?string $reference = null): self
    {
        return new self('paid', $transactionId, $reference);
    }

    public static function failed(string $message = 'The payment was not completed.'): self
    {
        return new self('failed', message: $message);
    }

    public static function cancelled(): self
    {
        return new self('cancelled', message: 'The payment was cancelled.');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
