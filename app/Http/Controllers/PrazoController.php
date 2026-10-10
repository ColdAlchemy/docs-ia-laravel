<?php

namespace App\Http\Controllers;

use App\Models\Prazo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PrazoController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['mes' => ['nullable', 'date_format:Y-m']]);

        $mes = $request->filled('mes')
            ? CarbonImmutable::createFromFormat('!Y-m', $request->query('mes'))
            : CarbonImmutable::now()->startOfMonth();

        // Grade de domingo a sábado cobrindo o mês inteiro.
        $inicio = $mes->startOfWeek(CarbonImmutable::SUNDAY);
        $fim = $mes->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY);

        $doPeriodo = Prazo::with('documento:id,titulo')
            ->where('espaco_id', $this->espaco()->id)
            ->whereDate('data', '>=', $inicio->toDateString())
            ->whereDate('data', '<=', $fim->toDateString())
            ->orderBy('data')->get()
            ->groupBy(fn (Prazo $p) => $p->data->toDateString());

        $semanas = [];
        for ($dia = $inicio; $dia <= $fim; $dia = $dia->addDay()) {
            $semanas[intdiv((int) round($inicio->diffInDays($dia)), 7)][] = [
                'data' => $dia,
                'do_mes' => $dia->month === $mes->month,
                'hoje' => $dia->isToday(),
                'prazos' => $doPeriodo->get($dia->toDateString(), collect()),
            ];
        }

        $base = Prazo::with('documento:id,titulo,numero,created_at')->where('espaco_id', $this->espaco()->id);

        return view('prazos.index', [
            'mes' => $mes,
            'anterior' => $mes->subMonth()->format('Y-m'),
            'proximo' => $mes->addMonth()->format('Y-m'),
            'semanas' => $semanas,
            'vencidos' => (clone $base)->where('data', '<', now()->toDateString())->orderByDesc('data')->limit(10)->get(),
            'proximos' => (clone $base)->where('data', '>=', now()->toDateString())->orderBy('data')->limit(30)->get(),
        ]);
    }

    /** Exporta os prazos do espaço como eventos de dia inteiro (.ics) para agendas externas. */
    public function ics(): Response
    {
        $linhas = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//DocsIA//Prazos//PT-BR', 'CALSCALE:GREGORIAN'];
        $agora = gmdate('Ymd\THis\Z');

        Prazo::with('documento:id,titulo')->where('espaco_id', $this->espaco()->id)->orderBy('data')->get()
            ->each(function (Prazo $p) use (&$linhas, $agora) {
                $linhas[] = 'BEGIN:VEVENT';
                $linhas[] = "UID:prazo-{$p->id}@docsia";
                $linhas[] = "DTSTAMP:{$agora}";
                $linhas[] = 'DTSTART;VALUE=DATE:'.$p->data->format('Ymd');
                $linhas[] = 'DTEND;VALUE=DATE:'.$p->data->addDay()->format('Ymd');
                $linhas[] = 'SUMMARY:'.$this->escapar($p->descricao.' — '.$p->documento->titulo);
                $linhas[] = 'END:VEVENT';
            });
        $linhas[] = 'END:VCALENDAR';

        return response(implode("\r\n", $linhas)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="prazos-docsia.ics"',
        ]);
    }

    private function escapar(string $texto): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $texto);
    }
}
