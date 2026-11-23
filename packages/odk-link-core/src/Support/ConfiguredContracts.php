<?php

namespace Stats4sd\FilamentOdkLink\Support;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use ReflectionClass;

class ConfiguredContracts
{
    public function validateSubmissionConfiguration(): void
    {
        foreach (['process_method', 'foreign_key_process_method'] as $legacy) {
            if (collect(config("filament-odk-link.submission.{$legacy}"))->filter()->isNotEmpty()) {
                throw new InvalidArgumentException("Remove filament-odk-link.submission.{$legacy} and migrate the callback to contracts.submission_processor (SubmissionProcessor).");
            }
        }
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @param  class-string<T>  $default
     * @return T
     */
    public function resolve(Container $container, string $name, string $contract, string $default): object
    {
        if ($name === 'submission_processor') {
            $this->validateSubmissionConfiguration();
        }

        $key = "filament-odk-link.contracts.{$name}";
        $class = config($key) ?? $default;

        if (! is_string($class) || ! class_exists($class) || ! is_a($class, $contract, true) || ! (new ReflectionClass($class))->isInstantiable()) {
            throw new InvalidArgumentException("{$key} must be an instantiable implementation of {$contract}.");
        }

        return $container->make($class);
    }
}
