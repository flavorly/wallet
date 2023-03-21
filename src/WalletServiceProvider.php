<?php

namespace Flavorly\Wallet;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Flavorly\Wallet\Commands\WalletCommand;

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
