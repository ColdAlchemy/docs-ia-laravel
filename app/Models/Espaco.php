<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Espaço de trabalho: agrupa documentos, conversas e membros. */
class Espaco extends Model
{
    use HasFactory;

    protected $table = 'espacos';

    protected $fillable = ['nome', 'dono_id'];

    public function dono(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dono_id');
    }

    public function membros(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->orderBy('users.name');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    public function prazos(): HasMany
    {
        return $this->hasMany(Prazo::class);
    }

    public function conversas(): HasMany
    {
        return $this->hasMany(Conversa::class);
    }

    public function perguntasVerificacao(): HasMany
    {
        return $this->hasMany(PerguntaVerificacao::class);
    }

    /** Próximo número de registro do espaço. */
    public function proximoNumero(): int
    {
        return (int) $this->documentos()->max('numero') + 1;
    }
}
