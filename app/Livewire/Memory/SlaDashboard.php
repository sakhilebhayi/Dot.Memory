<?php

namespace App\Livewire\Memory;

use App\Models\RetrievalClass;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Renders current SLA attainment per retrieval class (wiki.md §5).
 *
 * Reads only aggregated RetrievalObservation rows — never anything
 * about what was retrieved.
 */
class SlaDashboard extends Component
{
    #[Computed]
    public function classes(): Collection
    {
        return RetrievalClass::with(['storageTier', 'observations' => function ($query) {
            $query->orderByDesc('window_end')->limit(8);
        }])->orderBy('class_key')->get();
    }

    public function render()
    {
        return view('livewire.memory.sla-dashboard');
    }
}
