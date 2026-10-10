<?php

namespace Database\Factories;

use App\Models\Espaco;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Espaco> */
class EspacoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => 'Espaço '.fake()->company(),
            'dono_id' => User::factory(),
        ];
    }

    /** Cria o espaço já com o dono como membro. */
    public function configure(): static
    {
        return $this->afterCreating(fn (Espaco $e) => $e->membros()->syncWithoutDetaching([$e->dono_id]));
    }
}
