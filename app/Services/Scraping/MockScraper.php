<?php

namespace App\Services\Scraping;

use App\DTOs\FlightCombination;
use App\Models\FlightPrice;

class MockScraper extends BaseScraper
{
    private const BASE_PRICE = 7500; // Preço base por pessoa
    private const AIRLINES = ['Latam', 'Azul', 'Gol', 'TAP', 'Air France', 'British Airways', 'Alitalia'];

    public function search(FlightCombination $combination): ?FlightPrice
    {
        // Simula delay de rede
        $this->randomDelay(50, 200); // 50-200ms para mock

        $results = [
            'price_per_person' => $this->generatePrice($combination),
            'airline' => $this->randomAirline($combination->destination),
            'connections' => $this->randomConnections(),
            'baggage_included' => (bool) rand(0, 1),
            'flight_url' => 'https://example.com/book/' . rand(1000, 9999),
            'additional_data' => [
                'mock' => true,
                'generated_at' => now()->toIso8601String(),
            ],
        ];

        if (!$this->validateSearchResults($results)) {
            return null;
        }

        // Será configurado no FlightSearchService
        return $this->createFlightPrice($combination, $results, 0);
    }

    public function getSourceName(): string
    {
        return 'Mock (Teste)';
    }

    public function getSourceSlug(): string
    {
        return 'mock';
    }

    /**
     * Gera preço realista baseado na combinação
     */
    private function generatePrice(FlightCombination $combination): float
    {
        $price = self::BASE_PRICE;

        // Variação por destino
        $price *= $this->getDestinationMultiplier($combination->destination);

        // Variação por origem
        $price *= $this->getOriginMultiplier($combination->origin);

        // Variação por número de noites
        $nightsMultiplier = 1 + (($combination->nights - 13) * 0.02); // +2% por noite acima de 13
        $price *= $nightsMultiplier;

        // Variação por dia da semana (fim de semana mais caro)
        $dayOfWeek = $combination->departure_date->dayOfWeek;
        if ($dayOfWeek === 5 || $dayOfWeek === 6) { // Sexta ou sábado
            $price *= 1.05;
        }

        // Variação aleatória (+/- 10%)
        $price *= (1 + (rand(-10, 10) / 100));

        // Variação se é open-jaw
        if ($combination->isOpenJaw()) {
            $price *= 1.03; // Open-jaw é ~3% mais caro
        }

        return round($price, 2);
    }

    private function getDestinationMultiplier(string $destination): float
    {
        return match($destination) {
            'CDG' => 1.10, // Paris +10%
            'LHR' => 1.15, // Londres +15%
            'FCO' => 1.05, // Roma +5%
            default => 1.0,
        };
    }

    private function getOriginMultiplier(string $origin): float
    {
        return match($origin) {
            'GRU' => 0.95, // São Paulo -5% (mais concorrência)
            'GIG' => 1.00, // Rio é base
            default => 1.0,
        };
    }

    private function randomAirline(string $destination): string
    {
        $airlinesByDestination = [
            'CDG' => ['Air France', 'Latam', 'Azul', 'TAP'],
            'LHR' => ['British Airways', 'Latam', 'Azul', 'TAP'],
            'FCO' => ['Alitalia', 'Latam', 'Azul', 'TAP'],
        ];

        $options = $airlinesByDestination[$destination] ?? self::AIRLINES;
        return $options[array_rand($options)];
    }

    private function randomConnections(): int
    {
        // 70% chance de 0 conexões, 30% de 1 conexão
        return rand(0, 10) < 7 ? 0 : 1;
    }
}