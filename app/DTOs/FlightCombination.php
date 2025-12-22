<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class FlightCombination
{
    public function __construct(
        public Carbon $departure_date,
        public Carbon $return_date,
        public int $nights,
        public string $origin,
        public string $return_origin,
        public string $destination,
        public int $search_rule_id,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            departure_date: Carbon::parse($data['departure_date']),
            return_date: Carbon::parse($data['return_date']),
            nights: $data['nights'],
            origin: $data['origin'],
            return_origin: $data['return_origin'],
            destination: $data['destination'],
            search_rule_id: $data['search_rule_id'],
        );
    }

    public function getRouteAttribute(): string
    {
        if ($this->origin === $this->return_origin) {
            return sprintf('%s → %s → %s', $this->origin, $this->destination, $this->return_origin);
        }

        return sprintf('%s → %s / %s → %s (open-jaw)',
            $this->origin,
            $this->destination,
            $this->destination,
            $this->return_origin
        );
    }

    public function getDateRangeAttribute(): string
    {
        return sprintf('%s → %s',
            $this->departure_date->format('d/m/Y'),
            $this->return_date->format('d/m/Y')
        );
    }

    public function isOpenJaw(): bool
    {
        return $this->origin !== $this->return_origin;
    }

    public function toArray(): array
    {
        return [
            'departure_date' => $this->departure_date->toDateString(),
            'return_date' => $this->return_date->toDateString(),
            'nights' => $this->nights,
            'origin' => $this->origin,
            'return_origin' => $this->return_origin,
            'destination' => $this->destination,
            'route' => $this->getRouteAttribute(),
            'is_open_jaw' => $this->isOpenJaw(),
            'search_rule_id' => $this->search_rule_id,
        ];
    }
}