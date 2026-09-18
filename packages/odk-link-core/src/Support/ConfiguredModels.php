<?php

namespace Stats4sd\FilamentOdkLink\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ReflectionClass;
use Stats4sd\FilamentOdkLink\Contracts\FormOwner;
use Stats4sd\FilamentOdkLink\Contracts\PlatformUser;

class ConfiguredModels
{
    /** @return class-string<Model&FormOwner> */
    public function formOwnerClass(): string
    {
        return $this->modelClass('form_owner', FormOwner::class);
    }

    /** @return class-string<Model&PlatformUser> */
    public function userClass(): string
    {
        return $this->modelClass('user_model', PlatformUser::class);
    }

    public function validateOwner(mixed $owner): (Model & FormOwner) | null
    {
        if ($owner === null) {
            return null;
        }

        $class = $this->formOwnerClass();

        if (! $owner instanceof $class) {
            throw new InvalidArgumentException("filament-odk-link.models.form_owner requires an instance of {$class}; received " . get_debug_type($owner));
        }

        return $owner;
    }

    public function validateUser(mixed $user): (Model & PlatformUser) | null
    {
        if ($user === null) {
            return null;
        }

        $class = $this->userClass();

        if (! $user instanceof $class) {
            throw new InvalidArgumentException("filament-odk-link.models.user_model requires an instance of {$class}; received " . get_debug_type($user));
        }

        return $user;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return class-string<Model&T>
     */
    private function modelClass(string $name, string $contract): string
    {
        $key = "filament-odk-link.models.{$name}";
        $class = config($key);

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class) || ! is_a($class, $contract, true)) {
            throw new InvalidArgumentException("{$key} must be a concrete Eloquent model implementing {$contract}.");
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->isInstantiable() || ($reflection->getConstructor()?->getNumberOfRequiredParameters() ?? 0) > 0) {
            throw new InvalidArgumentException("{$key} must be an instantiable Eloquent model without required constructor arguments.");
        }

        return $class;
    }
}
