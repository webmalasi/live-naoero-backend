{{-- The entries of a live list, right in the page they appear on. --}}
<template x-if="section.type === 'feed' && feedEntries[section.feed]">
    <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="text-xs font-extrabold text-navy uppercase tracking-wider" x-text="'Entries (' + feedEntries[section.feed].rows.length + ')'"></div>
                <div class="text-[11px] text-slate-500">Add, edit, reorder and delete the entries right here: each opens in a panel over this page and saves on its own. They show everywhere the entry is used.</div>
            </div>
            <div class="flex items-center gap-2">
                <template x-if="feedEntries[section.feed].add"><button type="button" @click="openPanel(feedEntries[section.feed].add, 'Add to ' + feedEntries[section.feed].screen)" class="px-3 py-1 text-xs font-bold text-navy bg-accent rounded-lg">+ Add</button></template>
                <button type="button" @click="openPanel(feedEntries[section.feed].manage, feedEntries[section.feed].screen)" class="px-3 py-1 text-xs font-bold text-navy bg-slate-200 rounded-lg" x-text="'Manage all ' + feedEntries[section.feed].screen"></button>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            <template x-for="(entry, ei) in feedEntries[section.feed].rows" :key="ei">
                <div class="flex items-center gap-3 bg-white border border-slate-200 rounded-xl p-2">
                    <template x-if="entry.image"><img :src="entry.image" alt="" class="w-14 h-12 shrink-0 rounded-lg object-cover border border-slate-200"></template>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold text-navy truncate" x-text="entry.title"></div>
                        <div class="text-[11px] text-slate-500 truncate">
                            <span x-text="entry.note"></span>
                            <template x-if="!entry.active"><span class="text-amber-600 font-bold"> · hidden</span></template>
                        </div>
                    </div>
                    @include('admin.content_pages._pdf_badge')
                    <button type="button" @click="openPanel(entry.edit, entry.title)" class="shrink-0 px-3 py-1.5 text-xs font-bold text-navy bg-slate-100 hover:bg-slate-200 rounded-lg">Edit</button>
                </div>
            </template>
        </div>
    </div>
</template>
