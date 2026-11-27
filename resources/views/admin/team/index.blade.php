@extends('layouts.admin')

@section('title', 'Leadership & Team Management')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Leadership & Team Members</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $members->total() }} Members
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage Executive Leadership, Flight Operations, and Board profiles.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.team-members.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Team Member</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="naoero-card p-4 bg-white border border-slate-200">
        <form method="GET" action="{{ route('admin.team-members.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <select name="department" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ request('department') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-4 py-2 rounded-xl bg-navy text-white text-sm font-bold hover:bg-navy-700 transition-colors">
                    Filter
                </button>
                @if(request()->has('department'))
                    <a href="{{ route('admin.team-members.index') }}" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Team Members Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        @forelse($members as $person)
            <div class="naoero-card bg-white border border-slate-200 overflow-hidden flex flex-col justify-between">
                <div>
                    <!-- Photo -->
                    <div class="relative h-56 bg-slate-100 overflow-hidden">
                        <img src="{{ $person->photo_url_display }}" 
                             alt="{{ $person->name }}" 
                             class="w-full h-full object-cover"
                             onerror="this.src='https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=800&q=80'">
                        <div class="absolute top-2.5 right-2.5">
                            <span class="px-2 py-0.5 text-xs rounded-full font-bold shadow-sm {{ $person->is_active ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white' }}">
                                {{ $person->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="p-4 space-y-2">
                        <span class="px-2 py-0.5 rounded text-[11px] font-extrabold uppercase tracking-wider bg-navy/10 text-navy">
                            {{ $person->department }}
                        </span>
                        <h3 class="text-base font-extrabold text-navy leading-tight mt-1">{{ $person->name }}</h3>
                        <p class="text-xs font-semibold text-accent-hover">{{ $person->role }}</p>
                        @if($person->bio)
                            <p class="text-xs text-slate-500 line-clamp-3 mt-2">{{ $person->bio }}</p>
                        @endif
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs font-mono text-slate-400">#{{ $person->sort_order }}</span>
                    <div class="flex items-center gap-1">
                        <a href="{{ route('admin.team-members.edit', $person) }}" 
                           class="p-1.5 text-slate-400 hover:text-navy hover:bg-white rounded-lg transition-colors"
                           title="Edit Member">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('admin.team-members.destroy', $person) }}" 
                              onsubmit="return confirm('Are you sure you want to delete {{ $person->name }}?');"
                              class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                    title="Delete Member">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full naoero-card p-12 text-center text-slate-400 bg-white border border-slate-200">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <p class="text-base font-bold text-slate-600">No team members listed</p>
                <p class="text-xs text-slate-400 mt-1">Add executive leadership, board members, or flight captains.</p>
            </div>
        @endforelse
    </div>

    @if($members->hasPages())
        <div class="naoero-card p-4 bg-white border border-slate-200">
            {{ $members->links() }}
        </div>
    @endif

</div>
@endsection
