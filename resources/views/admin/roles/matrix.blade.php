@extends('layouts.admin')

@section('title', 'Role & Permission Access Matrix')

@section('content')
<div class="space-y-6">

    <!-- HEADER & ACTIONS -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-navy">Interactive Access Matrix</h1>
            <p class="text-xs text-gray-500 mt-0.5">Audit and update role authorizations across all airline modules in a unified matrix.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.roles.index') }}" 
               class="px-4 py-2 rounded-xl bg-white border border-gray-200 hover:border-navy text-navy font-bold text-xs shadow-sm transition-all">
                ← Back to Roles
            </a>

            <button type="submit" 
                    form="matrixForm" 
                    class="px-5 py-2.5 rounded-xl bg-navy hover:bg-[#062663] text-white font-bold text-xs shadow-md shadow-navy/20 hover:scale-105 transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save Matrix Changes</span>
            </button>
        </div>
    </div>

    <!-- MATRIX FORM -->
    <form id="matrixForm" method="POST" action="{{ route('admin.roles.matrix.update') }}">
        @csrf

        <div class="naoero-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    
                    <!-- Table Header -->
                    <thead class="bg-[#012169] text-white text-xs font-extrabold uppercase tracking-wider sticky top-0 z-20">
                        <tr>
                            <th class="py-4 px-6 min-w-[240px] bg-[#012169] border-r border-white/10">Module & Permission</th>
                            @foreach($roles as $role)
                                <th class="py-4 px-4 text-center min-w-[140px] border-r border-white/10 last:border-r-0">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="text-xs font-extrabold">{{ $role->name }}</span>
                                        @if($role->name === 'Super Admin')
                                            <span class="text-[9px] px-1.5 py-0.2 rounded bg-accent text-navy font-bold">Unrestricted</span>
                                        @else
                                            <span class="text-[9px] px-1.5 py-0.2 rounded bg-white/20 text-white font-normal">{{ $role->permissions->count() }} active</span>
                                        @endif
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <!-- Table Body -->
                    <tbody class="divide-y divide-gray-200">
                        @foreach($permissions as $module => $modulePermissions)
                            <!-- Module Group Header -->
                            <tr class="bg-gray-100/90 font-extrabold text-navy text-xs uppercase tracking-wider">
                                <td colspan="{{ count($roles) + 1 }}" class="py-2.5 px-6 border-y border-gray-200">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-navy"></span>
                                        <span>{{ $module }}</span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Permissions Rows -->
                            @foreach($modulePermissions as $perm)
                                <tr class="hover:bg-navy-50/40 transition-colors">
                                    <td class="py-3 px-6 font-mono text-xs text-gray-800 font-medium border-r border-gray-100 bg-white">
                                        <div class="flex items-center justify-between">
                                            <span>{{ $perm->name }}</span>
                                        </div>
                                    </td>

                                    @foreach($roles as $role)
                                        @php
                                            $hasPermission = $role->hasPermissionTo($perm->name);
                                            $isSuperAdmin = ($role->name === 'Super Admin');
                                        @endphp
                                        <td class="py-3 px-4 text-center border-r border-gray-100 last:border-r-0">
                                            @if($isSuperAdmin)
                                                <div class="inline-flex items-center justify-center w-6 h-6 rounded-lg bg-emerald-50 text-emerald-600 font-bold text-xs" title="Super Admin has all permissions">
                                                    ✓
                                                </div>
                                            @else
                                                <label class="inline-flex items-center justify-center p-1 rounded-lg hover:bg-navy-50 cursor-pointer">
                                                    <input type="checkbox" 
                                                           name="matrix[{{ $role->id }}][]" 
                                                           value="{{ $perm->name }}"
                                                           {{ $hasPermission ? 'checked' : '' }}
                                                           class="w-4 h-4 rounded text-navy border-gray-300 focus:ring-navy transition-all">
                                                </label>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>

                </table>
            </div>

            <!-- Footer Save Bar -->
            <div class="p-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <span class="text-xs text-gray-500 font-medium">Checkboxes reflect real-time role privileges upon saving.</span>
                <button type="submit" class="px-6 py-2 rounded-xl bg-navy hover:bg-[#062663] text-white font-bold text-xs shadow-md shadow-navy/20">
                    Apply Matrix Changes
                </button>
            </div>
        </div>

    </form>

</div>
@endsection
