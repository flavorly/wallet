<?php

namespace Flavorly\Wallet\Services;

use Flavorly\LaravelHelpers\Helpers\Math\Math;
use Flavorly\Wallet\Contracts\HasWallet as WalletInterface;
use Flavorly\Wallet\Exceptions\NotEnoughBalanceException;
use Flavorly\Wallet\Helpers\MoneyValueFormatter;
use Throwable;

final class BalanceService
{
    /**
     * Temporary cache for the balance as a static variable
     * This is used to avoid multiple queries to the database or cache hits
     * when dealing with the same wallet multiple times in the same request or
     * for a given process lifecycle
     */
    protected null|string|float|int $localCachedRawBalance = null;

    public function __construct(
        public readonly WalletInterface $model,
        public readonly CacheService $cache,
        public readonly ConfigurationService $configuration,
    ) {}

    /**
     * Refresh the balance on database & cache if necessary
     * Also sums all the values from the transactions to get the current balance always calculated
     * This ensures that the balance is always correct based on previous transactions
     *
     * This method also locks before doing any operation to avoid new transactions from
     * being created while the balance is being updated
     *
     * The only exception is when we perform within the transaction itself
     * the cache()->isWithin() will return true if we are currently performing
     * a transaction and so we have already applied a lock inside it.
     */
    protected function refresh(): void
    {
        $closure = function () {
            // Sum all the balance
            $balance = $this->model->transactions()->sum('amount');

            // Cache the balance
            $this->cache->put($balance);

            // Update the balance on database
            // Quietly as we dont need more events on this one.
            $this->model->updateQuietly([
                $this->configuration->getBalanceColumn() => $balance,
            ]);

            // Update the local variable just in case we need to re-use it
            $this->localCachedRawBalance = $balance;
        };

        if ($this->cache->isWithin()) {
            $closure();

            return;
        }

        $this->cache->blockAndWrapInTransaction($closure);
    }

    /**
     * Returns the balance without any formatting or casting
     */
    public function raw(bool $cached = true): int|float|string|null
    {
        if (! $cached) {
            $this->refresh();
        }

        // If we have a local cached balance, return it
        if ($this->localCachedRawBalance !== null) {
            return $this->localCachedRawBalance;
        }

        if ($this->configuration->getBalanceCachedOnModel()) {
            $this->localCachedRawBalance = $this->configuration->getBalanceCachedOnModel();
            $this->cache->put($this->localCachedRawBalance);
        }

        return $this->localCachedRawBalance ?? '0';
    }

    /**
     * Returns the balance as a MoneyValueFormatter instance
     */
    public function toFormatter(): MoneyValueFormatter
    {
        return new MoneyValueFormatter(
            $this->raw() ?? 0,
            ConfigurationService::getDecimals(),
            ConfigurationService::getCurrency(),
        );
    }

    /**
     * Returns the current balance as a string/float representation
     * Optional can be the cached balance or the actual balance calculated
     */
    public function get(bool $cached = true): Math
    {
        return (new MoneyValueFormatter(
            $this->raw($cached) ?? 0,
            ConfigurationService::getDecimals(),
            ConfigurationService::getCurrency(),
        ))->toNumber();
    }

    /**
     * Check if the wallet/model has enough balance for the given amount
     */
    public function hasEnoughFor(float|int|string $amount): bool
    {
        try {
            (new OperationService(
                credit: false,
                model: $this->model,
                cache: $this->cache,
                configuration: $this->configuration,
                balance: $this,
            ))
                ->debit($amount)
                ->throw(false)
                ->pretend()
                ->dispatch();

            return true;
        } catch (NotEnoughBalanceException $e) {
            return false;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
