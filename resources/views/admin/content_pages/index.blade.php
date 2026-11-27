@extends('layouts.admin')

@section('title', 'Website & App Pages')

@section('content')
@php
    $groupNames = \App\Models\ContentPage::GROUPS;
    $total = $bySlug->count() + count($listPages);
@endphp
<div class="space-y-6" x-data='pagesFilter(@json($search))' x-init="init()">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Website & App Pages</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">{{ $total }} pages</span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Every page of the website and the app is managed here: its text, pictures, cards and questions, and the lists it shows (fleet, team, offers…). Open a page and edit everything in one place. A save shows on the website and in the app within seconds, and every save can be undone from the page's history.</p>
        </div>
        <a href="{{ route('admin.content-pages.create') }}" class="inline-flex items-center gap-1 px-4 py-2 text-xs font-bold text-navy bg-accent hover:bg-accent-hover rounded-xl shadow-sm">+ New page</a>
    </div>

    <div class="sticky top-16 z-20 -mx-1 px-1 py-2 bg-[#F5F7FB]/95 backdrop-blur">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-3 space-y-3">
            <div class="relative">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/></svg>
                <input type="search" x-model="q" @input="apply()" x-ref="search" placeholder="Search pages by name, address or list… (press / to search)" autofocus
                       class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy focus:border-navy">
            </div>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="font-bold text-slate-500 uppercase tracking-wider text-[10px]">Status</span>
                    <template x-for="o in [['all','All'],['published','Published'],['hidden','Hidden']]" :key="o[0]">
                        <button type="button" @click="status = o[0]; apply()" :class="status === o[0] ? 'bg-navy text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'" class="px-3 py-1 rounded-full font-bold" x-text="o[1]"></button>
                    </template>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="font-bold text-slate-500 uppercase tracking-wider text-[10px]">Type</span>
                    <template x-for="o in [['all','All'],['page','Pages'],['list','Lists of entries'],['lists','Pages that show lists']]" :key="o[0]">
                        <button type="button" @click="kind = o[0]; apply()" :class="kind === o[0] ? 'bg-navy text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'" class="px-3 py-1 rounded-full font-bold" x-text="o[1]"></button>
                    </template>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="font-bold text-slate-500 uppercase tracking-wider text-[10px]">Menu</span>
                    <select x-model="section" @change="apply()" class="px-3 py-1 rounded-lg border border-slate-200 text-xs font-bold text-navy bg-white">
                        <option value="">Everywhere</option>
                        @foreach(array_keys($menu) as $heading)<option value="{{ $heading }}">{{ $heading }}</option>@endforeach
                        <option value="Other pages">Other pages (not in the menu)</option>
                    </select>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <span class="text-slate-500"><b class="text-navy" x-text="shown"></b> of <span x-text="total"></span> shown</span>
                    <button type="button" x-show="filtered" x-cloak @click="reset()" class="font-bold text-navy underline">Clear filters</button>
                </div>
            </div>
        </div>
    </div>

    @php
        $head = '<thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="text-left px-4 py-2.5">Page</th><th class="text-left px-4 py-2.5 hidden lg:table-cell">Last saved</th><th class="text-left px-4 py-2.5">Status</th><th class="px-4 py-2.5"></th></tr></thead>';
    @endphp

        {{-- Laid out like the website's menu: the heading, then its pages. --}}
        @foreach($menu as $heading => $items)
            <div class="space-y-3" data-section="{{ $heading }}">
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-500">{{ $heading }} <span class="normal-case font-semibold text-slate-400">&middot; menu on the website</span></h2>
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <table class="w-full text-sm">{!! $head !!}<tbody class="divide-y divide-slate-100">
                        @foreach($items as $item)
                            @if(is_string($item))
                                <tr class="bg-slate-50" data-sub><td colspan="4" class="px-4 py-1.5 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">{{ $item }}</td></tr>
                            @elseif($item[0] === 'page' && isset($bySlug[$item[1]]))
                                @include('admin.content_pages._page_row', ['page' => $bySlug[$item[1]], 'label' => $item[2], 'lists' => $lists])
                            @elseif($item[0] === 'list' && isset($listPages[$item[1]]))
                                @include('admin.content_pages._list_row', ['key' => $item[1], 'info' => $listPages[$item[1]], 'label' => $item[2]])
                            @endif
                        @endforeach
                    </tbody></table>
                </div>
            </div>
        @endforeach

        <div class="space-y-3" data-section="Other pages">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-500">Other pages <span class="normal-case font-semibold text-slate-400">&middot; not in the website menu</span></h2>
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <table class="w-full text-sm">{!! $head !!}<tbody class="divide-y divide-slate-100">
                    @foreach($otherPages as $page) @include('admin.content_pages._page_row', ['page' => $page, 'lists' => $lists]) @endforeach
                    @foreach($otherLists as $key => $info) @include('admin.content_pages._list_row', ['key' => $key, 'info' => $info]) @endforeach
                </tbody></table>
            </div>
        </div>
<p x-show="shown === 0" x-cloak class="p-8 text-center bg-white rounded-2xl border border-slate-200 text-sm text-slate-500 font-bold">No page matches these filters.</p>
</div>
@endsection

@push('scripts')
<script>
function pagesFilter(initial) {
    return {
        q: initial || '', status: 'all', kind: 'all', section: '',
        shown: 0, total: 0,
        get filtered() { return this.q !== '' || this.status !== 'all' || this.kind !== 'all' || this.section !== ''; },
        init() {
            this.total = this.$root.querySelectorAll('[data-row]').length;
            this.apply();
            window.addEventListener('keydown', (e) => {
                if (e.key === '/' && !/input|textarea|select/i.test(document.activeElement?.tagName ?? '')) { e.preventDefault(); this.$refs.search.focus(); }
            });
        },
        reset() { this.q = ''; this.status = 'all'; this.kind = 'all'; this.section = ''; this.apply(); },
        apply() {
            const words = this.q.trim().toLowerCase().split(/\s+/).filter(Boolean);
            let shown = 0;
            this.$root.querySelectorAll('[data-section]').forEach((sec) => {
                const hereOk = !this.section || sec.dataset.section === this.section;
                let any = 0;
                let sub = null, subAny = 0;
                const closeSub = () => { if (sub) sub.classList.toggle('hidden', !subAny); };
                sec.querySelectorAll('tbody tr').forEach((tr) => {
                    if (tr.hasAttribute('data-sub')) { closeSub(); sub = tr; subAny = 0; return; }
                    const d = tr.dataset;
                    const ok = hereOk
                        && words.every((w) => d.text.includes(w))
                        && (this.status === 'all' || d.status === this.status)
                        && (this.kind === 'all' || (this.kind === 'lists' ? d.lists === '1' : d.kind === this.kind));
                    tr.classList.toggle('hidden', !ok);
                    if (ok) { any++; subAny++; }
                });
                closeSub();
                sec.classList.toggle('hidden', !any);
                shown += any;
            });
            this.shown = shown;
        },
    };
}
</script>
@endpush
