@extends('layouts.admin')

@section('title', 'Add Check-In Port Timetable')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('admin.travel-info.index') }}" class="text-xs font-bold text-slate-500 hover:text-navy">&larr; Back to Travel Info Hub</a>
            <h1 class="text-2xl font-extrabold text-navy mt-1">Add Check-In Port</h1>
            <p class="text-sm text-ink-muted">Configure desk opening and closing schedule for an airport port.</p>
        </div>
    </div>

    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm">
        <form method="POST" action="{{ route('admin.travel-info.check-in.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Port Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="port" value="{{ old('port') }}" required placeholder="e.g. Brisbane, Tarawa, Majuro" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 font-bold focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        IATA Airport Code
                    </label>
                    <input type="text" name="airport_code" value="{{ old('airport_code') }}" placeholder="e.g. BNE, TRW, INU" class="w-full px-3.5 py-2.5 text-sm uppercase font-mono rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Check-In Opens <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="opens" value="{{ old('opens', '3 hours before departure') }}" required placeholder="3 hours before departure" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Check-In Closes <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="closes" value="{{ old('closes', '1 hour before departure') }}" required placeholder="1 hour before departure" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Port Specific Notes</label>
                <textarea name="notes" rows="2" placeholder="Optional notes regarding early bag drop or immigration..." class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">{{ old('notes') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>

                <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between border border-slate-200 mt-5">
                    <div>
                        <div class="text-xs font-bold text-navy">Highlight Row</div>
                        <div class="text-[11px] text-slate-500">Red bold notice</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_highlighted" value="1" {{ old('is_highlighted') ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-rose-500"></div>
                    </label>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between border border-slate-200 mt-5">
                    <div>
                        <div class="text-xs font-bold text-navy">Active Status</div>
                        <div class="text-[11px] text-slate-500">Show on timetable</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.travel-info.index') }}" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-2.5 text-sm font-bold text-white bg-navy hover:bg-navy-dark rounded-xl shadow-sm hover:shadow transition-all">Save Port</button>
            </div>
        </form>
    </div>
</div>
@endsection
