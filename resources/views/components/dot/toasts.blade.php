{{-- One toast host for the whole app, included once by the layout.

     Two rules learned the hard way elsewhere in this ecosystem: the payload
     shape is fixed (detail.type / detail.message), because a mismatched
     shape renders a blank pill that looks like a bug in the feature that
     fired it; and errors linger longer than successes, because a failure
     the reader missed is a failure they will hit again.

     Announced politely via aria-live so a screen reader hears the outcome
     without being yanked out of context. --}}
<div
    class="dot-toasts"
    aria-live="polite"
    aria-atomic="false"
    x-data="{
        toasts: [],
        add(detail) {
            const type = ['success', 'error', 'warning', 'info'].includes(detail?.type) ? detail.type : 'info';
            const message = (detail?.message ?? '').toString().trim();
            if (message === '') { return; }
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type, message, title: detail?.title ?? null });
            setTimeout(() => this.dismiss(id), type === 'error' ? 10000 : type === 'warning' ? 8000 : 5000);
        },
        dismiss(id) { this.toasts = this.toasts.filter((t) => t.id !== id); },
        icon(type) {
            return { success: 'check_circle', error: 'error', warning: 'warning', info: 'info' }[type];
        },
    }"
    x-on:notify.window="add($event.detail)"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div class="dot-toast" :class="'dot-toast--' + toast.type" role="status">
            <span class="material-symbols-rounded dot-toast__icon" x-text="icon(toast.type)" aria-hidden="true"></span>
            <div class="dot-toast__text">
                <strong class="dot-toast__title" x-show="toast.title" x-text="toast.title"></strong>
                <span x-text="toast.message"></span>
            </div>
            <button type="button" class="dot-toast__close" x-on:click="dismiss(toast.id)" aria-label="Dismiss">
                <span class="material-symbols-rounded" aria-hidden="true">close</span>
            </button>
        </div>
    </template>
</div>
