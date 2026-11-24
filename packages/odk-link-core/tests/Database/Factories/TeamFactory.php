<?php

namespace Stats4sd\OdkLink\Core\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Stats4sd\OdkLink\Core\Tests\Models\Team;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
        ];
    }
}
