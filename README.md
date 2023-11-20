# Manage ODK forms  in a harmonised way with Laravel Filament 

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stats4sd/filament-odk-link.svg?style=flat-square)](https://packagist.org/packages/stats4sd/filament-odk-link)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/stats4sd/filament-odk-link/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/stats4sd/filament-odk-link/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/stats4sd/filament-odk-link/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/stats4sd/filament-odk-link/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/stats4sd/filament-odk-link.svg?style=flat-square)](https://packagist.org/packages/stats4sd/filament-odk-link)



This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Installationp

You can install the package via composer:

```bash
composer require stats4sd/filament-odk-link
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="filament-odk-link-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="filament-odk-link-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="filament-odk-link-views"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$filamentOdkLink = new Stats4sd\FilamentOdkLink();
echo $filamentOdkLink->echoPhrase('Hello, Stats4sd!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Stats4SD](https://github.com/stats4sd)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
