{{-- Choose an icon by sight. Choosing one replaces a picture on the entry; uploading a picture replaces the icon. --}}
<div x-data="{ open: false, q: '' }" @click.outside="open = false" class="relative">
    <button type="button" @click="open = !open" class="w-full flex items-center gap-2 px-3 py-2 text-sm rounded-xl border border-slate-200 bg-white text-left">
        <span class="w-5 h-5 shrink-0 text-navy" x-html="iconSvg(item.icon)"></span>
        <span class="flex-1 truncate" :class="item.icon ? 'text-navy' : 'text-slate-400'" x-text="iconLabel(item.icon)"></span>
        <span class="text-slate-400 text-xs">&#9662;</span>
    </button>
    <div x-show="open" x-cloak>
        <div class="absolute z-30 mt-1 w-72 max-w-[85vw] bg-white border border-slate-200 rounded-xl shadow-lg p-2 space-y-2">
            <input type="search" x-model="q" placeholder="Search icons" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-200">
            <div class="grid grid-cols-7 gap-1 max-h-52 overflow-y-auto">
                <template x-for="name in (open ? icons.filter(n => n.includes(q.trim().toLowerCase())) : [])" :key="name">
                    <button type="button" :title="iconLabel(name)" @click="setIcon(item, name); open = false" class="w-8 h-8 p-1.5 rounded-lg text-navy hover:bg-navy/10" :class="item.icon === name ? 'bg-accent' : ''" x-html="iconSvg(name)"></button>
                </template>
            </div>
            <button type="button" @click="setIcon(item, ''); open = false" class="w-full px-2 py-1 text-[11px] font-bold text-slate-600 bg-slate-100 rounded-lg">No icon</button>
        </div>
    </div>
</div>
