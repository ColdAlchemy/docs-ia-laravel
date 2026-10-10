<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prazo extends Model
{
    protected $fillable = ['espaco_id', 'documento_id', 'data', 'descricao'];

    protected function casts(): array
    {
        return ['data' => 'date'];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    /** Dias até o vencimento (negativo se já venceu), contados a partir de hoje. */
    public function diasRestantes(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->data->copy()->startOfDay(), false);
    }
}
