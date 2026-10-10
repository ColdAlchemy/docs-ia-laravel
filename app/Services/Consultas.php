<?php

namespace App\Services;

use App\Models\Conversa;
use App\Models\Documento;
use App\Models\Mensagem;
use App\Models\User;

/**
 * Registra as perguntas ao agente: cria/continua a conversa, roda o agente e guarda
 * a resposta com fontes, passos e a avaliação de fundamentação.
 */
class Consultas
{
    private const MAX_HISTORICO = 10;

    public function __construct(private readonly DocumentAgent $agent) {}

    public function perguntar(User $user, int $espacoId, string $pergunta, ?Conversa $conversa = null, ?Documento $documento = null): Mensagem
    {
        $conversa ??= Conversa::create([
            'espaco_id' => $espacoId,
            'user_id' => $user->id,
            'documento_id' => $documento?->id,
            'titulo' => mb_strimwidth($pergunta, 0, 80, '…'),
        ]);

        // O histórico vem do banco, nunca do navegador.
        $historico = $conversa->mensagens()
            ->latest('id')->limit(self::MAX_HISTORICO)->get()->reverse()
            ->map(fn (Mensagem $m) => ['role' => $m->papel, 'content' => $m->conteudo])
            ->values()->all();

        $conversa->mensagens()->create(['papel' => 'user', 'conteudo' => $pergunta]);

        $resultado = $this->agent->ask($pergunta, new Escopo($espacoId, $conversa->documento_id), $historico);

        $conversa->touch();

        return $conversa->mensagens()->create([
            'papel' => 'assistant',
            'conteudo' => $resultado['resposta'],
            'fontes' => $resultado['fontes'],
            'passos' => $resultado['passos'],
            'avaliacao' => $resultado['avaliacao'],
            'fundamentada' => $resultado['avaliacao']['fundamentada'],
        ]);
    }

    /** Formato enviado ao navegador (e usado nas páginas). */
    public static function paraTela(Mensagem $m): array
    {
        return [
            'id' => $m->id,
            'papel' => $m->papel,
            'conteudo' => $m->conteudo,
            'fontes' => $m->fontes ?? [],
            'passos' => $m->passos ?? [],
            'avaliacao' => $m->avaliacao,
        ];
    }
}
