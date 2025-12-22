<?php

namespace App\Jobs;

use App\DTOs\FlightCombination;
use App\Models\FlightPrice;
use App\Models\FlightSearch;
use App\Services\Scraping\ScraperFactory;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessFlightSearchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private FlightCombination $combination,
        private int $flightSearchId,
        private string $source,
        private int $passengers,
        private string $cabinClass
    ) {}

    public function handle(): void
    {
        try {
            $scraper = ScraperFactory::make($this->source);
            $scraper->configure($this->passengers, $this->cabinClass);

            $flightPrice = $scraper->search($this->combination);

            if ($flightPrice) {
                $flightPrice->flight_search_id = $this->flightSearchId;
                $flightPrice->save();

                Log::info("Preço encontrado", [
                    'source' => $this->source,
                    'combination' => $this->combination->toArray(),
                    'price' => $flightPrice->price_per_person,
                ]);
            } else {
                Log::warning("Nenhum preço encontrado", [
                    'source' => $this->source,
                    'combination' => $this->combination->toArray(),
                ]);
            }
        } catch (Exception $e) {
            Log::error("Erro ao processar busca", [
                'source' => $this->source,
                'combination' => $this->combination->toArray(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function uniqueId(): string
    {
        return md5(json_encode([
            $this->combination->toArray(),
            $this->source,
        ]));
    }
}