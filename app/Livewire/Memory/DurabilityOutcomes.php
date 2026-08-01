<?php

namespace App\Livewire\Memory;

use App\Models\DurabilityOutcome;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Lists recent durability verification results (wiki.md §4, §6).
 */
class DurabilityOutcomes extends Component
{
    #[Computed]
    public function outcomes(): Collection
    {
        return DurabilityOutcome::with('storageTier')
            ->orderByDesc('verified_at')
            ->limit(25)
            ->get();
    }

    public function render()
    {
        return view('livewire.memory.durability-outcomes');
    }
}
