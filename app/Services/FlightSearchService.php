<?php

namespace App\Services;

use App\DTOs\FlightCombination;
use App\Jobs\ProcessFlightSearchJob;
use App\Models\FlightSearch;
use App\Models\FlightPrice;
use App\Models\SearchRule;
use App\Services\Report\TextReportGenerator;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FlightSearchService
{
    public function __construct(
        private CombinatorService $combinator,
        private TextReportGenerator $reportGenerator
    ) {}

    /**
     * Executa uma busca completa para uma SearchRule
     */
    public function executeSearch(SearchRule $rule, array $sources = ['mock']): FlightSearch
    {
        // Criar FlightSearch
        $search = $this->createFlightSearch($rule, $sources);

        try {
            $this->markAsRunning($search);

            // Gerar combinações
            $combinations = $this->combinator->generateAllCombinations($rule);
            $search->combinations_tested = $combinations->count();
            $search->save();

            // Disparar jobs
            $this->dispatchJobs($search, $combinations, $sources, $rule);

            // Capturar tempo de início antes de atualizar
            $startedAt = $search->started_at;
            $completedAt = now();

            // Atualizar estatísticas finais
            $search = $this->updateFinalStats($search);

            // Calcular duração (usando timestamp para evitar problemas de precisão)
            $duration = $startedAt
                ? (int)abs($completedAt->timestamp - $startedAt->timestamp)
                : 0;

            // Marcar como completada com duração calculada
            $search->update([
                'status' => 'completed',
                'completed_at' => $completedAt,
                'duration_seconds' => $duration,
            ]);
            $search = $search->fresh();

            // Gerar relatório
            $reportPath = $this->reportGenerator->save($search);
            Log::info("Relatório gerado: {$reportPath}");

            return $search;
        } catch (\Exception $e) {
            $this->markAsFailed($search, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cria um FlightSearch
     */
    private function createFlightSearch(SearchRule $rule, array $sources): FlightSearch
    {
        return FlightSearch::create([
            'search_rule_id' => $rule->id,
            'status' => 'pending',
            'sources_used' => $sources,
            'combinations_tested' => 0,
            'results_found' => 0,
            'errors_count' => 0,
        ]);
    }

    /**
     * Marca busca como rodando
     */
    private function markAsRunning(FlightSearch $search): void
    {
        $search->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Dispara jobs para processar combinações
     */
    private function dispatchJobs(
        FlightSearch $search,
        \Illuminate\Support\Collection $combinations,
        array $sources,
        SearchRule $rule
    ): void {
        $jobs = [];

        foreach ($sources as $source) {
            foreach ($combinations as $combination) {
                $jobs[] = new ProcessFlightSearchJob(
                    $combination,
                    $search->id,
                    $source,
                    $rule->passengers,
                    $rule->cabin_class
                );
            }
        }

        // Processar síncronamente por enquanto
        $totalJobs = count($jobs);
        $this->info("📤 Processando {$totalJobs} buscas...");

        foreach ($jobs as $index => $job) {
            $job->handle();

            $progress = intval((($index + 1) / $totalJobs) * 100);
            $barLength = intval($progress / 2);
            $bar = str_repeat('█', $barLength);
            $empty = str_repeat('░', 50 - $barLength);

            $this->info("█{$bar}{$empty} {$progress}% (" . ($index + 1) . "/{$totalJobs})");
        }
    }

    /**
     * Aguarda a conclusão de todos os jobs
     */
    private function waitForCompletion($batch, FlightSearch $search): void
    {
        // Não necessário no modo síncrono
    }

    /**
     * Atualiza estatísticas finais
     */
    private function updateFinalStats(FlightSearch $search): FlightSearch
    {
        $search = $search->fresh();

        $resultsCount = FlightPrice::where('flight_search_id', $search->id)->count();

        $lowestPrice = FlightPrice::where('flight_search_id', $search->id)
            ->orderBy('price_total')
            ->first();

        $search->update([
            'results_found' => $resultsCount,
            'lowest_price_found' => $lowestPrice?->price_total,
        ]);

        return $search->fresh();
    }

    /**
     * Marca busca como completada
     */
    private function markAsCompleted(FlightSearch $search): void
    {
        $search->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Marca busca como falha
     */
    private function markAsFailed(FlightSearch $search, string $errorMessage): void
    {
        $search->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Helper para log
     */
    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message . "\n";
        }
        Log::info($message);
    }

    private function warn(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message . "\n";
        }
        Log::warning($message);
    }
}