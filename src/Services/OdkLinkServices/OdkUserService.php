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
    public function createUser(string $email, string $password): array
    {
        $token = $this->authenticate();

        try {

            return Http::withToken($token)
                ->post("{$this->endpoint}/users", [
                    'email' => $email,
                    'password' => $password,
                ])
                ->throw()
                ->json();

        } catch (RequestException $e) {
            if ($e->getCode() === 409) {
                // the user already exists; so get the odk ID
                $result = Http::withToken($token)
                    ->get("{$this->endpoint}/users?q={$email}")
                    ->throw()
                    ->json()[0];

                return $result;
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

        try {

            return Http::withToken($token)
                ->post("{$this->endpoint}/assignments/{$role}/{$user->odk_id}")
                ->throw()
                ->json();
        } catch (RequestException $e) {
            if ($e->getCode() === 409) {
                // user already has role;

                return [
                    'success' => true,
                ];
            }

            throw $e;
        }

    }

    public function addUserToProject(WithOdkCentralAccount $user, OdkProject $odkProject): array
    {
        $token = $this->authenticate();

        try {

            // Check if the User account exists on ODK Central
            if ($user->odk_id) {


                return Http::withToken($token)
                    ->post("{$this->endpoint}/projects/{$odkProject->id}/assignments/manager/{$user->odk_id}")
                    ->throw()
                    ->json();
            }

            return [
                'success' => true,
                'message' => 'User does not exist on ODK Central.'
            ];

        } catch (RequestException $e) {
            if ($e->getCode() === 409) {
                return [
                    'success' => true,
                ];
            }

            throw $e;
        }

    }

    /**
     * @throws RequestException
     * @throws ConnectionException
     */
    public function removeUserFromProject(WithOdkCentralAccount $user, OdkProject $odkProject): array
    {
        $token = $this->authenticate();

        return Http::withToken($token)
            ->delete("{$this->endpoint}/projects/{$odkProject->id}/assignments/manager/{$user->odk_id}")
            ->throw()
            ->json();

    }
}