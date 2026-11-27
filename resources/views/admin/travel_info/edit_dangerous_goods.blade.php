@extends('layouts.admin')

@section('title', 'Edit Dangerous Goods Item - ' . $dangerousGood->item_name)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('admin.travel-info.index') }}" class="text-xs font-bold text-slate-500 hover:text-navy">&larr; Back to Travel Info Hub</a>
            <h1 class="text-2xl font-extrabold text-navy mt-1">Edit DG Item</h1>
            <p class="text-sm text-ink-muted">Modify {{ $dangerousGood->item_name }} regulations.</p>
        </div>
        <form method="POST" action="{{ route('admin.travel-info.dangerous-goods.destroy', $dangerousGood) }}" onsubmit="return confirm('Are you sure you want to delete this item?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-xl border border-rose-200">Delete Item</button>
        </form>
    </div>

    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm">
        <form method="POST" action="{{ route('admin.travel-info.dangerous-goods.update', $dangerousGood) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Item Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="item_name" value="{{ old('item_name', $dangerousGood->item_name) }}" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 font-bold focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Category <span class="text-rose-500">*</span>
                    </label>
                    <select name="category" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy font-bold">
                        <option value="quick_reference" {{ old('category', $dangerousGood->category) === 'quick_reference' ? 'selected' : '' }}>Quick Reference Table Item</option>
                        <option value="conditional" {{ old('category', $dangerousGood->category) === 'conditional' ? 'selected' : '' }}>Accepted With Specific Conditions</option>
                        <option value="prohibited" {{ old('category', $dangerousGood->category) === 'prohibited' ? 'selected' : '' }}>Prohibited Articles</option>
                        <option value="topic" {{ old('category', $dangerousGood->category) === 'topic' ? 'selected' : '' }}>Dedicated Topic Section</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Carry-On Baggage</label>
                    <input type="text" name="carry_on" value="{{ old('carry_on', $dangerousGood->carry_on) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Checked Baggage</label>
                    <input type="text" name="checked" value="{{ old('checked', $dangerousGood->checked) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Airline Approval</label>
                    <input type="text" name="approval_required" value="{{ old('approval_required', $dangerousGood->approval_required) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Icon Identifier</label>
                    <input type="text" name="icon" value="{{ old('icon', $dangerousGood->icon) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $dangerousGood->sort_order) }}" min="0" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Specific Conditions / Details</label>
                <textarea name="details" rows="3" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">{{ old('details', $dangerousGood->details) }}</textarea>
            </div>

            <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between border border-slate-200">
                <div>
                    <div class="text-xs font-bold text-navy">Visibility Status</div>
                    <div class="text-[11px] text-slate-500">Show on dangerous goods page</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $dangerousGood->is_active) ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                </label>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.travel-info.index') }}" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-2.5 text-sm font-bold text-white bg-navy hover:bg-navy-dark rounded-xl shadow-sm hover:shadow transition-all">Update Item</button>
            </div>
        </form>
    </div>
</div>
@endsection
