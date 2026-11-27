@extends('layouts.admin')

@section('title', $title)

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="listPage()">
    @include('admin.content_pages._entry_panel')

    <div>
        <a href="{{ route('admin.content-pages.index') }}" class="text-xs font-bold text-navy hover:underline">&larr; Website &amp; App Pages</a>
        <h1 class="text-2xl font-extrabold text-navy mt-1">{{ $title }}</h1>
        <p class="text-sm text-ink-muted mt-1">{{ $holds }}. <span class="font-mono text-xs text-slate-400">{{ $address }}</span></p>
        <p class="text-sm text-ink-muted">Add, edit, reorder and delete the entries below: each opens in a panel over this page and saves on its own. A save shows on the website and in the app within seconds.</p>
    </div>

    <template x-for="(list, key) in feedEntries" :key="key">
        <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-4 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-xs font-extrabold text-navy uppercase tracking-wider" x-text="list.screen + ' (' + list.rows.length + ')'"></div>
                <div class="flex items-center gap-2">
                    <template x-if="list.add"><button type="button" @click="openPanel(list.add, 'Add to ' + list.screen)" class="px-3 py-1.5 text-xs font-bold text-navy bg-accent rounded-lg">+ Add</button></template>
                    <button type="button" @click="openPanel(list.manage, list.screen)" class="px-3 py-1.5 text-xs font-bold text-navy bg-slate-200 rounded-lg" x-text="'Manage all ' + list.screen"></button>
                </div>
            </div>
            <p x-show="!list.rows.length" class="text-sm text-slate-500">Nothing here yet.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <template x-for="(entry, ei) in list.rows" :key="ei">
                    <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-xl p-2">
                        <template x-if="entry.image"><img :src="entry.image" alt="" class="w-14 h-12 shrink-0 rounded-lg object-cover border border-slate-200"></template>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-navy truncate" x-text="entry.title"></div>
                            <div class="text-[11px] text-slate-500 truncate">
                                <span x-text="entry.note"></span>
                                <template x-if="!entry.active"><span class="text-amber-600 font-bold"> &middot; hidden</span></template>
                            </div>
                        </div>
                        @include('admin.content_pages._pdf_badge')
                        <button type="button" @click="openPanel(entry.edit, entry.title)" class="shrink-0 px-3 py-1.5 text-xs font-bold text-navy bg-white hover:bg-slate-100 border border-slate-200 rounded-lg">Edit</button>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script>
function listPage() {
    const keys = @json(array_keys($feedEntries));
    return {
        feedEntries: @json($feedEntries),
        panel: null,
        panelCanSave: false,
        // The entry's own form, inside the panel: the biggest one that is not a delete or toggle button.
        panelForm() {
            const doc = this.$refs.panelFrame?.contentDocument;
            if (!doc) return null;
            const forms = [...doc.querySelectorAll('form')].filter((f) => !f.querySelector('input[name=_method][value=DELETE]') && f.elements.length > 3);
            return forms.sort((a, b) => b.elements.length - a.elements.length)[0] ?? null;
        },
        panelLoaded() {
            try { this.panelCanSave = !!this.panelForm(); } catch (e) { this.panelCanSave = false; }
        },
        savePanel() {
            const form = this.panelForm();
            if (form) form.requestSubmit();
        },
        openPanel(url, title) {
            const u = new URL(url, location.origin);
            u.searchParams.set('embed', '1');
            this.panel = { url: u.toString(), title };
        },
        async closePanel() {
            this.panel = null;
            try {
                const r = await fetch(@json(route('admin.content-pages.feed-entries')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (r.ok) { const all = await r.json(); this.feedEntries = Object.fromEntries(keys.map((k) => [k, all[k]])); }
            } catch (e) { /* shown fresh the next time the page is opened */ }
        },
    };
}
</script>
@endpush
