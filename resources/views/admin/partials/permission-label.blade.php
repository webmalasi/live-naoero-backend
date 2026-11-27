{{-- A permission's name and what it opens; a section master is marked as such. --}}
@php
    $permissionHelp = \App\Services\Auth\AdminAccess::permissionLabels()[$perm->name] ?? null;
    $isMaster = array_key_exists($perm->name, \App\Services\Auth\AdminAccess::SECTIONS);
@endphp
<span class="flex flex-col leading-tight">
    <span class="font-mono {{ $size ?? 'text-xs' }} text-slate-700 font-semibold">
        {{ $perm->name }}
        @if ($isMaster)
            <span class="ml-1 px-1.5 py-0.5 rounded bg-accent/30 text-navy text-[9px] font-black uppercase tracking-wider align-middle">Master</span>
        @endif
    </span>
    @if ($permissionHelp)
        <span class="text-[10px] text-slate-500 font-normal">{{ $permissionHelp }}</span>
    @endif
</span>
