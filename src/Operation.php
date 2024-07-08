<?php

namespace Flavorly\Wallet;

use Brick\Math\Exception\MathException;
use Closure;
use Exception;
use Flavorly\LaravelHelpers\Helpers\Math\Math;
use Flavorly\Wallet\Concerns\EvaluatesClosures;
use Flavorly\Wallet\Enums\TransactionType;
use Flavorly\Wallet\Events\TransactionCreatedEvent;
use Flavorly\Wallet\Events\TransactionCreditEvent;
use Flavorly\Wallet\Events\TransactionDebitEvent;
use Flavorly\Wallet\Events\TransactionFailedEvent;
use Flavorly\Wallet\Events\TransactionFinishedEvent;
use Flavorly\Wallet\Events\TransactionStartedEvent;
use Flavorly\Wallet\Exceptions\InvalidOperationArgumentsException;
use Flavorly\Wallet\Exceptions\NotEnoughBalanceException;
use Flavorly\Wallet\Exceptions\WalletLockedException;
use Flavorly\Wallet\Models\Transaction;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Str;
use Throwable;

final class Operation
{
    use EvaluatesClosures;

    /**
     * Stores the main callbacks
     *
     * @var array<callable>
     */
    protected array $callbacks = [];

    /**
     * Stores the callbacks to run before
     *
     * @var array<callable>
     */
    protected array $beforeCallbacks = [];

    /**
     * Stores the callbacks to run after
     *
     * @var array<callable>
     */
    protected array $afterCallbacks = [];

    /**
     * The type of transaction debit/credit
     */
    protected TransactionType $type;

    /**
     * Store the amount of teh transaction
     * If the type is debit it will be casted to a negative value
     */
    protected int|float|string $amount;

    /**
     * Additional Meta information to store along the transaction
     *
     * @var array<string,mixed>
     */
    protected array $meta = [];

    /**
     * Only process the transaction if the value is true
     */
    protected bool $shouldContinue = true;

    /**
     * If an exception should be triggered if something fails
     */
    protected bool $shouldThrow = true;

    /**
     * If balance should be refreshed after the transaction
     */
    protected bool $shouldRefreshBalance = true;

    /**
     * Stores the endpoint
     */
    protected ?string $endpoint = null;

    /**
     * How much times to retry before failing
     */
    protected int $retry = 1;

    /**
     * Retry Delay before retrying again
     */
    protected int $retryDelay = 1000;

    /**
     * Stores if the transaction is currently being processed
     */
    protected bool $processing = false;

    /**
     * Main service interface that contains the wallet data
     */
    protected Wallet $wallet;

    /**
     * Stores the transaction if it was successful
     */
    protected ?Transaction $transaction = null;

    public function __construct(Wallet $service, TransactionType $type)
    {
        $this->wallet = $service;
        $this->type = $type;
    }

    /**
     * If the transaction was successful
     */
    public function ok(): bool
    {
        return $this->transaction !== null;
    }

    /**
     * If the transaction failed
     */
    public function failed(): bool
    {
        return ! $this->ok();
    }

    /**
     * Get the underlying transaction
     */
    public function transaction(): ?Transaction
    {
        return $this->transaction;
    }

    /**
     * If the transaction should continue
     */
    protected function shouldContinue(): bool
    {
        if (! $this->shouldContinue) {
            return false;
        }

        return true;
    }

    /**
     * @throws InvalidOperationArgumentsException
     * @throws NotEnoughBalanceException
     * @throws MathException
     */
    protected function ensureWeCanDispatch(): void
    {
        if ($this->processing) {
            throw new InvalidOperationArgumentsException('Transaction is already being processed');
        }

        if (Math::of(
            $this->amount,
            $this->wallet->configuration()->getDecimals(),
            $this->wallet->configuration()->getDecimals(),
        )->isZero()) {
            throw new InvalidOperationArgumentsException('Amount cannot be zero');
        }

        if (! $this->hasEnoughBalanceOperation()) {
            throw new NotEnoughBalanceException('Not enough balance');
        }
    }

    /**
     * This is the main method that will run the transaction
     * and also process the callbacks and everything related to the transaction itself
     *
     * @return $this
     *
     * @throws Throwable
     * @throws WalletLockedException
     */
    public function dispatch(): Operation
    {
        // Ensure everything is set
        $this->ensureWeCanDispatch();

        // Ensure we don't go further
        if (! $this->shouldContinue()) {
            return $this;
        }

        // Compile default callbacks
        $this->compileDefaultCallbacks();

        try {
            // Retry, by default 1, so should only retry once
            retry(
                $this->retry,
                fn () => $this->dispatchAndBlock(function () {
                    $this->processBeforeCallbacks();
                    $this->processMainCallbacks();
                    $this->processAfterCallbacks();
                }),
                $this->retryDelay,
            );
        } catch (Throwable $e) {
            // Dispatch the failed event
            event(new TransactionFailedEvent(
                $this->wallet->model,
                $this->type,
                $this->amount,
                $e
            ));

            // If we should throw, throw the exception
            if ($this->shouldThrow) {
                throw $e;
            }
        } finally {
            // Set the processing to false
            //$this->processing = false;
            event(new TransactionFinishedEvent(
                $this->wallet->model,
                $this->type,
                $this->amount,
                $this->transaction
            ));
        }

        return $this;
    }

    /**
     * This is also the main function, but takes only a callback
     * and also Blocks & wrap the transaction in a database transaction
     *
     * This so we can have a cleaner code within the dispatch method
     *
     * @throws WalletLockedException
     */
    protected function dispatchAndBlock(callable $callback): void
    {
        $this->processing = true;

        // If the resource is locked, throw an exception instantly
        if ($this->wallet->cache()->locked()) {
            throw new WalletLockedException(
                sprintf(
                    'Resource is locked on Model : %s with Key: %s',
                    $this->wallet->configuration()->getClass(),
                    $this->wallet->configuration()->getPrimaryKey()
                ),
            );
        }

        try {
            $this
                ->wallet
                ->cache()
                ->blockAndWrapInTransaction($callback);
        } catch (LockTimeoutException $e) {
            throw new WalletLockedException(
                sprintf(
                    'Resource is locked on Model : %s with Key: %s',
                    $this->wallet->configuration()->getClass(),
                    $this->wallet->configuration()->getPrimaryKey()
                ),
            );
        } catch (Exception $e) {
            throw new WalletLockedException(
                sprintf(
                    'Resource is locked on Model : %s with Key: %s Additional: %s',
                    $this->wallet->configuration()->getClass(),
                    $this->wallet->configuration()->getPrimaryKey(),
                    $e->getMessage(),
                ),
            );
        } finally {
            $this->processing = false;
        }
    }

    /**
     * Checks if the user has enough balance for the operation
     * Current we use floats to make the calculations which should be enough
     *
     * We also allow credit, so if user has credit we will allow the transaction
     */
    protected function hasEnoughBalanceOperation(): bool
    {
        // Its credit, we can just move on
        if ($this->type->isCredit()) {
            return true;
        }

        $decimals = $this->wallet->configuration()->getDecimals();

        $currentBalance = $this->wallet->balance();
        $wantedToTransaction = Math::of($this->amount, $decimals, $decimals)->absolute();
        $difference = Math::of($currentBalance, $decimals, $decimals)->subtract($wantedToTransaction);
        $differencePositive = Math::of($difference, $decimals, $decimals)->absolute();
        $allowedCredit = Math::of($this->wallet->configuration()->getMaximumCredit(), $decimals, $decimals)->ensureScale();

        if ($currentBalance < $wantedToTransaction && $differencePositive <= $allowedCredit) {
            return true;
        }

        return $currentBalance >= $wantedToTransaction;
    }

    /**
     * Get the amount for the operation
     */
    protected function getAmountForOperation(): string|float|int
    {
        $decimals = $this->wallet->configuration()->getDecimals();

        return $this->type->isDebit() ?
            Math::of($this->amount, $decimals, $decimals)->negative()->toStorageScale() :
            Math::of($this->amount, $decimals, $decimals)->toStorageScale();
    }

    /**
     * Process the main callbacks
     */
    protected function processMainCallbacks(): void
    {
        foreach ($this->callbacks as $callback) {
            $this->evaluate($callback, ['operation' => $this]);
        }
    }

    /**
     * Process the callbacks that should be executed before
     */
    protected function processBeforeCallbacks(): void
    {
        foreach ($this->beforeCallbacks as $callback) {
            $this->evaluate($callback, ['operation' => $this]);
        }
    }

    /**
     * Process the callbacks that should be executed after
     */
    protected function processAfterCallbacks(): void
    {
        foreach ($this->afterCallbacks as $callback) {
            $this->evaluate($callback, ['operation' => $this]);
        }
    }

    /**
     * Compile the default callbacks that should be present
     * in all transactions
     *
     * @return $this
     */
    protected function compileDefaultCallbacks(): Operation
    {
        // Dispatch a started event
        $this->before(callback: function (): void {
            event(new TransactionStartedEvent($this->wallet->model, $this->type, $this->amount));
        }, shift: true);

        // If we should refresh balance cache should be refreshed
        if ($this->shouldRefreshBalance) {
            $this->callback(callback: function () {
                $this->wallet->balance(cached: false);
            });
        }

        // Register the Callback based on the operation
        // This is where the actual transaction is being inserted
        // After the transaction is inserted, balance refresh callback
        // will also be called and ensures that the balance is updated
        $this->callback(callback: function (): void {
            /** @phpstan-ignore-next-line */
            $this->transaction = $this->wallet->model->transactions()->create([
                'uuid' => Str::uuid(),
                'type' => $this->type->value(),
                'amount' => $this->getAmountForOperation(),
                'decimal_places' => $this->wallet->configuration()->getDecimals(),
                'meta' => $this->meta,
                'endpoint' => $this->endpoint,
            ]);

            // Dispatch Transaction Created Event
            event(new TransactionCreatedEvent(
                $this->wallet->model,
                $this->transaction
            ));

            // Send Event Based on Type
            match ($this->type) {
                TransactionType::CREDIT => event(new TransactionCreditEvent($this->wallet->model, $this->transaction)),
                TransactionType::DEBIT => event(new TransactionDebitEvent($this->wallet->model, $this->transaction)),
            };
        }, shift: true);

        return $this;
    }

    /**
     * Registers a callback that should be executed before the transaction
     * Note: If any exception is thrown within the callback, everything will be reverted
     *
     * @return $this
     */
    public function before(?callable $callback, bool $shift = false): Operation
    {
        if (! $callback) {
            return $this;
        }

        $shift ? array_unshift($this->beforeCallbacks, $callback) : $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Registers a callback that should be executed after the transaction
     * Note: If any exception is thrown within the callback, everything will be reverted
     *
     * @return $this
     */
    public function after(?callable $callback, bool $shift = false): Operation
    {
        if (! $callback) {
            return $this;
        }

        $shift ? array_unshift($this->afterCallbacks, $callback) : $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Registers a callback that should be executed after the transaction
     * This method is only for internal use, we dont want the user to mess up
     * the order of the callbacks, so we provide a after and before method to register anything
     * prior and after
     *
     * @return $this
     */
    protected function callback(?callable $callback, bool $shift = false): Operation
    {
        if (! $callback) {
            return $this;
        }

        $shift ? array_unshift($this->callbacks, $callback) : $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * Instructs that a credit should be made
     *
     * @return $this
     */
    public function credit(float|int|string $amount): Operation
    {
        $this->type = TransactionType::CREDIT;
        $this->amount = $amount;

        return $this;
    }

    /**
     * Instructs that a debit should be made
     *
     * @return $this
     */
    public function debit(float|int|string $amount): Operation
    {
        $this->type = TransactionType::DEBIT;
        $this->amount = $amount;

        return $this;
    }

    /**
     * Conditionally executes the operation
     * If the value is true, it will process the transaction
     * If the value is false, it will not process the transaction
     *
     * @return $this
     */
    public function if(bool|Closure $condition): Operation
    {
        $this->shouldContinue = (bool) $this->evaluate($condition);

        return $this;
    }

    /**
     * Pretends a operation that never goes through but still evaluates the callbacks
     *
     * @return $this
     */
    public function pretend(): Operation
    {
        return $this->if(false);
    }

    /**
     * If we should throw an exception if the transaction fails or is not present
     * suppressing the exception will not throw an exception but you can always
     * evaluate whenever it was successful or not by using the ok() or fail() methods
     *
     * @return $this
     */
    public function throw(bool|Closure $condition = true): Operation
    {
        $this->shouldThrow = (bool) $this->evaluate($condition);

        return $this;
    }

    /**
     * It's a common to figure out from where the transaction came from
     * Example: API, Frontend, etc, so you can perform some nice stats around it
     * or even figure from where this transaction came from
     *
     * @return $this
     */
    public function endpoint(?string $endpoint = null): Operation
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * Same as throw but the opposite
     *
     * @return $this
     */
    public function dontThrow(bool|Closure $condition = false): Operation
    {
        $this->shouldThrow = (bool) $this->evaluate($condition);

        return $this;
    }

    /**
     * Appends additional metadata to the transaction
     *
     * @param  array<string,mixed>  $meta
     * @return $this
     */
    public function meta(array $meta): Operation
    {
        $this->meta = $meta;

        return $this;
    }
}
