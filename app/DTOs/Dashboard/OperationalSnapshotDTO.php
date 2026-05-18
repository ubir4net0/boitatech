<?php

namespace App\DTOs\Dashboard;

class OperationalSnapshotDTO
{
    public function __construct(
        public readonly string $generatedAt,
        public readonly array $cards,
        public readonly array $charts,
        public readonly array $feed,
        public readonly array $map,
    ) {
    }

    public function toArray(): array
    {
        return [
            'generated_at' => $this->generatedAt,
            'cards' => $this->cards,
            'charts' => $this->charts,
            'feed' => $this->feed,
            'map' => $this->map,
        ];
    }
}
