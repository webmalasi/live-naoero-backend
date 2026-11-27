@extends('layouts.admin')

@section('title', 'Role & Access Management')

@section('content')
<div class="space-y-6">

    <!-- HEADER & ACTION BAR -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-navy">Roles & Access Hierarchy</h1>
            <p class="text-xs text-gray-500 mt-0.5">Define security roles, assign granular permissions, and control operational authorizations.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.roles.matrix') }}" 
               class="px-4 py-2 rounded-xl bg-white border border-gray-200 hover:border-navy text-navy font-bold text-xs shadow-sm hover:shadow transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                <span>Interactive Permission Matrix</span>
            </a>

            <a href="{{ route('admin.roles.create') }}" 
               class="px-4 py-2 rounded-xl bg-navy hover:bg-[#062663] text-white font-bold text-xs shadow-md shadow-navy/20 hover:scale-105 transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                <span>+ Create New Role</span>
            </a>
        </div>
    </div>

    <!-- ROLES GRID -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($roles as $role)
            <div class="naoero-card p-6 flex flex-col justify-between relative overflow-hidden group">
                
                <!-- Accent stripe for Super Admin -->
                @if($role->name === 'Super Admin')
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-accent to-[#FFD56B]"></div>
                @else
                    <div class="absolute top-0 left-0 right-0 h-1.5 bg-navy"></div>
                @endif

                <div>
                    <!-- Header -->
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl {{ $role->name === 'Super Admin' ? 'bg-amber-100 text-amber-900' : 'bg-navy-50 text-navy' }} flex items-center justify-center font-bold text-base shadow-sm shrink-0">
                                @if($role->name === 'Super Admin') 👑 @else 🛡️ @endif
                            </div>
                            <div>
                                <h3 class="text-lg font-extrabold text-navy leading-tight">{{ $role->name }}</h3>
                                <p class="text-[11px] font-mono text-gray-400">guard: {{ $role->guard_name }}</p>
                            </div>
                        </div>

                        <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $role->name === 'Super Admin' ? 'bg-amber-50 text-accent-hover border border-amber-200' : 'bg-navy-50 text-navy border border-navy-100' }}">
                            {{ $role->users_count }} {{ Str::plural('User', $role->users_count) }}
                        </span>
                    </div>

                    <!-- Permissions Summary -->
                    <div class="space-y-2 mb-6">
                        <div class="flex items-center justify-between text-xs text-gray-500 font-semibold">
                            <span>Permissions:</span>
                            <span class="font-bold text-navy">{{ $role->permissions_count }} granted</span>
                        </div>

                        <div class="flex flex-wrap gap-1">
                            @forelse($role->permissions->take(5) as $perm)
                                <span class="px-2 py-0.5 rounded-md bg-gray-100 text-[11px] font-mono text-gray-700">
                                    {{ $perm->name }}
                                </span>
                            @empty
                                <span class="text-xs text-gray-400 italic">No permissions assigned yet</span>
                            @endforelse

                            @if($role->permissions_count > 5)
                                <span class="px-2 py-0.5 rounded-md bg-navy-50 text-[11px] font-bold text-navy">
                                    +{{ $role->permissions_count - 5 }} more
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="pt-4 border-t border-gray-100 flex items-center justify-between gap-2">
                    <a href="{{ route('admin.roles.show', $role) }}" 
                       class="text-xs font-bold text-navy hover:text-accent-hover flex items-center gap-1 transition-colors">
                        <span>Role Details</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>

                    <div class="flex items-center gap-1">
                        <a href="{{ route('admin.roles.edit', $role) }}" 
                           class="p-2 rounded-xl text-gray-600 hover:text-navy hover:bg-navy-50 transition-colors" title="Edit Permissions">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>

                        @if($role->name !== 'Super Admin')
                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Are you sure you want to delete role {{ $role->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-2 rounded-xl text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors" title="Delete Role">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

            </div>
        @endforeach
    </div>

</div>
@endsection
