{{-- A page that is a list of entries, in the overview. --}}
<tr class="hover:bg-slate-50/60" data-row data-kind="list" data-status="live" data-lists="1" data-text="{{ mb_strtolower(($label ?? '').' '.$info[0].' '.$info[1].' '.$info[2]) }}">
    <td class="px-4 py-3">
        <a href="{{ route('admin.content-pages.list', $key) }}" class="font-bold text-navy hover:underline">{{ $label ?? $info[0] }}</a>
        <div class="text-[11px] text-slate-500">{{ $info[2] }} <span class="font-mono text-slate-400">&middot; {{ $info[1] }}</span></div>
    </td>
    <td class="px-4 py-3 hidden lg:table-cell text-xs text-slate-400">A list of entries</td>
    <td class="px-4 py-3"><span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">Live</span></td>
    <td class="px-4 py-3 text-right">
        <a href="{{ route('admin.content-pages.list', $key) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-navy bg-accent hover:bg-accent-hover rounded-xl shadow-sm">Edit &rarr;</a>
    </td>
</tr>
