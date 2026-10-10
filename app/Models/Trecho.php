<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trecho extends Model
{
    protected $table = 'trechos';

    protected $fillable = ['documento_id', 'ordem', 'texto', 'embedding', 'inicio', 'fim'];

    protected function casts(): array
    {
        return ['embedding' => 'array'];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }
}
