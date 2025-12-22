<?php

namespace App\Services;

use App\DTOs\FlightCombination;
use App\Models\SearchRule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class CombinatorService
{
    /**
     * Gera todas as combinações possíveis para uma SearchRule
     */
    public function generateAllCombinations(SearchRule $rule): Collection
    {
        $dateCombinations = $this->generateDateCombinations($rule);
        $routeCombinations = $this->generateRouteCombinations($rule);

        $allCombinations = collect();

        foreach ($dateCombinations as $dates) {
            foreach ($routeCombinations as $route) {
                $allCombinations->push(new FlightCombination(
                    departure_date: $dates['departure'],
                    return_date: $dates['return'],
                    nights: $dates['nights'],
                    origin: $route['origin'],
                    return_origin: $route['return_origin'],
                    destination: $route['destination'],
                    search_rule_id: $rule->id,
                ));
            }
        }

        return $allCombinations;
    }

    /**
     * Gera todas as combinações de datas válidas
     */
    public function generateDateCombinations(SearchRule $rule): Collection
    {
        $combinations = collect();

        $departurePeriod = CarbonPeriod::create(
            $rule->departure_date_min,
            $rule->departure_date_max
        );

        $returnPeriod = CarbonPeriod::create(
            $rule->return_date_min,
            $rule->return_date_max
        );

        foreach ($departurePeriod as $departure) {
            foreach ($returnPeriod as $return) {
                if ($this->isValidDateRange($departure, $return, $rule)) {
                    $nights = $this->calculateNights($departure, $return);

                    $combinations->push([
                        'departure' => $departure->copy(),
                        'return' => $return->copy(),
                        'nights' => $nights,
                    ]);
                }
            }
        }

        return $combinations;
    }

    /**
     * Gera todas as combinações de rotas (incluindo open-jaw)
     */
    public function generateRouteCombinations(SearchRule $rule): Collection
    {
        $combinations = collect();
        $origins = $rule->origin_codes;
        $destinations = $rule->destination_codes;

        foreach ($origins as $origin) {
            foreach ($destinations as $destination) {
                // Rota tradicional (ida e volta pela mesma cidade)
                $combinations->push([
                    'origin' => $origin,
                    'return_origin' => $origin,
                    'destination' => $destination,
                ]);

                // Rotas open-jaw (volta por outra cidade de origem)
                foreach ($origins as $returnOrigin) {
                    if ($origin !== $returnOrigin) {
                        $combinations->push([
                            'origin' => $origin,
                            'return_origin' => $returnOrigin,
                            'destination' => $destination,
                        ]);
                    }
                }
            }
        }

        return $combinations;
    }

    /**
     * Valida se um range de datas respeita as regras
     */
    public function isValidDateRange(Carbon $departure, Carbon $return, SearchRule $rule): bool
    {
        // A volta deve ser depois da ida
        if ($return->lte($departure)) {
            return false;
        }

        $nights = $this->calculateNights($departure, $return);

        // Verifica se está dentro da janela de noites
        if ($nights < $rule->min_nights || $nights > $rule->max_nights) {
            return false;
        }

        // Verifica se está dentro das janelas de data
        if ($departure->lt($rule->departure_date_min) || $departure->gt($rule->departure_date_max)) {
            return false;
        }

        if ($return->lt($rule->return_date_min) || $return->gt($rule->return_date_max)) {
            return false;
        }

        return true;
    }

    /**
     * Calcula o número de noites entre duas datas
     */
    public function calculateNights(Carbon $departure, Carbon $return): int
    {
        return $departure->diffInDays($return);
    }

    /**
     * Retorna estatísticas das combinações
     */
    public function getStatistics(SearchRule $rule): array
    {
        $combinations = $this->generateAllCombinations($rule);

        $totalCombinations = $combinations->count();
        $openJawCount = $combinations->filter(fn($c) => $c->isOpenJaw())->count();
        $traditionalCount = $totalCombinations - $openJawCount;

        // Agrupar por número de noites
        $byNights = $combinations->groupBy(fn($c) => $c->nights)
            ->map(fn($group) => $group->count())
            ->sortKeys()
            ->toArray();

        // Agrupar por origem
        $byOrigin = $combinations->groupBy(fn($c) => $c->origin)
            ->map(fn($group) => $group->count())
            ->sortDesc()
            ->toArray();

        // Agrupar por destino
        $byDestination = $combinations->groupBy(fn($c) => $c->destination)
            ->map(fn($group) => $group->count())
            ->sortDesc()
            ->toArray();

        return [
            'total_combinations' => $totalCombinations,
            'traditional_routes' => $traditionalCount,
            'open_jaw_routes' => $openJawCount,
            'by_nights' => $byNights,
            'by_origin' => $byOrigin,
            'by_destination' => $byDestination,
            'estimated_searches' => $totalCombinations * 2, // 2 fontes (Skyscanner + Google Flights)
        ];
    }
}