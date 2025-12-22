<?php

namespace App\Services\Report;

use App\Models\FlightSearch;
use Illuminate\Support\Facades\Storage;

class TextReportGenerator
{
    public function generate(FlightSearch $search): string
    {
        $lines = [];

        // Cabeçalho
        $lines[] = str_repeat('═', 60);
        $lines[] = "  RELATÓRIO DE BUSCA DE PASSAGENS";
        $lines[] = "  {$search->searchRule->name}";
        $lines[] = str_repeat('═', 60);
        $lines[] = '';
        $lines[] = "Data: " . $search->updated_at->format('d/m/Y H:i');
        $lines[] = "Buscas realizadas: {$search->combinations_tested}";
        $lines[] = "Resultados encontrados: {$search->results_found}";
        $lines[] = "Fontes: " . implode(', ', $search->sources_used);
        $lines[] = "Duração: {$search->duration_seconds}s";
        $lines[] = '';

        // Melhores preços
        $lines[] = str_repeat('─', 60);
        $lines[] = "🥇 MELHORES PREÇOS";
        $lines[] = str_repeat('─', 60);
        $lines[] = '';

        $bestPrices = $search->flightPrices()
            ->orderBy('price_total')
            ->limit(20)
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
                $lines[] = "{$medal} {$price->price_per_person_formatted} → {$price->origin} {$price->departure_date->format('d/m')} → {$price->destination} {$price->return_date->format('d/m')} → {$returnOrigin}";
                $lines[] = "   Total: {$price->price_total_formatted} ({$price->passengers} pessoas)";
                $lines[] = "   Fonte: {$price->source_label} | {$price->nights} noites | {$price->airline}";
                $lines[] = '';
            }
        }

        // Estatísticas
        $lines[] = str_repeat('─', 60);
        $lines[] = "📊 ESTATÍSTICAS";
        $lines[] = str_repeat('─', 60);
        $lines[] = '';

        $stats = $this->calculateStats($search);

        $lines[] = "Preço médio: " . number_format($stats['avg_price'], 2, ',', '.');
        $lines[] = "Menor preço: " . number_format($stats['min_price'], 2, ',', '.');
        $lines[] = "Maior preço: " . number_format($stats['max_price'], 2, ',', '.');
        $lines[] = "Variação: " . number_format($stats['price_range'], 2, ',', '.') . " (" . number_format($stats['variation_percent'], 1, ',', '.') . "%)";
        $lines[] = '';

        // Por origem
        $lines[] = "Por origem:";
        foreach ($stats['by_origin'] as $origin => $avgPrice) {
            $lines[] = "  • {$origin}: " . number_format($avgPrice, 2, ',', '.');
        }
        $lines[] = '';

        // Por destino
        $lines[] = "Por destino:";
        $cityNames = ['CDG' => 'Paris', 'LHR' => 'Londres', 'FCO' => 'Roma'];
        foreach ($stats['by_destination'] as $dest => $avgPrice) {
            $city = $cityNames[$dest] ?? $dest;
            $lines[] = "  • {$city} ({$dest}): " . number_format($avgPrice, 2, ',', '.');
        }
        $lines[] = '';

        // Rodapé
        $lines[] = str_repeat('═', 60);
        $lines[] = "Fim do relatório";
        $lines[] = str_repeat('═', 60);

        return implode("\n", $lines);
    }

    public function save(FlightSearch $search): string
    {
        $content = $this->generate($search);

        $filename = "search_{$search->id}_" . now()->format('Ymd_His') . ".txt";

        // Usar storage_path diretamente para evitar problemas de permissão
        $path = storage_path("reports/{$filename}");

        // Criar diretório se não existir
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);

        return $path;
    }

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
                'by_origin' => [],
                'by_destination' => [],
            ];
        }

        $minPrice = $prices->min();
        $maxPrice = $prices->max();
        $avgPrice = $prices->avg();

        $byOrigin = $search->flightPrices()
            ->selectRaw('origin, AVG(price_per_person) as avg_price')
            ->groupBy('origin')
            ->get()
            ->pluck('avg_price', 'origin')
            ->toArray();

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
            'by_origin' => $byOrigin,
            'by_destination' => $byDestination,
        ];
    }
}