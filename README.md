# A simple wallet system for your user

[![Latest Version on Packagist](https://img.shields.io/packagist/v/flavorly/wallet.svg?style=flat-square)](https://packagist.org/packages/flavorly/wallet)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/flavorly/wallet/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/flavorly/wallet/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/flavorly/wallet/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/flavorly/wallet/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/flavorly/wallet.svg?style=flat-square)](https://packagist.org/packages/flavorly/wallet)

A dead simple yet secure & safe wallet for eloquent models.

## Installation

You can install the package via composer:

```bash
composer require flavorly/wallet
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="wallet-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="wallet-config"
```

This is the contents of the published config file:

```php
return [
    'decimal_places' => 10,
    'currency' => 'USD',
    'columns' => [
        'balance' => 'wallet_balance',
        'decimals' => 'wallet_decimal_places',
        'currency' => 'wallet_currency',
    ]
];
```

## Usage

```php
use Flavorly\Wallet\Concerns\HasWallet;
use Flavorly\Wallet\Contracts\WalletContract;

class User extends Model implements WalletContract
{
    use HasWallet;
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Flavorly](https://github.com/flavorly)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
