<?php

namespace Flavorly\Wallet;

use Flavorly\Wallet\Commands\WalletCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WalletServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('wallet')
            ->hasConfigFile()
            ->hasMigration('add_transactions_and-wallet')
            ->hasCommand(WalletCommand::class);
    }
}
