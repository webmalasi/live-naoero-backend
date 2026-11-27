{{--
    The "Adjust stock" dialog. The page sets adjust = {id, sku, name, onHand, reserved}
    on its Alpine scope to open it.
--}}
<div x-show="adjust" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-navy-950/60 p-4" @keydown.escape.window="adjust = null">
    <form method="POST" :action="'{{ url('admin/inventory/variants') }}/' + adjust?.id + '/adjust'" class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6 space-y-4" @click.outside="adjust = null"
          x-data="{ mode: 'add', qty: '' }">
        @csrf
        <div>
            <h3 class="text-lg font-extrabold text-navy">Adjust stock</h3>
            <p class="text-xs text-slate-500 mt-1"><span class="font-bold text-slate-700" x-text="adjust?.name"></span> · <span class="font-mono" x-text="adjust?.sku"></span></p>
            <p class="text-xs text-slate-500 mt-1">On hand <strong x-text="adjust?.onHand"></strong>, held for unpaid orders <strong x-text="adjust?.reserved"></strong>.</p>
        </div>
        <div class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-slate-100">
            @foreach (['add' => '+ Add', 'remove' => '− Remove', 'set' => '= Set to'] as $mode => $label)
                <label class="text-center">
                    <input type="radio" name="mode" value="{{ $mode }}" x-model="mode" class="sr-only">
                    <span class="block px-2 py-1.5 rounded-lg text-xs font-bold cursor-pointer" :class="mode === '{{ $mode }}' ? 'bg-white text-navy shadow-sm' : 'text-slate-500'">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <label class="block">
            <span class="text-xs font-bold text-slate-600" x-text="mode === 'set' ? 'New on-hand count' : 'Units'"></span>
            <input name="quantity" x-model="qty" type="number" min="0" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <span class="text-[11px] text-slate-500" x-show="qty !== ''"
                  x-text="'On hand after: ' + (mode === 'add' ? (+adjust?.onHand + +qty) : mode === 'remove' ? (+adjust?.onHand - +qty) : +qty)"></span>
        </label>
        <label class="block">
            <span class="text-xs font-bold text-slate-600">Reason</span>
            <select name="reason" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                @foreach ($reasons as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="text-xs font-bold text-slate-600">Note <span class="font-normal text-slate-400">(optional)</span></span>
            <input name="note" maxlength="300" placeholder="e.g. 2 hoodies with torn seams" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
        </label>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="adjust = null" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">Cancel</button>
            <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Save adjustment</button>
        </div>
    </form>
</div>
