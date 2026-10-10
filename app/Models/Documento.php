<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Documento extends Model
{
    use HasFactory;

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_PROCESSANDO = 'processando';

    public const STATUS_PRONTO = 'pronto';

    public const STATUS_ERRO = 'erro';

    protected $fillable = [
        'espaco_id', 'user_id', 'numero', 'origem', 'arquivo_nome',
        'titulo', 'conteudo', 'status', 'erro', 'dados',
    ];

    protected function casts(): array
    {
        return ['dados' => 'array'];
    }

    public function espaco(): BelongsTo
    {
        return $this->belongsTo(Espaco::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function trechos(): HasMany
    {
        return $this->hasMany(Trecho::class)->orderBy('ordem');
    }

    public function prazos(): HasMany
    {
        return $this->hasMany(Prazo::class)->orderBy('data');
    }

    public function conversas(): HasMany
    {
        return $this->hasMany(Conversa::class);
    }

    public function estaPronto(): bool
    {
        return $this->status === self::STATUS_PRONTO;
    }

    /** Número de registro no formato 0007/2026. */
    public function registro(): string
    {
        return sprintf('%04d/%d', $this->numero ?: $this->id, ($this->created_at ?? now())->year);
    }

    public function rotuloTipo(): string
    {
        return match ($this->dados['tipo'] ?? null) {
            'contrato' => 'Contrato',
            'notificacao' => 'Notificação',
            'peticao' => 'Petição',
            'outro' => 'Outro',
            default => '—',
        };
    }

    public function rotuloStatus(): string
    {
        return match ($this->status) {
            self::STATUS_PENDENTE => 'Na fila',
            self::STATUS_PROCESSANDO => 'Processando',
            self::STATUS_PRONTO => 'Arquivado',
            self::STATUS_ERRO => 'Com erro',
            default => $this->status,
        };
    }
}
