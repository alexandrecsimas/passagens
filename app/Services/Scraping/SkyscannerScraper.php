<?php

namespace App\Services\Scraping;

use App\DTOs\FlightCombination;
use App\Models\FlightPrice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SkyscannerScraper extends BaseScraper
{
    private string $baseUrl = 'https://www.skyscanner.com.br';

    /**
     * Executa a busca de preços no Skyscanner
     */
    public function search(FlightCombination $combination): ?FlightPrice
    {
        try {
            // Montar URL da busca
            $url = $this->buildSearchUrl($combination);

            // Fazer request
            $response = $this->makeRequest($url);

            // Parsear resposta
            $results = $this->parseResponse($response);

            // Validar resultados
            if (!$this->validateSearchResults($results)) {
                return null;
            }

            // Criar FlightPrice
            return $this->createFlightPrice($combination, $results, 0);
        } catch (\Exception $e) {
            Log::error("Skyscanner scraper error", [
                'combination' => $combination->toArray(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Retorna o nome da fonte
     */
    public function getSourceName(): string
    {
        return 'Skyscanner';
    }

    /**
     * Retorna o slug da fonte
     */
    public function getSourceSlug(): string
    {
        return 'skyscanner';
    }

    /**
     * Monta URL da busca no Skyscanner
     */
    private function buildSearchUrl(FlightCombination $combination): string
    {
        // Formato da URL Skyscanner:
        // /transporte/passagens-aereas/{origem}/{destino}/{data-ida}/{data-volta}?adultos={passageiros}

        $from = strtolower($combination->origin);
        $to = strtolower($combination->destination);
        $outbound = $combination->departure_date->format('Y-m-d');
        $inbound = $combination->return_date->format('Y-m-d');
        $adults = $this->passengers;

        return "{$this->baseUrl}/transporte/passagens-aereas/{$from}/{$to}/{$outbound}/{$inbound}?adults={$adults}&cabinclass=economy";
    }

    /**
     * Faz request HTTP com retry logic
     */
    private function makeRequest(string $url, int $retries = 3): string
    {
        for ($i = 0; $i < $retries; $i++) {
            try {
                $this->randomDelay(1000, 3000); // 1-3 segundos delay

                $response = Http::withHeaders([
                    'User-Agent' => $this->getUserAgent(),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                ])->timeout(30)->get($url);

                if ($response->successful()) {
                    return $response->body();
                }

                if ($response->status() === 429) {
                    // Rate limited - esperar mais
                    sleep(5);
                    continue;
                }

                throw new \Exception("HTTP {$response->status()}");
            } catch (\Exception $e) {
                if ($i === $retries - 1) {
                    throw $e;
                }
                sleep(2);
            }
        }

        throw new \Exception("Max retries exceeded");
    }

    /**
     * Parseia HTML da resposta do Skyscanner
     */
    private function parseResponse(string $html): array
    {
        // Verificar se há captcha ou bloqueio
        if (strpos($html, 'captcha') !== false || strpos($html, 'Cloudflare') !== false) {
            throw new \Exception("Blocked by captcha/Cloudflare");
        }

        // Tentar encontrar preços no HTML
        // NOTA: Esta é uma implementação simplificada
        // O Skyscanner muda frequentemente, então pode precisar ajustar

        // Buscar por padrão de preço no HTML
        // Exemplo: R$ 7.500 ou 7500
        preg_match('/R\$\s*([\d\.]+,\d+)/', $html, $priceMatches);

        if (!$priceMatches || !isset($priceMatches[1])) {
            // Tentar outro formato
            preg_match('/(\d+[\.,]\d+)/', $html, $priceMatches);
        }

        if (!$priceMatches || !isset($priceMatches[1])) {
            throw new \Exception("Could not find price in response");
        }

        // Limpar preço (remover R$, pontos, converter vírgula para ponto)
        $priceString = str_replace(['R$', '.'], '', $priceMatches[1]);
        $priceString = str_replace(',', '.', $priceString);
        $price = floatval($priceString);

        // Buscar companhia aérea
        preg_match('/([A-Z]{2}\s*\d+)/', $html, $airlineMatches);
        $airline = $airlineMatches[1] ?? 'Vários';

        // Tentar identificar se é direto
        $direct = stripos($html, 'direto') !== false || stripos($html, 'diretamente') !== false;
        $connections = $direct ? 0 : 1;

        // Verificar se há menção de bagagem
        $baggage = stripos($html, 'bagagem') !== false || stripos($html, 'malas') !== false;

        return [
            'price_per_person' => $price,
            'airline' => $airline,
            'connections' => $connections,
            'baggage_included' => $baggage,
            'flight_url' => '', // Seria extraído do HTML
            'additional_data' => [
                'source' => 'skyscanner',
                'scraped_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Formatar companhia aérea
     */
    private function formatAirline(string $airline): string
    {
        // Mapear códigos para nomes
        $airlines = [
            'LA' => 'Latam',
            'G3' => 'Gol',
            'AD' => 'Azul',
            'TP' => 'TAP',
            'AF' => 'Air France',
            'AZ' => 'Alitalia',
            'BA' => 'British Airways',
            'LH' => 'Lufthansa',
        ];

        foreach ($airlines as $code => $name) {
            if (strpos($airline, $code) === 0) {
                return $name;
            }
        }

        return $airline;
    }
}