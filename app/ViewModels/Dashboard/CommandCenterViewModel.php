<?php

namespace App\ViewModels\Dashboard;

use App\DTOs\Dashboard\OperationalSnapshotDTO;

class CommandCenterViewModel
{
    public function __construct(
        private readonly OperationalSnapshotDTO $snapshot,
    ) {
    }

    public function toArray(): array
    {
        return $this->snapshot->toArray();
    }

    public function generatedAtHuman(): string
    {
        return now()->parse($this->snapshot->generatedAt)->format('d/m/Y H:i');
    }
}
