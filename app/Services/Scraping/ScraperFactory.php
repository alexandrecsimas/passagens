<?php

namespace App\Services\Scraping;

use Exception;

class ScraperFactory
{
    /**
     * Cria uma instância do scraper apropriado
     */
    public static function make(string $source): BaseScraper
    {
        return match($source) {
            'mock' => new MockScraper(),
            'skyscanner' => throw new Exception('SkyscannerScraper ainda não implementado'),
            'google_flights' => throw new Exception('GoogleFlightsScraper ainda não implementado'),
            default => throw new Exception("Fonte desconhecida: {$source}"),
        };
    }

    /**
     * Retorna todas as fontes disponíveis
     */
    public static function getAvailableSources(): array
    {
        return [
            'mock' => 'Mock (Teste)',
            'skyscanner' => 'Skyscanner',
            'google_flights' => 'Google Flights',
        ];
    }

    /**
     * Retorna as fontes implementadas
     */
    public static function getImplementedSources(): array
    {
        return ['mock'];
    }
}