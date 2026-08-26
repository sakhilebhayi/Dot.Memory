@props(['name', 'title', 'maxWidth' => '38rem'])

{{-- Progressive disclosure without leaving the page: inspect something in
     depth, then close and keep your place in the list.

     Accessibility is the whole job here. A dialog that traps nobody, closes
     on nothing and announces itself as a div is worse than no dialog: it
     strands keyboard and screen-reader users inside a page that looks
     unchanged to them. So: role/aria-modal, a labelled title, Escape to
     close, focus moved in on open and returned on close, and the page
     behind made inert to scroll. --}}
<div
    x-data="{
        open: false,
        returnFocusTo: null,
        show() {
            this.returnFocusTo = document.activeElement;
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.panel?.focus());
        },
        hide() {
            this.open = false;
            document.body.style.overflow = '';
            // Return focus to whatever opened this, so closing a dialog does
            // not dump a keyboard user back at the top of the document.
            // Guarded on isConnected: Livewire can replace the triggering
            // element while the dialog is open, leaving a detached node that
            // silently swallows focus.
            const trigger = this.returnFocusTo;
            if (trigger && trigger.isConnected && typeof trigger.focus === 'function') {
                trigger.focus();
            }
        },
    }"
    {{-- Livewire 3 wraps dispatch payloads ({name: '...'}), Alpine's own
         $dispatch sends the bare value. Accept both rather than making
         callers remember which side they are on. --}}
    x-on:open-modal.window="(($event.detail?.name ?? $event.detail) === '{{ $name }}') && show()"
    x-on:close-modal.window="(($event.detail?.name ?? $event.detail) === '{{ $name }}') && hide()"
    x-on:keydown.escape.window="open && hide()"
>
    {{-- x-show, not <template x-if>: Alpine owns an x-if subtree, so
         Livewire's DOM morph never reaches content inside one and the
         dialog renders whatever was there when the page first loaded.
         x-show keeps the element in the DOM (display:none hides it from
         the tab order and the accessibility tree just as well). --}}
    <div class="dot-modal" x-show="open" x-cloak x-on:click.self="hide()">
            <div
                class="dot-modal__panel"
                style="max-width: {{ $maxWidth }};"
                x-ref="panel"
                tabindex="-1"
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-title-{{ $name }}"
            >
                <div class="dot-modal__head">
                    <h2 class="dot-modal__title" id="modal-title-{{ $name }}">{{ $title }}</h2>
                    <button type="button" class="dot-modal__close" x-on:click="hide()" aria-label="Close">
                        <span class="material-symbols-rounded" aria-hidden="true">close</span>
                    </button>
                </div>
                <div class="dot-modal__body">{{ $slot }}</div>
                @isset($footer)
                    <div class="dot-modal__foot">{{ $footer }}</div>
                @endisset
        </div>
    </div>
</div>
