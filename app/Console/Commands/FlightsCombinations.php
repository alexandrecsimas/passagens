<?php

namespace App\Console\Commands;

use App\Models\SearchRule;
use App\Services\CombinatorService;
use Illuminate\Console\Command;

class FlightsCombinations extends Command
{
    protected $signature = 'flights:combinations
                            {--rule-id= : ID da SearchRule (usa a ativa padrão se não informado)}
                            {--stats : Mostra apenas estatísticas}
                            {--json : Exporta em JSON}';

    protected $description = 'Lista todas as combinações de voos baseadas nas SearchRules';

    public function __construct(
        private CombinatorService $combinator
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $rule = $this->getSearchRule();

        if (!$rule) {
            $this->error('Nenhuma SearchRule ativa encontrada!');
            return Command::FAILURE;
        }

        $this->info("🔍 Analisando SearchRule: {$rule->name}");
        $this->newLine();

        $stats = $this->combinator->getStatistics($rule);

        if ($this->option('stats')) {
            $this->displayStats($rule, $stats);
            return Command::SUCCESS;
        }

        $combinations = $this->combinator->generateAllCombinations($rule);

        if ($this->option('json')) {
            $this->displayJson($combinations);
            return Command::SUCCESS;
        }

        $this->displayStats($rule, $stats);
        $this->newLine();
        $this->displayCombinations($combinations);

        return Command::SUCCESS;
    }

    private function getSearchRule(): ?SearchRule
    {
        $ruleId = $this->option('rule-id');

        if ($ruleId) {
            return SearchRule::find($ruleId);
        }

        return SearchRule::active()
            ->orderBy('priority', 'desc')
            ->first();
    }

    private function displayStats(SearchRule $rule, array $stats): void
    {
        $this->info('📊 ESTATÍSTICAS');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total de Combinações', $stats['total_combinations']],
                ['Rotas Tradicionais', $stats['traditional_routes']],
                ['Rotas Open-Jaw', $stats['open_jaw_routes']],
                ['Buscas Estimadas (2 fontes)', $stats['estimated_searches']],
            ]
        );

        $this->newLine();
        $this->info('📅 POR Noites:');
        foreach ($stats['by_nights'] as $nights => $count) {
            $this->line("  • {$nights} noites: {$count} combinações");
        }

        $this->newLine();
        $this->info('✈️  POR Origem:');
        foreach ($stats['by_origin'] as $origin => $count) {
            $this->line("  • {$origin}: {$count} combinações");
        }

        $this->newLine();
        $this->info('🌍 POR Destino:');
        foreach ($stats['by_destination'] as $dest => $count) {
            $city = match($dest) {
                'CDG' => 'Paris',
                'LHR' => 'Londres',
                'FCO' => 'Roma',
                default => $dest,
            };
            $this->line("  • {$city} ({$dest}): {$count} combinações");
        }
    }

    private function displayCombinations($combinations): void
    {
        $this->info('🔢 TODAS AS COMBINAÇÕES');

        $tableData = $combinations
            ->map(fn($c, $i) => [
                $i + 1,
                $c->departure_date->format('d/m/Y'),
                $c->return_date->format('d/m/Y'),
                $c->nights,
                $c->origin,
                $c->destination,
                $c->return_origin,
                $c->isOpenJaw() ? '✓' : '',
            ])
            ->toArray();

        $this->table(
            ['#', 'Ida', 'Volta', 'Noites', 'Origem', 'Dest', 'Volta', 'Open-Jaw'],
            $tableData
        );

        $this->newLine();
        $this->info("✅ Total: {$combinations->count()} combinações listadas");
    }

    private function displayJson($combinations): void
    {
        $this->line(json_encode($combinations->map->toArray()->toArray(), JSON_PRETTY_PRINT));
    }
}