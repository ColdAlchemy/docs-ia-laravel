<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mensagem extends Model
{
    protected $table = 'mensagens';

    protected $fillable = ['conversa_id', 'papel', 'conteudo', 'fontes', 'passos', 'avaliacao', 'fundamentada'];

    protected function casts(): array
    {
        return ['fontes' => 'array', 'passos' => 'array', 'avaliacao' => 'array', 'fundamentada' => 'boolean'];
    }

    public function conversa(): BelongsTo
    {
        return $this->belongsTo(Conversa::class);
    }
}
