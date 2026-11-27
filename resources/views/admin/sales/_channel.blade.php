{{-- Where a shop order was placed: the website or the app. --}}
@if (($channel ?? 'app') === 'website')
    <span class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded-md bg-sky-50 border border-sky-200 text-[10px] font-bold text-sky-700">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>Website
    </span>
@else
    <span class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded-md bg-accent-light border border-accent-soft text-[10px] font-bold text-navy">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>App
    </span>
@endif
