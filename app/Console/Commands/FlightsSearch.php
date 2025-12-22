<?php

namespace App\Console\Commands;

use App\Models\SearchRule;
use App\Services\FlightSearchService;
use Illuminate\Console\Command;

class FlightsSearch extends Command
{
    protected $signature = 'flights:search
                            {--rule-id= : ID da SearchRule (usa a ativa se não informado)}
                            {--source=mock : Fonte de dados (mock, skyscanner, google_flights, all)}
                            {--async : Dispara jobs em background e retorna}';

    protected $description = 'Executa busca de preços de passagens para todas as combinações';

    public function __construct(
        private FlightSearchService $searchService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $rule = $this->getSearchRule();

        if (!$rule) {
            $this->error('❌ Nenhuma SearchRule ativa encontrada!');
            return Command::FAILURE;
        }

        $source = $this->option('source');
        $sources = $source === 'all' ? ['mock'] : [$source]; // Apenas mock por enquanto

        $this->info("🔍 Iniciando busca de passagens...");
        $this->info("📊 Regra: {$rule->name}");
        $this->info("📡 Fonte(s): " . implode(', ', $sources));
        $this->newLine();

        try {
            $startTime = microtime(true);

            $search = $this->searchService->executeSearch($rule, $sources);

            $duration = microtime(true) - $startTime;

            $this->newLine();
            $this->info("✅ Busca completada em " . number_format($duration, 1) . " segundos");

            // Exibir resumo
            $this->displaySummary($search);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Erro ao executar busca: " . $e->getMessage());
            return Command::FAILURE;
        }
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

    private function displaySummary($search): void
    {
        $this->newLine();
        $this->info("📊 RESUMO DA BUSCA:");

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Status', $search->status],
                ['Combinações testadas', $search->combinations_tested],
                ['Resultados encontrados', $search->results_found],
                ['Duração', $search->duration_seconds . 's'],
            ]
        );

        if ($search->lowest_price_found) {
            $this->newLine();
            $this->info("💰 Menor preço encontrado:");
            $this->line("   Total: R$ " . number_format($search->lowest_price_found, 2, ',', '.'));

            $bestPrice = $search->flightPrices()
                ->orderBy('price_total')
                ->first();

            if ($bestPrice) {
                $this->line("   Por pessoa: {$bestPrice->price_per_person_formatted}");
                $this->line("   Rota: {$bestPrice->route}");
                $this->line("   Datas: {$bestPrice->date_range}");
                $this->line("   Fonte: {$bestPrice->source_label}");
            }
        }

        $this->newLine();
        $this->info("📄 Relatório salvo em: storage/reports/");
    }
}