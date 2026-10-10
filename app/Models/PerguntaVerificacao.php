<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerguntaVerificacao extends Model
{
    protected $table = 'perguntas_verificacao';

    protected $fillable = ['espaco_id', 'pergunta', 'ultima_fundamentada', 'ultima_resposta', 'ultimo_motivo', 'executada_em'];

    protected function casts(): array
    {
        return ['ultima_fundamentada' => 'boolean', 'executada_em' => 'datetime'];
    }
}
