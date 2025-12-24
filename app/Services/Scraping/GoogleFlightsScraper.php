<?php

namespace App\Services\Scraping;

use App\DTOs\FlightCombination;
use App\Models\FlightPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Playwright\Playwright;

class GoogleFlightsScraper extends BaseScraper
{
    private string $baseUrl = 'https://www.google.com/travel/flights';
    private ?object $context = null;
    private ?object $page = null;

    /**
     * Executa a busca de preços no Google Flights
     */
    public function search(FlightCombination $combination): ?FlightPrice
    {
        try {
            // Inicializar Playwright
            $this->initializeBrowser();

            // Montar URL da busca
            $url = $this->buildSearchUrl($combination);

            // Navegar para a URL e esperar resultados
            $results = $this->fetchFlightResults($url);

            // Validar resultados
            if (!$this->validateSearchResults($results)) {
                return null;
            }

            // Criar FlightPrice
            return $this->createFlightPrice($combination, $results, 0);
        } catch (\Exception $e) {
            Log::error("Google Flights scraper error", [
                'combination' => $combination->toArray(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        } finally {
            $this->closeBrowser();
        }
    }

    /**
     * Retorna o nome da fonte
     */
    public function getSourceName(): string
    {
        return 'Google Flights';
    }

    /**
     * Retorna o slug da fonte
     */
    public function getSourceSlug(): string
    {
        return 'google_flights';
    }

    /**
     * Inicializa o browser headless
     */
    private function initializeBrowser(): void
    {
        if ($this->context === null) {
            try {
                // Iniciar Chromium diretamente com Playwright PHP 1.1.0 API
                $this->context = Playwright::chromium([
                    'headless' => true,
                    'slowMo' => 100, // Pequeno delay para estabilidade
                    'context' => [
                        'userAgent' => $this->getUserAgent(),
                        'locale' => 'pt-BR',
                        'viewport' => ['width' => 1920, 'height' => 1080],
                    ],
                ]);

                // Criar página
                $this->page = $this->context->newPage();

            } catch (\Exception $e) {
                Log::error("Failed to initialize Playwright browser", [
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }

    /**
     * Fecha o browser e libera recursos
     */
    private function closeBrowser(): void
    {
        try {
            if ($this->page !== null) {
                $this->page->close();
                $this->page = null;
            }

            if ($this->context !== null) {
                $this->context->close();
                $this->context = null;
            }
        } catch (\Exception $e) {
            Log::warning("Error closing browser", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Monta URL da busca no Google Flights
     */
    private function buildSearchUrl(FlightCombination $combination): string
    {
        // Formato da URL Google Flights:
        // https://www.google.com/travel/flights?q=Flights+from+GRU+to+CDG&curr=BRL&departure=2026-07-01&return=2026-07-15

        $from = strtoupper($combination->origin);
        $to = strtoupper($combination->destination);
        $outbound = $combination->departure_date->format('Y-m-d');
        $inbound = $combination->return_date->format('Y-m-d');

        // Parâmetros da query string
        $params = [
            'hl' => 'pt-BR',
            'gl' => 'br',
            'curr' => 'BRL',
            'departure' => $outbound,
            'return' => $inbound,
            'type' => '1', // 1 = Round trip
        ];

        $query = http_build_query($params);

        return "{$this->baseUrl}?q=Flights+from+{$from}+to+{$to}&{$query}";
    }

    /**
     * Busca resultados de voo no Google Flights
     */
    private function fetchFlightResults(string $url): array
    {
        try {
            // Navegar para a URL
            $this->page->goto($url, [
                'waitUntil' => 'domcontentloaded',
                'timeout' => 60000, // 60 segundos
            ]);

            // Esperar os resultados carregarem
            // Google Flights usa seletores específicos para os cards de voo
            $this->page->waitForSelector('.yR1fYc', ['timeout' => 15000]);

            // Extrair dados do primeiro voo (o melhor preço)
            $results = $this->extractFlightData();

            return $results;
        } catch (\Exception $e) {
            Log::error("Error fetching flight results", [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Extrai dados do voo da página
     */
    private function extractFlightData(): array
    {
        try {
            // Google Flights CSS Selectors (baseados na estrutura de 2025)
            // .yR1fYc - Flight card container
            // .sSHqwe - Airline name (genérico, precisa ser mais específico)
            // .YMlIz.FpEdX span - Price
            // .EfT7Ae - Stops information

            // Pegar o primeiro card de voo usando locator
            $flightCardLocator = $this->page->locator('.yR1fYc')->first();
            $flightCardExists = $flightCardLocator->count() > 0;

            if (!$flightCardExists) {
                throw new \Exception("No flight results found");
            }

            // Extrair preço primeiro (é mais específico)
            // O preço geralmente está em um span com classe YMlIz FpEdX
            $priceLocator = $flightCardLocator->locator('.YMlIz.FpEdX span')->first();
            $priceText = '';
            if ($priceLocator->count() > 0) {
                $priceText = $priceLocator->textContent();
            }

            if (empty($priceText)) {
                throw new \Exception("Price not found in results");
            }

            // Log para debug
            Log::info("Price extracted", [
                'raw_price' => $priceText,
                'length' => strlen($priceText),
            ]);

            // Limpar preço (remover R$, espaços, converter vírgula para ponto)
            $priceString = $priceText;

            // Remover R$ (case insensitive)
            $priceString = preg_replace('/R\$/i', '', $priceString);

            // Remover TODOS os espaços (incluindo Unicode non-breaking spaces)
            $priceString = preg_replace('/\s+/u', '', $priceString);

            // Converter formato brasileiro (4.342) para float (4342)
            // Primeiro remove ponto de milhar
            $priceString = str_replace('.', '', $priceString);

            $price = floatval($priceString);

            if ($price <= 0) {
                Log::warning("Price validation failed", [
                    'raw' => $priceText,
                    'cleaned' => $priceString,
                    'floatval' => $price,
                ]);
                throw new \Exception("Invalid price: {$price} (from: {$priceText})");
            }

            // Extrair companhia aérea - usar um seletor mais específico
            // O nome da companhia geralmente está dentro do card de voo principal
            $airlineLocator = $flightCardLocator->locator('div')->first();
            $airline = 'Várias'; // Padrão

            // Tentar pegar o texto do card de voo para extrair a companhia
            $fullText = $flightCardLocator->textContent();
            if (!empty($fullText)) {
                // O texto completo pode conter o nome da companhia
                // Por exemplo, "ITA Airways" ou "LATAM"
                if (stripos($fullText, 'ITA') !== false) {
                    $airline = 'ITA Airways';
                } elseif (stripos($fullText, 'LATAM') !== false) {
                    $airline = 'LATAM Airlines';
                } elseif (stripos($fullText, 'Azul') !== false) {
                    $airline = 'Azul Brazilian Airlines';
                } elseif (stripos($fullText, 'Gol') !== false) {
                    $airline = 'Gol Linhas Aéreas';
                } elseif (stripos($fullText, 'TAP') !== false) {
                    $airline = 'TAP Portugal';
                } elseif (stripos($fullText, 'Air France') !== false) {
                    $airline = 'Air France';
                }
            }

            // Extrair número de conexões se possível
            $connections = 1; // Padrão
            $stopsText = '';

            // Tentar encontrar informação de paradas
            $stopsLocator = $flightCardLocator->locator('.EfT7Ae')->first();
            if ($stopsLocator->count() > 0) {
                $stopsText = $stopsLocator->textContent();

                if (stripos($stopsText, 'direto') !== false || stripos($stopsText, 'nonstop') !== false) {
                    $connections = 0;
                } elseif (preg_match('/(\d+)\s*(parada|stop)/i', $stopsText, $matches)) {
                    $connections = (int) $matches[1];
                }
            }

            // Extrair URL do voo (para referência futura)
            $flightUrl = $this->page->url();

            return [
                'price_per_person' => $price,
                'airline' => $this->formatAirline($airline),
                'connections' => $connections,
                'baggage_included' => false, // Google Flights não mostra claramente
                'flight_url' => $flightUrl,
                'additional_data' => [
                    'source' => 'google_flights',
                    'scraped_at' => now()->toIso8601String(),
                    'stops_text' => $stopsText,
                    'browser' => 'chromium-headless',
                    'full_text' => substr($fullText, 0, 500), // Primeiros 500 caracteres para debug
                ],
            ];
        } catch (\Exception $e) {
            Log::error("Error extracting flight data", [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Verifica se a bagagem está incluída
     */
    private function checkBaggageIncluded($flightCard): bool
    {
        // Simplificado - Google Flights não mostra claramente esta informação
        return false;
    }

    /**
     * Formata nome da companhia aérea
     */
    private function formatAirline(string $airline): string
    {
        // Mapear códigos para nomes completos
        $airlines = [
            'LA' => 'LATAM Airlines',
            'G3' => 'Gol Linhas Aéreas',
            'AD' => 'Azul Brazilian Airlines',
            'TP' => 'TAP Portugal',
            'AF' => 'Air France',
            'AZ' => 'ITA Airways',
            'BA' => 'British Airways',
            'LH' => 'Lufthansa',
            'KL' => 'KLM',
            'IB' => 'Iberia',
            'AA' => 'American Airlines',
            'UA' => 'United Airlines',
            'DL' => 'Delta Air Lines',
            'JJ' => 'LATAM',
            'TK' => 'Turkish Airlines',
            'EK' => 'Emirates',
            'QR' => 'Qatar Airways',
        ];

        // Limpar nome da companhia
        $airline = trim($airline);

        // Verificar se já é um nome completo
        if (strlen($airline) > 3) {
            return $airline;
        }

        // Mapear código para nome
        return $airlines[strtoupper($airline)] ?? $airline;
    }
}