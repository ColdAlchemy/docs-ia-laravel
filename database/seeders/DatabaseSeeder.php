<?php

namespace Database\Seeders;

use App\Jobs\ProcessarDocumento;
use App\Models\Documento;
use App\Models\Espaco;
use App\Models\User;
use App\Services\Consultas;
use Illuminate\Database\Seeder;

/** Dados fictícios, apenas para demonstração: dois usuários, um espaço, documentos, prazos e conversas. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demo = User::firstOrCreate(
            ['email' => 'demo@docsia.test'],
            ['name' => 'Ana Demonstração', 'password' => 'demonstracao'],
        );
        $colega = User::firstOrCreate(
            ['email' => 'colega@docsia.test'],
            ['name' => 'Bruno Colega', 'password' => 'demonstracao'],
        );

        $espaco = Espaco::firstOrCreate(['nome' => 'Escritório Modelo', 'dono_id' => $demo->id]);
        $espaco->membros()->syncWithoutDetaching([$demo->id, $colega->id]);

        $em5 = now()->addDays(5)->format('d/m/Y');
        $ha3 = now()->subDays(3)->format('d/m/Y');

        $exemplos = [
            'Contrato de prestação de serviços (exemplo)' => "Contrato de prestação de serviços de manutenção de sistemas.\nContratante: Maria Souza\nContratada: Tech Soluções Ltda\n\nO pagamento mensal de R\$ 1.500,00 deve ocorrer até o dia 10/12/2026, por transferência bancária.\n\nO atraso no pagamento gera multa de R\$ 150,00 e juros de 1% ao mês.\n\nO contrato tem vigência até 31/12/2027 e pode ser rescindido com aviso prévio de 30 dias.",
            'Notificação extrajudicial (exemplo)' => "Notificação extrajudicial.\nNotificante: João Pereira\nNotificado: Comércio Alfa Ltda\n\nFica o notificado intimado a regularizar o débito de R\$ 4.200,00 no prazo de 15 dias, até 20/11/2026.\n\nO não atendimento poderá resultar em cobrança judicial.",
            'Política de férias (exemplo)' => "As férias devem ser solicitadas com 30 dias de antecedência.\n\nO colaborador pode dividir as férias em até três períodos, sendo um deles de no mínimo 14 dias corridos.",
            'Termo de entrega de equipamentos (exemplo)' => "Termo de entrega de equipamentos.\nContratante: Padaria Pão Quente\nContratada: Tech Soluções Ltda\n\nOs equipamentos devem ser entregues até {$em5}, no endereço da contratante.\n\nO descumprimento do prazo gera multa de R\$ 500,00 por dia de atraso.",
            'Aviso de renovação de licença (exemplo)' => "Aviso de renovação de licença de software.\nContratante: Clínica Vida\nContratada: Tech Soluções Ltda\n\nA licença venceu em {$ha3}. A renovação custa R\$ 980,00 por ano e deve ser paga com urgência.",
        ];

        foreach ($exemplos as $titulo => $conteudo) {
            if ($espaco->documentos()->where('titulo', $titulo)->exists()) {
                continue;
            }
            $documento = Documento::create([
                'espaco_id' => $espaco->id,
                'user_id' => $demo->id,
                'numero' => $espaco->proximoNumero(),
                'titulo' => $titulo,
                'conteudo' => $conteudo,
            ]);
            ProcessarDocumento::dispatchSync($documento);
        }

        foreach ([
            'Qual é o prazo de pagamento do contrato?',
            'Quando as férias devem ser solicitadas?',
            'Existe cláusula de arbitragem internacional?',
        ] as $pergunta) {
            $espaco->perguntasVerificacao()->firstOrCreate(['pergunta' => $pergunta]);
        }

        if ($espaco->conversas()->doesntExist()) {
            $consultas = app(Consultas::class);
            foreach ([
                'Qual é o prazo de pagamento do contrato?',
                'Quanto é a multa por atraso?',
                'Qual o número do passaporte do diretor?',
            ] as $pergunta) {
                $consultas->perguntar($demo, $espaco->id, $pergunta);
            }
        }
    }
}
