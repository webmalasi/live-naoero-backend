@extends('layouts.admin')

@section('title', 'System Audit Trail Logs')

@section('content')
<div class="space-y-6">

    <!-- HEADER & ACTION BAR -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-navy">Flight Deck Audit Trail</h1>
            <p class="text-xs text-gray-500 mt-0.5">Comprehensive chronological log of administrator actions, access events, and system updates.</p>
        </div>

        @if(auth()->user()->hasRole('Super Admin'))
            <form method="POST" action="{{ route('admin.activity-logs.clear') }}" onsubmit="return confirm('Clear entire audit trail log history?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 rounded-xl bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 font-bold text-xs shadow-sm transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span>Clear Audit Trail</span>
                </button>
            </form>
        @endif
    </div>

    <!-- FILTER BAR -->
    <div class="p-4 rounded-2xl bg-white border border-gray-100 shadow-sm">
        <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            
            <div class="sm:col-span-4 relative">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search description, IP..." class="w-full px-3.5 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium focus:outline-none focus:border-navy">
            </div>

            <div class="sm:col-span-3">
                <select name="module" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:outline-none focus:border-navy">
                    <option value="">All Modules</option>
                    @foreach($modules as $m)
                        <option value="{{ $m }}" {{ request('module') === $m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-3">
                <select name="user_id" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs font-medium text-gray-700 focus:outline-none focus:border-navy">
                    <option value="">All Personnel</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2 flex items-center gap-2">
                <button type="submit" class="w-full py-2 px-3 rounded-xl bg-navy hover:bg-[#062663] text-white font-bold text-xs transition-colors">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'module', 'action', 'user_id']))
                    <a href="{{ route('admin.activity-logs.index') }}" class="py-2 px-3 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs">
                        Reset
                    </a>
                @endif
            </div>

        </form>
    </div>

    <!-- LOGS TABLE -->
    <div class="naoero-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 border-b border-gray-100 text-xs font-extrabold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="py-3.5 px-6">Timestamp</th>
                        <th class="py-3.5 px-4">Operator</th>
                        <th class="py-3.5 px-4">Module</th>
                        <th class="py-3.5 px-4">Action</th>
                        <th class="py-3.5 px-6">Activity Description</th>
                        <th class="py-3.5 px-4 text-right">IP & Network</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium text-xs">
                    @forelse($logs as $log)
                        <tr class="hover:bg-navy-50/30 transition-colors">
                            <td class="py-3.5 px-6 font-mono text-gray-500 whitespace-nowrap">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="font-bold text-navy">{{ $log->user?->name ?? 'System Service' }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-lg text-[11px] font-bold bg-navy-50 text-navy border border-navy-100">
                                    {{ $log->module }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded-md font-bold uppercase text-[10px] {{ $log->action === 'create' ? 'bg-emerald-50 text-emerald-700' : ($log->action === 'delete' ? 'bg-rose-50 text-rose-700' : 'bg-gray-100 text-gray-700') }}">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6 text-gray-800">
                                {{ $log->description }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono text-gray-400">
                                {{ $log->ip_address ?? '127.0.0.1' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-gray-400">No activity logs recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
