<?php

namespace App\Services\Report;

use App\Models\FlightSearch;

class ExecutiveReportGenerator
{
    /**
     * Gera relatório executivo resumido (top 5)
     */
    public function generate(FlightSearch $search): string
    {
        $lines = [];

        // Cabeçalho
        $lines[] = "📊 RELATÓRIO EXECUTIVO - {$search->searchRule->name}";
        $lines[] = str_repeat('═', 50);
        $lines[] = '';
        $lines[] = "Data: " . $search->updated_at->format('d/m/Y H:i');
        $lines[] = "Combinações testadas: {$search->combinations_tested}";
        $lines[] = "Resultados encontrados: {$search->results_found}";
        $lines[] = "Fontes: " . implode(', ', $search->sources_used);
        $lines[] = "Duração: {$search->duration_seconds}s";
        $lines[] = '';

        // Melhores preços (top 5)
        $lines[] = str_repeat('═', 50);
        $lines[] = "🏆 MELHORES PREÇOS (TOP 5)";
        $lines[] = str_repeat('═', 50);
        $lines[] = '';

        $bestPrices = $search->flightPrices()
            ->orderBy('price_total')
            ->limit(5)
            ->get();

        if ($bestPrices->isEmpty()) {
            $lines[] = "Nenhum preço encontrado.";
        } else {
            foreach ($bestPrices as $index => $price) {
                $rank = $index + 1;
                $medal = match($rank) {
                    1 => '🥇',
                    2 => '🥈',
                    3 => '🥉',
                    default => "{$rank}.",
                };

                $returnOrigin = $price->return_origin === $price->origin ? $price->origin : $price->return_origin;
                $lines[] = "{$medal} {$price->price_per_person_formatted}";
                $lines[] = "   {$price->origin} {$price->departure_date->format('d/m')} → {$price->destination} {$price->return_date->format('d/m')} → {$returnOrigin}";
                $lines[] = "   Total: {$price->price_total_formatted} ({$price->passengers} pessoas)";
                $lines[] = "   {$price->nights} noites | {$price->airline}";
                $lines[] = '';
            }
        }

        // Estatísticas resumidas
        $lines[] = str_repeat('═', 50);
        $lines[] = "📈 ESTATÍSTICAS";
        $lines[] = str_repeat('═', 50);
        $lines[] = '';

        $stats = $this->calculateStats($search);

        $lines[] = "Menor preço: " . number_format($stats['min_price'], 2, ',', '.');
        $lines[] = "Preço médio: " . number_format($stats['avg_price'], 2, ',', '.');
        $lines[] = "Variação: " . number_format($stats['variation_percent'], 1, ',', '.') . "%";
        $lines[] = '';

        // Melhor destino
        $lines[] = "Melhor destino: " . $this->getBestDestination($stats);
        $lines[] = '';

        // Rodapé
        $lines[] = str_repeat('═', 50);
        $lines[] = "Gerado em: " . now()->format('d/m/Y H:i:s');
        $lines[] = str_repeat('═', 50);

        return implode("\n", $lines);
    }

    /**
     * Gera relatório no formato para WhatsApp (mais compacto)
     */
    public function generateForWhatsApp(FlightSearch $search): string
    {
        $lines = [];

        // Cabeçalho
        $lines[] = "✈️ *" . str_replace(' ', '\\ ', $search->searchRule->name) . "*";
        $lines[] = '';

        // Top 3 preços (WhatsApp tem limite de caracteres)
        $bestPrices = $search->flightPrices()
            ->orderBy('price_total')
            ->limit(3)
            ->get();

        if ($bestPrices->isNotEmpty()) {
            $lines[] = "*🏆 MELHORES PREÇOS*";
            $lines[] = '';

            foreach ($bestPrices as $index => $price) {
                $medal = match($index + 1) {
                    1 => '🥇',
                    2 => '🥈',
                    3 => '🥉',
                };

                $returnOrigin = $price->return_origin === $price->origin ? $price->origin : $price->return_origin;
                $lines[] = "{$medal} *{$price->price_per_person_formatted}*";
                $lines[] = "{$price->origin} {$price->departure_date->format('d/m')} → {$price->destination} → {$returnOrigin}";
                $lines[] = "Total: *{$price->price_total_formatted}* | {$price->nights} noites";
                $lines[] = '';
            }

            // Estatísticas rápidas
            $stats = $this->calculateStats($search);
            $lines[] = "📊 Menor: " . number_format($stats['min_price'], 2, ',', '.') . " | Média: " . number_format($stats['avg_price'], 2, ',', '.');
            $lines[] = "Melhor destino: " . $this->getBestDestination($stats);
        } else {
            $lines[] = "❌ Nenhum preço encontrado.";
        }

        $lines[] = '';
        $lines[] = "_Atualizado: " . $search->updated_at->format('d/m/Y H:i') . "_";

        return implode("\n", $lines);
    }

    /**
     * Calcula estatísticas básicas
     */
    private function calculateStats(FlightSearch $search): array
    {
        $prices = $search->flightPrices->pluck('price_per_person')->filter();

        if ($prices->isEmpty()) {
            return [
                'avg_price' => 0,
                'min_price' => 0,
                'max_price' => 0,
                'price_range' => 0,
                'variation_percent' => 0,
                'by_destination' => [],
            ];
        }

        $minPrice = $prices->min();
        $maxPrice = $prices->max();
        $avgPrice = $prices->avg();

        $byDestination = $search->flightPrices()
            ->selectRaw('destination, AVG(price_per_person) as avg_price')
            ->groupBy('destination')
            ->get()
            ->pluck('avg_price', 'destination')
            ->toArray();

        return [
            'avg_price' => $avgPrice,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'price_range' => $maxPrice - $minPrice,
            'variation_percent' => (($maxPrice - $minPrice) / $minPrice) * 100,
            'by_destination' => $byDestination,
        ];
    }

    /**
     * Retorna o melhor destino (menor média)
     */
    private function getBestDestination(array $stats): string
    {
        if (empty($stats['by_destination'])) {
            return 'N/A';
        }

        $cityNames = [
            'CDG' => 'Paris',
            'LHR' => 'Londres',
            'FCO' => 'Roma'
        ];

        $bestDestination = min($stats['by_destination']);
        $bestCode = array_search($bestDestination, $stats['by_destination']);

        return ($cityNames[$bestCode] ?? $bestCode) . ' (R$ ' . number_format($bestDestination, 2, ',', '.') . ')';
    }
}