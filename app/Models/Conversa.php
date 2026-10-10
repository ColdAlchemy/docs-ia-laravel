<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversa extends Model
{
    protected $fillable = ['espaco_id', 'user_id', 'documento_id', 'titulo'];

    public function mensagens(): HasMany
    {
        return $this->hasMany(Mensagem::class)->orderBy('id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
