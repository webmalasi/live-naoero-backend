@extends('layouts.admin')

@section('title', 'Edit Check-In Port - ' . $checkInTime->port)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('admin.travel-info.index') }}" class="text-xs font-bold text-slate-500 hover:text-navy">&larr; Back to Travel Info Hub</a>
            <h1 class="text-2xl font-extrabold text-navy mt-1">Edit Check-In Port</h1>
            <p class="text-sm text-ink-muted">Modify {{ $checkInTime->port }} check-in timetable.</p>
        </div>
        <form method="POST" action="{{ route('admin.travel-info.check-in.destroy', $checkInTime) }}" onsubmit="return confirm('Are you sure you want to delete this port?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-xl border border-rose-200">Delete Port</button>
        </form>
    </div>

    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm">
        <form method="POST" action="{{ route('admin.travel-info.check-in.update', $checkInTime) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Port Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="port" value="{{ old('port', $checkInTime->port) }}" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 font-bold focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        IATA Airport Code
                    </label>
                    <input type="text" name="airport_code" value="{{ old('airport_code', $checkInTime->airport_code) }}" class="w-full px-3.5 py-2.5 text-sm uppercase font-mono rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Check-In Opens <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="opens" value="{{ old('opens', $checkInTime->opens) }}" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Check-In Closes <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="closes" value="{{ old('closes', $checkInTime->closes) }}" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Port Specific Notes</label>
                <textarea name="notes" rows="2" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">{{ old('notes', $checkInTime->notes) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $checkInTime->sort_order) }}" min="0" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>

                <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between border border-slate-200 mt-5">
                    <div>
                        <div class="text-xs font-bold text-navy">Highlight Row</div>
                        <div class="text-[11px] text-slate-500">Red bold notice</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_highlighted" value="1" {{ old('is_highlighted', $checkInTime->is_highlighted) ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-rose-500"></div>
                    </label>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between border border-slate-200 mt-5">
                    <div>
                        <div class="text-xs font-bold text-navy">Active Status</div>
                        <div class="text-[11px] text-slate-500">Show on timetable</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $checkInTime->is_active) ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.travel-info.index') }}" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-2.5 text-sm font-bold text-white bg-navy hover:bg-navy-dark rounded-xl shadow-sm hover:shadow transition-all">Update Port</button>
            </div>
        </form>
    </div>
</div>
@endsection
