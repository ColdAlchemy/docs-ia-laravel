<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Documento extends Model
{
    use HasFactory;

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_PROCESSANDO = 'processando';

    public const STATUS_PRONTO = 'pronto';

    public const STATUS_ERRO = 'erro';

    protected $fillable = ['titulo', 'conteudo', 'status', 'erro', 'dados'];

    protected function casts(): array
    {
        return ['dados' => 'array'];
    }

    public function trechos(): HasMany
    {
        return $this->hasMany(Trecho::class)->orderBy('ordem');
    }

    public function estaPronto(): bool
    {
        return $this->status === self::STATUS_PRONTO;
    }
}
