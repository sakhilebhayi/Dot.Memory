<?php

namespace App\Livewire\Memory;

use App\Models\Index;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Lists the graph/vector/audit-log index inventory (wiki.md §4).
 */
class IndexInventory extends Component
{
    #[Computed]
    public function indexes(): Collection
    {
        return Index::with('storageTier')
            ->orderBy('type')
            ->orderBy('index_key')
            ->get();
    }

    public function render()
    {
        return view('livewire.memory.index-inventory');
    }
}
