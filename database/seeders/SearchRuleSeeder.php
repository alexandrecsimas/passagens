<?php

namespace Database\Seeders;

use App\Models\SearchRule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SearchRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SearchRule::updateOrCreate(
            [
                'name' => 'Viagem Europa - 15 Anos da Clarice',
            ],
            [
                'description' => 'Busca automática de passagens para viagem em família (9 pessoas) para Paris, Londres e Roma. Janela: 18-20/07/2026 (ida) e 01-03/08/2026 (volta).',

                // Janelas de data (docs/Init.md linha 26-29)
                'departure_date_min' => '2026-07-18', // Não posso sair antes de 18/07
                'departure_date_max' => '2026-07-20', // Posso sair até 20/07
                'return_date_min' => '2026-08-01',     // Posso voltar a partir de 01/08
                'return_date_max' => '2026-08-03',     // Não posso voltar depois de 03/08

                // Duração (docs/Init.md linha 53)
                'min_nights' => 13,
                'max_nights' => 16,

                // Origens (docs/Init.md linha 31)
                'origins' => ['GRU', 'GIG'], // Priorizar GRU, comparar com GIG

                // Destinos (docs/Init.md linha 23)
                'destinations' => ['CDG', 'LHR', 'FCO'], // Paris, Londres, Roma

                // Passageiros (docs/Init.md linha 33)
                'passengers' => 9,

                // Classe (docs/Init.md linha 32)
                'cabin_class' => 'economy',

                // Preferências (docs/Init.md linha 34-35)
                'max_connections' => 1, // Máximo 1 conexão
                'baggage_included' => true, // Priorizar bagagem incluída

                // Controle
                'is_active' => true,
                'priority' => 100, // Alta prioridade
                'user_id' => null, // Sem usuário associado inicialmente
            ]
        );

        $this->command->info('✅ SearchRule "Viagem Europa - 15 Anos da Clarice" criada/atualizada com sucesso!');
    }
}