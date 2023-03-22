<?php

namespace Flavorly\Wallet;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class WalletServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('wallet')
            ->hasConfigFile('laravel-wallet')
            ->hasMigration('add_transactions_and_wallet');
    }
}
