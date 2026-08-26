<?php

namespace App\Livewire\Memory;

use App\Models\OpsIncident;
use App\Services\Memory\KnowledgeTranslator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Browse and search everything the ecosystem has learned. Filters speak
 * the user's language (platform names, "Fixed"/"Being watched") and the
 * text search covers the translated headline plus the decrypted record
 * narrative -- workable in-memory at the archive's current scale; revisit
 * with a search index when the archive grows past a few thousand entries.
 */
class KnowledgeBrowser extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $platform = '';

    #[Url]
    public string $status = '';

    /**
     * @return Collection<int, array{incident: OpsIncident, human: array<string, mixed>}>
     */
    #[Computed]
    public function entries(): Collection
    {
        $translator = app(KnowledgeTranslator::class);

        return OpsIncident::query()
            ->when($this->platform !== '', fn ($query) => $query->where('platform', $this->platform))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderByDesc('detected_at')
            ->get()
            ->map(fn (OpsIncident $incident): array => [
                'incident' => $incident,
                'human' => $translator->translate($incident),
            ])
            ->when($this->search !== '', fn (Collection $entries) => $entries->filter(
                fn (array $entry): bool => Str::contains(
                    Str::lower($entry['human']['headline'].' '.$entry['human']['what_was_learned'].' '.json_encode($entry['incident']->record ?? [])),
                    Str::lower($this->search),
                ),
            ))
            ->values();
    }

    /**
     * @return array<string, string> platform key => display label
     */
    #[Computed]
    public function platformOptions(): array
    {
        $translator = app(KnowledgeTranslator::class);

        return OpsIncident::query()
            ->distinct()
            ->pluck('platform')
            ->mapWithKeys(fn (string $platform): array => [$platform => $translator->platformLabel($platform)])
            ->all();
    }

    #[Computed]
    public function totalArchived(): int
    {
        return OpsIncident::query()->count();
    }

    public function render()
    {
        return view('livewire.memory.knowledge-browser');
    }
}
