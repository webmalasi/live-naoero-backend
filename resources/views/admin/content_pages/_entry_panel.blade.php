    {{-- Entries of a live list are edited in a panel over this page, so nothing here is lost. --}}
    <div x-show="panel" x-cloak x-transition.opacity @keydown.escape.window="panel && closePanel()" class="fixed inset-0 z-[60] flex items-stretch justify-center bg-navy-950/60 p-2 sm:p-4">
        <div class="flex flex-col w-full max-w-[96rem] bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-slate-200 bg-slate-50">
                <div class="min-w-0">
                    <div class="text-sm font-extrabold text-navy truncate" x-text="panel?.title"></div>
                    <div class="text-[11px] text-slate-500">Press Save here: you do not need to scroll down. Your unsaved changes to the page stay as they are.</div>
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    <button type="button" x-show="panelCanSave" x-cloak @click="savePanel()" class="px-5 py-2 text-xs font-bold text-white bg-navy hover:opacity-90 rounded-xl shadow-sm">Save</button>
                    <button type="button" @click="closePanel()" class="px-4 py-2 text-xs font-bold text-navy bg-accent hover:bg-accent-hover rounded-xl">Done &amp; back to the page</button>
                </div>
            </div>
            <template x-if="panel"><iframe x-ref="panelFrame" @load="panelLoaded()" :src="panel.url" class="flex-1 w-full border-0 bg-white" title="Edit entries"></iframe></template>
        </div>
    </div>
