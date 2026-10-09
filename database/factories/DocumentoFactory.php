<?php

namespace Database\Factories;

use App\Models\Documento;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Documento> */
class DocumentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'titulo' => fake()->sentence(3),
            'conteudo' => fake()->paragraphs(2, true),
            'status' => Documento::STATUS_PENDENTE,
        ];
    }
}
