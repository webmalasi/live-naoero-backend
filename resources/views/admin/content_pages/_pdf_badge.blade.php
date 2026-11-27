{{-- Lists whose entries carry a document (Agency Resources) say whether each one really holds a PDF. --}}
<template x-if="entry.pdf !== undefined">
    <span class="shrink-0 inline-flex items-center gap-1 px-2 py-1 text-[11px] font-bold border rounded-lg"
          :class="entry.pdf ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'"
          :title="entry.pdf ? 'This card has a PDF' : 'This card has no PDF'">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            <path x-show="entry.pdf" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2 2 4-4"/>
            <path x-show="!entry.pdf" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 12l4 4m0-4l-4 4"/>
        </svg>
        <span x-text="entry.pdf ? 'PDF' : 'No PDF'"></span>
    </span>
</template>
