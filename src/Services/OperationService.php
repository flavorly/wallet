<?php

namespace Flavorly\Wallet\Services;

use Brick\Math\Exception\MathException;
use Closure;
use Exception;
use Flavorly\LaravelHelpers\Helpers\Math\Math;
use Flavorly\Wallet\Concerns\EvaluatesClosures;
use Flavorly\Wallet\Contracts\HasWallet as WalletInterface;
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
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class OperationService
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
    protected bool $credit = false;

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
    protected string $endpoint = 'default';

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
     * Stores the transaction if it was successful
     */
    public ?Transaction $transaction = null;

    /**
     * Related Model of the transaction
     */
    protected ?Model $subject = null;

    public function __construct(
        public readonly WalletInterface $model,
        public readonly CacheService $cache,
        public readonly ConfigurationService $configuration,
        public readonly BalanceService $balance,
        bool $credit = false,
    ) {
        $this->credit = $credit;
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
            ConfigurationService::getDecimals(),
            ConfigurationService::getDecimals(),
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
    public function dispatch(): OperationService
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
                $this->model,
                $this->credit,
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
                $this->model,
                $this->credit,
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
        if ($this->cache->locked()) {
            throw new WalletLockedException(
                sprintf(
                    'Resource is locked on Model : %s with Key: %s',
                    $this->configuration->getClass(),
                    $this->configuration->getPrimaryKey()
                ),
            );
        }

        try {
            $this
                ->cache
                ->blockAndWrapInTransaction($callback);
        } catch (LockTimeoutException $e) {
            throw new WalletLockedException(
                sprintf(
                    'Resource is locked on Model : %s with Key: %s',
                    $this->configuration->getClass(),
                    $this->configuration->getPrimaryKey()
                ),
            );
        } catch (Exception $e) {
            throw new WalletLockedException(
                sprintf(
                    'Resource is locked on Model : %s with Key: %s Additional: %s',
                    $this->configuration->getClass(),
                    $this->configuration->getPrimaryKey(),
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
        if ($this->credit) {
            return true;
        }

        $decimals = ConfigurationService::getDecimals();

        $balance = $this->balance->get();
        $transaction_amount = Math::of($this->amount, $decimals, $decimals)->absolute()->toFloat();
        $difference = Math::of($balance->toFloat(), $decimals, $decimals)->subtract($transaction_amount)->absolute();
        $credit = Math::of($this->configuration->getMaximumCredit(), $decimals, $decimals)->ensureScale()->toFloat();

        if ($balance->isLessThan($transaction_amount) && $difference->isLessThanOrEqual($credit)) {
            return true;
        }

        return $balance->isGreaterThanOrEqual($transaction_amount);
    }

    /**
     * Get the amount for the operation
     */
    protected function getAmountForOperation(): string|float|int
    {
        $decimals = ConfigurationService::getDecimals();

        return $this->credit
            ? Math::of($this->amount, $decimals, $decimals)->toStorageScale()
            : Math::of($this->amount, $decimals, $decimals)->negative()->toStorageScale();

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
    protected function compileDefaultCallbacks(): OperationService
    {
        // Dispatch a started event
        $this->before(callback: function (): void {
            event(new TransactionStartedEvent(
                $this->model,
                $this->credit,
                $this->amount
            ));
        }, shift: true);

        // If we should refresh balance cache should be refreshed
        if ($this->shouldRefreshBalance) {
            $this->callback(callback: function () {
                $this->balance->get(cached: false);
            });
        }

        // Register the Callback based on the operation
        // This is where the actual transaction is being inserted
        // After the transaction is inserted, balance refresh callback
        // will also be called and ensures that the balance is updated
        $this->callback(callback: function (): void {

            $payload = [
                'credit' => $this->credit,
                'amount' => $this->getAmountForOperation(),
                'meta' => $this->meta,
                'endpoint' => $this->endpoint,
            ];

            if ($this->subject) {
                $morphClass = method_exists($this->subject, 'getMorphClass') ? $this->subject->getMorphClass() : get_class($this->subject);
                $payload['subject_id'] = $this->subject->getKey();
                $payload['subject_type'] = $morphClass;
            }

            $this->transaction = $this
                ->model
                ->transactions()
                ->create($payload);

            // Dispatch Transaction Created Event
            event(new TransactionCreatedEvent(
                $this->model,
                $this->transaction
            ));

            // Send The Event Based on Credit/Debit
            if ($this->credit) {
                event(new TransactionCreditEvent($this->model, $this->transaction));
            } else {
                event(new TransactionDebitEvent($this->model, $this->transaction));
            }
            unset($payload);
        }, shift: true);

        return $this;
    }

    /**
     * Registers a callback that should be executed before the transaction
     * Note: If any exception is thrown within the callback, everything will be reverted
     *
     * @return $this
     */
    public function before(?callable $callback, bool $shift = false): OperationService
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
    public function after(?callable $callback, bool $shift = false): OperationService
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
    protected function callback(?callable $callback, bool $shift = false): OperationService
    {
        if (! $callback) {
            return $this;
        }

        $shift ? array_unshift($this->callbacks, $callback) : $this->callbacks[] = $callback;

        return $this;
    }

    /**
     * Instructs the amount of the transaction
     *
     * @return $this
     */
    public function amount(float|int|string $amount): OperationService
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * Instructs that a credit should be made
     *
     * @return $this
     */
    public function credit(float|int|string $amount): OperationService
    {
        $this->credit = true;
        $this->amount = $amount;

        return $this;
    }

    /**
     * Instructs that a debit should be made
     *
     * @return $this
     */
    public function debit(float|int|string $amount): OperationService
    {
        $this->credit = false;
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
    public function if(bool|Closure $condition): OperationService
    {
        $this->shouldContinue = (bool) $this->evaluate($condition);

        return $this;
    }

    /**
     * Pretends a operation that never goes through but still evaluates the callbacks
     *
     * @return $this
     */
    public function pretend(): OperationService
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
    public function throw(bool|Closure $condition = true): OperationService
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
    public function endpoint(string $endpoint = 'default'): OperationService
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * Same as throw but the opposite
     *
     * @return $this
     */
    public function dontThrow(bool|Closure $condition = false): OperationService
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
    public function meta(array $meta): OperationService
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * Related Model of the transaction
     *
     * Ex: User made a transaction to an order
     *
     * @return $this
     */
    public function subject(?Model $subject = null): OperationService
    {
        $this->subject = $subject;

        return $this;
    }
}
