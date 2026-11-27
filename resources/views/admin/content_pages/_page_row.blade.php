{{-- One page in the overview. $label is the name the website's menu gives it. --}}
<tr class="hover:bg-slate-50/60" data-row data-kind="page" data-status="{{ $page->is_published ? 'published' : 'hidden' }}" data-lists="{{ ! empty($lists[$page->id]) ? 1 : 0 }}" data-text="{{ mb_strtolower(($label ?? '').' '.$page->title.' '.$page->slug.' '.$page->path.' '.collect($lists[$page->id] ?? [])->pluck('label')->implode(' ')) }}">
    <td class="px-4 py-3">
        <a href="{{ route('admin.content-pages.edit', $page) }}" class="font-bold text-navy hover:underline">{{ $label ?? $page->title }}</a>
        <div class="text-[11px] font-mono text-slate-400">{{ $page->path ?: '/api/pages/'.$page->slug }}</div>
        @if(! empty($lists[$page->id]))
            <div class="mt-1.5 flex flex-wrap items-center gap-1 text-[11px] text-slate-500">
                <span>Also shows:</span>
                @foreach($lists[$page->id] as $list)
                    <a href="{{ $list['url'] }}" class="px-2 py-0.5 rounded-full bg-sky-50 text-sky-800 font-bold hover:bg-sky-100">{{ $list['label'] }} &rarr;</a>
                @endforeach
            </div>
        @endif
    </td>
    <td class="px-4 py-3 hidden lg:table-cell text-xs text-slate-500">
        {{ $page->updated_at?->diffForHumans() }}
        @if($page->editor) <span class="text-slate-400">by {{ $page->editor->name }}</span> @endif
        <div class="text-[11px] text-slate-400">version {{ $page->version }} &middot; {{ $page->sections_count }} sections</div>
    </td>
    <td class="px-4 py-3">
        <form method="POST" action="{{ route('admin.content-pages.toggle-published', $page) }}">
            @csrf @method('PATCH')
            <button type="submit" title="{{ $page->is_published ? 'Hide this page (the website and the app show their built-in text)' : 'Publish this page' }}"
                class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $page->is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                {{ $page->is_published ? 'Published' : 'Hidden' }}
            </button>
        </form>
    </td>
    <td class="px-4 py-3 text-right">
        <a href="{{ route('admin.content-pages.edit', $page) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-navy bg-accent hover:bg-accent-hover rounded-xl shadow-sm">Edit &rarr;</a>
    </td>
</tr>
