<div>
    <x-dot.page
        title="Timeline"
        lede="How the ecosystem's knowledge has grown — problems discovered, fixed, and reused, in the order it happened."
    />

    @forelse ($this->days as $day => $events)
        <section class="dot-day">
            <h2 class="dot-day__label">{{ $day }}</h2>
            <x-dot.card class="dot-day__card">
                <ol class="dot-events">
                    @foreach ($events as $event)
                        <li class="dot-event">
                            <span class="material-symbols-rounded dot-event__icon" aria-hidden="true">{{ $event['icon'] }}</span>
                            <span class="dot-event__text">
                                @if ($event['link'] !== null)
                                    <a href="{{ $event['link'] }}">{{ $event['text'] }}</a>
                                @else
                                    {{ $event['text'] }}
                                @endif
                            </span>
                            <time class="dot-event__time" datetime="{{ $event['at']->toISOString() }}">
                                {{ $event['at']->timezone(config('app.timezone'))->format('H:i') }}
                            </time>
                        </li>
                    @endforeach
                </ol>
            </x-dot.card>
        </section>
    @empty
        <x-dot.empty title="The story hasn't started yet" icon="timeline">
            Once the ecosystem's guardian records its first experience, this becomes the running story of what
            the Dot platforms have lived through and learned.
        </x-dot.empty>
    @endforelse
</div>
