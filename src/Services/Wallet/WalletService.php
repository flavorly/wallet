<?php

namespace Flavorly\Wallet\Services\Wallet;

use Exception;
use Flavorly\Wallet\Contracts\Wallet;
use Flavorly\Wallet\Data\WalletConfigurationData;
use Flavorly\Wallet\Exceptions\WalletDatabaseTransactionException;
use Flavorly\Wallet\Exceptions\WalletLockedException;
use Flavorly\Wallet\Models\Transaction;
use Flavorly\Wallet\Services\Math\WalletMathService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Contracts\Cache\LockTimeoutException;
use React\Promise\Deferred;

class WalletService implements WalletInterface
{
    private ?WalletConfigurationData $configuration;
    private ?WalletCacheService $cacheService;

    public function __construct(public readonly Wallet|Model $model)
    {
        $this->configuration = $this->model->walletConfiguration();
        $this->cacheService = new WalletCacheService($this);
    }

    public function getModel(): Wallet|Model
    {
        return $this->model;
    }

    protected function getBalanceCached(): float|int|string
    {
        if($this->cacheService->hasCachedBalance()) {
            return $this->cacheService->cachedBalance();
        }
        return $this->model->{$this->configuration->balance_column};
    }

    protected function refreshBalanceCache(): void
    {
        $closure = function(){
            // Sum all the balance
            $balance = $this->model->transactions()->sum('amount');

            // Cache the balance
            $this->cacheService->setBalanceCache($balance);

            // Update the balance on database
            $this->model->update([
                $this->configuration->balance_column => $balance,
            ]);
        };

        if($this->isLocked()){
            $closure();
            return;
        }
        $this->wrap($closure);
    }

    protected function getDecimalPlaces(): int
    {
        return $this->model->{$this->configuration->decimals_column};
    }

    public function balance(bool $cached = true): float|int|string
    {
        if(!$cached) {
           $this->refreshBalanceCache();
        }
        $math = (new WalletMathService($this->getDecimalPlaces()));
        return $math->toFloat($this->getBalanceCached());
    }

    /**
     * @throws WalletLockedException
     */
    protected function wrap(callable $callback, bool $shouldRefresh = true): mixed
    {
        // Wrap in a single callback a bit of callback chain pain but should be okish
        $mainCallback = function() use($callback, $shouldRefresh){
            $result =  $callback();
            $shouldRefresh && $this->refreshBalanceCache();
            return $result;
        };

        try{
            return $this->cacheService->block(function() use ($mainCallback): mixed {
                if(DB::transactionLevel() > 0){
                    return $mainCallback();
                }

                return DB::transaction(function() use ($mainCallback): mixed {
                    $result = $mainCallback();
                    throw_if(
                        $result === false || (is_countable($result) && count($result) === 0),
                        WalletDatabaseTransactionException::class,
                        sprintf('Wallet Datatable transaction failed with message: %s', $result)
                    );
                    return $result;
                });
            });
        }
        catch (LockTimeoutException $e){
            throw new WalletLockedException(
                sprintf(
                    'Resource is locked on Model : %s with Key: %s',
                    $this->model::class,
                    $this->model->getKey()
                ),
            );
        }
        catch (Exception $e)
        {
            throw new WalletLockedException(
                sprintf(
                    'Resource is locked on Model : %s with Key: %s Additional: %s',
                    $this->model::class,
                    $this->model->getKey(),
                    $e->getMessage(),
                ),
            );
        }
    }

    public function isLocked(): bool
    {
        return $this->cacheService->isLocked();
    }

    public function cache(): WalletCacheService
    {
        return $this->cacheService;
    }

    public function add(float|int|string $amount): Transaction
    {
        $closure = function() use ($amount): Transaction {
            $math = (new WalletMathService($this->getDecimalPlaces()));
            /** @var Transaction $transaction */
            $transaction =  $this->model->transactions()->create([
                'uuid' => Str::uuid(),
                'type' => Transaction::TYPE_DEPOSIT,
                'amount' => $math->toInteger($amount),
                'decimal_places' => $this->getDecimalPlaces(),
                'meta' => [],
            ]);
            return $transaction;
        };

        return $this->wrap($closure);
    }

    public function remove(float|int|string $amount): Transaction
    {
        $closure = function() use ($amount): Transaction {
            $math = (new WalletMathService($this->getDecimalPlaces()));
            /** @var Transaction $transaction */
            $transaction = $this->model->transactions()->create([
                'uuid' => Str::uuid(),
                'type' => Transaction::TYPE_WITHDRAW,
                'amount' => $math->negative($amount),
                'decimal_places' => $this->getDecimalPlaces(),
                'meta' => [],
            ]);
            return $transaction;
        };

        return $this->wrap($closure);
    }
}
