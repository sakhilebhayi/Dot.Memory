<x-app-layout>
<div class="dot-page-body">
    <x-dot.page
        title="Storage & retrieval"
        lede="The technical health of the memory infrastructure itself: how fast stored knowledge can be retrieved, and whether that meets its promises. Dot.Memory stores without reading, so everything here is operational telemetry, never content."
    />

    <div class="dot-stack">
        <x-dot.panels :cols="4">
            <x-dot.panel>
                <x-dot.readout label="Retrieval classes" :value="$retrievalClassCount" :pad="2" />
            </x-dot.panel>
            <x-dot.panel>
                <x-dot.readout
                    label="Classes meeting SLA"
                    :value="$classesMeetingSla.'/'.$retrievalClassCount"
                    :tone="$retrievalClassCount > 0 && $classesMeetingSla === $retrievalClassCount ? 'ok' : null"
                />
            </x-dot.panel>
            <x-dot.panel>
                <x-dot.readout label="Active indexes" :value="$activeIndexCount" :pad="2" />
            </x-dot.panel>
            <x-dot.panel>
                <x-dot.readout
                    label="Durability failures (90d)"
                    :value="$recentDurabilityFailures"
                    :pad="2"
                    :tone="$recentDurabilityFailures > 0 ? 'bad' : 'ok'"
                />
            </x-dot.panel>
        </x-dot.panels>

        <livewire:memory.sla-dashboard />

        <div class="dot-grid dot-grid--pair">
            <livewire:memory.index-inventory />
            <livewire:memory.durability-outcomes />
        </div>
    </div>
</div>
</x-app-layout>
