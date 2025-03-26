<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink\Traits;

// Trait to give your User model, if you want them to be able to log into ODK Central and have access to their projects
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

/** @phpstan-extends Model */
trait HasOdkCentralAccount
{

    protected static function bootHasOdkCentralAccount()
    {

    }

    /**
     * @throws RequestException
     * @throws BindingResolutionException
     * @throws ConnectionException
     */
    public function syncWithOdkCentral()
    {
        $odkLinkService = app()->make(OdkLinkService::class);

        // assign site-wide roles
        if ($this->isAdmin()) {
            $odkLinkService->assignRole($this, 'admin');
        }

        foreach ($this->teams as $team) {
            $odkLinkService->addUserToProject($this, $team->odkProject);

        }


    }

}
