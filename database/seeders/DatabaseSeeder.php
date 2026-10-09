<?php

namespace Database\Seeders;

use App\Jobs\ProcessarDocumento;
use App\Models\Documento;
use Illuminate\Database\Seeder;

/** Documentos fictícios, apenas para demonstração. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $exemplos = [
            'Contrato de prestação de serviços (exemplo)' => "Contrato de prestação de serviços de manutenção de sistemas.\nContratante: Maria Souza\nContratada: Tech Soluções Ltda\n\nO pagamento mensal de R\$ 1.500,00 deve ocorrer até o dia 10/12/2026, por transferência bancária.\n\nO atraso no pagamento gera multa de R\$ 150,00 e juros de 1% ao mês.\n\nO contrato tem vigência até 31/12/2027 e pode ser rescindido com aviso prévio de 30 dias.",
            'Notificação extrajudicial (exemplo)' => "Notificação extrajudicial.\nNotificante: João Pereira\nNotificado: Comércio Alfa Ltda\n\nFica o notificado intimado a regularizar o débito de R\$ 4.200,00 no prazo de 15 dias, até 20/11/2026.\n\nO não atendimento poderá resultar em cobrança judicial.",
            'Política de férias (exemplo)' => "As férias devem ser solicitadas com 30 dias de antecedência.\n\nO colaborador pode dividir as férias em até três períodos, sendo um deles de no mínimo 14 dias corridos.",
        ];

        foreach ($exemplos as $titulo => $conteudo) {
            ProcessarDocumento::dispatchSync(Documento::create(['titulo' => $titulo, 'conteudo' => $conteudo]));
        }
    }
}
