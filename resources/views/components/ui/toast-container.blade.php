<div
    x-data="{
        toasts: [],
        add(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, titulo: detail.titulo, mensagem: detail.mensagem });
            setTimeout(() => this.remove(id), 6000);
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id);
        },
    }"
    x-on:agendamento-toast.window="add($event.detail)"
    class="pointer-events-none fixed right-4 top-4 z-50 flex w-80 flex-col gap-2"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-x-4"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex items-start gap-2.5 rounded-lg border border-brand-100 bg-white px-3.5 py-3 shadow-xl dark:border-brand-500/30 dark:bg-slate-900"
        >
            <span class="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-brand-500"></span>
            <div class="min-w-0 flex-1">
                <p class="text-[12.5px] font-bold text-slate-900 dark:text-white" x-text="toast.titulo"></p>
                <p class="mt-0.5 text-[12px] text-slate-500 dark:text-slate-400" x-text="toast.mensagem"></p>
            </div>
            <button type="button" @click="remove(toast.id)" class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L8.94 10l-4.72 4.72a.75.75 0 1 0 1.06 1.06L10 11.06l4.72 4.72a.75.75 0 1 0 1.06-1.06L11.06 10l4.72-4.72a.75.75 0 0 0-1.06-1.06L10 8.94 5.28 4.22Z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </template>
</div>
