<?php

namespace App\Services\Scraping;

use App\DTOs\FlightCombination;
use App\Models\FlightPrice;
use Carbon\Carbon;

abstract class BaseScraper
{
    protected int $passengers;
    protected string $cabinClass;

    public function __construct()
    {
        // Valores padrão, podem ser sobrescritos
        $this->passengers = 9;
        $this->cabinClass = 'economy';
    }

    /**
     * Executa a busca de preços para uma combinação
     */
    abstract public function search(FlightCombination $combination): ?FlightPrice;

    /**
     * Retorna o nome da fonte
     */
    abstract public function getSourceName(): string;

    /**
     * Retorna o slug da fonte
     */
    abstract public function getSourceSlug(): string;

    /**
     * Configura o scraper com parâmetros da busca
     */
    public function configure(int $passengers, string $cabinClass): void
    {
        $this->passengers = $passengers;
        $this->cabinClass = $cabinClass;
    }

    /**
     * Formata preço para exibição
     */
    protected function formatPrice(float $price): string
    {
        return number_format($price, 2, ',', '.');
    }

    /**
     * Calcula o número de noites entre duas datas
     */
    protected function calculateNights(Carbon $departure, Carbon $return): int
    {
        return $departure->diffInDays($return);
    }

    /**
     * Valida se o resultado da busca é válido
     */
    protected function validateSearchResults(?array $results): bool
    {
        if ($results === null) {
            return false;
        }

        return isset($results['price_per_person'])
            && isset($results['airline'])
            && $results['price_per_person'] > 0;
    }

    /**
     * Cria um FlightPrice a partir dos resultados
     */
    protected function createFlightPrice(
        FlightCombination $combination,
        array $results,
        int $flightSearchId
    ): FlightPrice {
        return new FlightPrice([
            // 'flight_search_id' => $flightSearchId, // Será definido no Job
            'source' => $this->getSourceSlug(),
            'origin' => $combination->origin,
            'destination' => $combination->destination,
            'return_origin' => $combination->return_origin,
            'departure_date' => $combination->departure_date,
            'return_date' => $combination->return_date,
            'nights' => $combination->nights,
            'price_per_person' => $results['price_per_person'],
            'passengers' => $this->passengers,
            'currency' => 'BRL',
            'airline' => $results['airline'] ?? 'N/A',
            'connections' => $results['connections'] ?? 0,
            'baggage_included' => $results['baggage_included'] ?? false,
            'flight_url' => $results['flight_url'] ?? null,
            'additional_data' => $results['additional_data'] ?? null,
            'expires_at' => now()->addHours(6), // Preço expira em 6 horas
        ]);
    }

    /**
     * Simula delay para evitar bloqueios (apenas para scrapers reais)
     */
    protected function randomDelay(int $minMs = 500, int $maxMs = 2000): void
    {
        usleep(rand($minMs, $maxMs) * 1000);
    }

    /**
     * Retorna user agent realista
     */
    protected function getUserAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        ];

        return $agents[array_rand($agents)];
    }
}