<?php

namespace Stats4sd\FilamentOdkLink\Services\OdkLinkServices;


use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithOdkCentralAccount;
use Stats4sd\FilamentOdkLink\Models\OdkLink\OdkProject;


trait OdkUserService
{

    /**
     * Creates a new user in ODK Central
     *
     * requires an array with email and password
     * @return array $userData
     *
     * @throws RequestException|ConnectionException
     */
    public function createUser(array $userData): array
    {
        $token = $this->authenticate();

        try {

            return Http::withToken($token)
                ->post("{$this->endpoint}/users", $userData)
                ->throw()
                ->json();

        } catch (RequestException $e) {
            if ($e->getCode() === 409) {
                // the user already exists; so get the odk ID
                return Http::withToken($token)
                    ->get("{$this->endpoint}/users?q={$userData['email']}")
                    ->throw()
                    ->json()[0];
            }

            throw $e;
        }
    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function updateUserPassword(WithOdkCentralAccount $user, string $oldPassword, string $newPassword)
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->put("{$this->endpoint}/users/{$user->odk_id}/password", [
                'old' => $oldPassword,
                'new' => $newPassword,
            ])
            ->throw()
            ->json();

    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function assignRole(WithOdkCentralAccount $user, string $role): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->post("{$this->endpoint}/assignments/{$role}/{$user->odk_id}")
            ->throw()
            ->json();

    }

    public function addUserToProject(WithOdkCentralAccount $user, OdkProject $odkProject): array
    {
        $token = $this->authenticate();

        ray('adding to OIDK Centrak');

        return Http::withToken($token)
            ->post("{$this->endpoint}/projects/{$odkProject->id}/assignments/manager/{$user->odk_id}")
            ->throw()
            ->json();

    }

    /**
     * Updates a project name
     *
     * @return array $projectInfo
     *
     * @throws RequestException|ConnectionException
     */
    public function updateUser(WithOdkCentralAccount $user): array
    {
        $token = $this->authenticate();

    }

    /**
     * Archives a project
     *
     * @return array $success
     *
     * @throws RequestException|ConnectionException
     */
    public function deleteUser(Authenticatable $user): array
    {
        $token = $this->authenticate();

    }
}
