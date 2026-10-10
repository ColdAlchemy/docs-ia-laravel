<?php

namespace App\Services;

use App\Models\Espaco;
use App\Models\Mensagem;
use App\Models\PerguntaVerificacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Indicadores de qualidade das respostas e execução em lote das perguntas de verificação. */
class Qualidade
{
    public function __construct(private readonly DocumentAgent $agent) {}

    /**
     * @return array{total: int, fundamentadas: int, reprovadas: int, sem_fonte: int, invalidas: int, taxa: ?int}
     */
    public function indicadores(Espaco $espaco): array
    {
        $respostas = $this->respostas($espaco)->get();

        $total = $respostas->count();
        $fundamentadas = $respostas->where('fundamentada', true)->count();
        $semFonte = $respostas->filter(fn (Mensagem $m) => ! $m->fundamentada && empty($m->fontes))->count();
        $invalidas = $respostas->filter(fn (Mensagem $m) => ! empty($m->avaliacao['invalidas']))->count();

        return [
            'total' => $total,
            'fundamentadas' => $fundamentadas,
            'reprovadas' => $total - $fundamentadas,
            'sem_fonte' => $semFonte,
            'invalidas' => $invalidas,
            'taxa' => $total ? (int) round($fundamentadas / $total * 100) : null,
        ];
    }

    /** Taxa de fundamentação por semana (últimas $semanas), da mais antiga para a mais recente. */
    public function porSemana(Espaco $espaco, int $semanas = 6): Collection
    {
        $inicio = now()->startOfWeek()->subWeeks($semanas - 1);
        $respostas = $this->respostas($espaco)->where('mensagens.created_at', '>=', $inicio)->get();

        return collect(range(0, $semanas - 1))->map(function (int $i) use ($inicio, $respostas) {
            $de = $inicio->copy()->addWeeks($i);
            $doGrupo = $respostas->filter(fn ($m) => $m->created_at >= $de && $m->created_at < $de->copy()->addWeek());
            $ok = $doGrupo->where('fundamentada', true)->count();

            return [
                'semana' => $de,
                'total' => $doGrupo->count(),
                'fundamentadas' => $ok,
                'taxa' => $doGrupo->count() ? (int) round($ok / $doGrupo->count() * 100) : null,
            ];
        });
    }

    /** Respostas reprovadas mais recentes, com a conversa que as originou. */
    public function reprovadas(Espaco $espaco, int $limite = 8): Collection
    {
        return $this->respostas($espaco)
            ->where('fundamentada', false)
            ->with('conversa')
            ->latest('mensagens.id')->limit($limite)->get();
    }

    /** Roda todas as perguntas de verificação do espaço e guarda o último resultado de cada uma. */
    public function executarLote(Espaco $espaco): int
    {
        $perguntas = $espaco->perguntasVerificacao()->get();

        foreach ($perguntas as $item) {
            /** @var PerguntaVerificacao $item */
            $r = $this->agent->ask($item->pergunta, new Escopo($espaco->id));
            $item->update([
                'ultima_fundamentada' => $r['avaliacao']['fundamentada'],
                'ultima_resposta' => $r['resposta'],
                'ultimo_motivo' => $r['avaliacao']['motivo'],
                'executada_em' => now(),
            ]);
        }

        return $perguntas->count();
    }

    /** @return Builder<Mensagem> */
    private function respostas(Espaco $espaco): Builder
    {
        return Mensagem::query()
            ->where('papel', 'assistant')
            ->whereHas('conversa', fn ($q) => $q->where('espaco_id', $espaco->id));
    }
}
