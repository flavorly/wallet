<?php

namespace Flavorly\Wallet\Enums;

enum TransactionType
{
    case CREDIT;
    case DEBIT;

    public function value(): string
    {
        return match ($this) {
            TransactionType::CREDIT => 'credit',
            TransactionType::DEBIT => 'debit',
        };
    }

    public function isCredit(): bool
    {
        return $this === TransactionType::CREDIT;
    }

    public function isDebit(): bool
    {
        return $this === TransactionType::DEBIT;
    }
}
