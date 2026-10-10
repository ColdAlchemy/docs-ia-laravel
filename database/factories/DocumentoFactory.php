<?php

namespace Database\Factories;

use App\Models\Documento;
use App\Models\Espaco;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Documento> */
class DocumentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'espaco_id' => Espaco::factory(),
            'numero' => 1,
            'titulo' => fake()->sentence(3),
            'conteudo' => fake()->paragraphs(2, true),
            'status' => Documento::STATUS_PENDENTE,
        ];
    }
}
