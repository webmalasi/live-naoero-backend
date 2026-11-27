@extends('layouts.admin')

@section('title', 'System Permissions')

@section('content')
<div class="space-y-6" x-data="{
    createModal: false,
    editModal: false,
    editId: null,
    editName: '',
    editActionUrl: ''
}">

    <!-- HEADER & ACTION BAR -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-navy">Granular Permissions Directory</h1>
            <p class="text-xs text-gray-500 mt-0.5">Manage fine-grained security authorizations mapped to roles and users.</p>
        </div>

        <button type="button" 
                @click="createModal = true"
                class="px-4 py-2 rounded-xl bg-navy hover:bg-[#062663] text-white font-bold text-xs shadow-md shadow-navy/20 hover:scale-105 transition-all flex items-center gap-2">
            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            <span>+ Add New Permission</span>
        </button>
    </div>

    <!-- MODULE PERMISSIONS GRID -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($groupedPermissions as $module => $modulePerms)
            <div class="naoero-card p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-accent"></span>
                        <h2 class="text-base font-extrabold text-navy uppercase tracking-wider">{{ $module }}</h2>
                    </div>
                    <span class="text-xs font-bold text-navy bg-navy-50 px-2 py-0.5 rounded-full">{{ count($modulePerms) }}</span>
                </div>

                <div class="space-y-2.5">
                    @foreach($modulePerms as $perm)
                        <div class="p-3 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between gap-3 hover:bg-navy-50/40 transition-colors">
                            <div class="min-w-0">
                                <p class="text-xs font-mono font-bold text-navy truncate">{{ $perm->name }}</p>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @forelse($perm->roles as $role)
                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-white text-gray-700 border border-gray-200">
                                            {{ $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-[10px] text-gray-400 italic">No roles</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button"
                                        @click="editModal = true; editId = {{ $perm->id }}; editName = '{{ $perm->name }}'; editActionUrl = '{{ route('admin.permissions.update', $perm) }}'"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-navy hover:bg-white transition-colors" title="Edit Permission">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>

                                <form method="POST" action="{{ route('admin.permissions.destroy', $perm) }}" onsubmit="return confirm('Delete permission {{ $perm->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-white transition-colors" title="Delete">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <!-- CREATE PERMISSION MODAL -->
    <div x-show="createModal" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-navy-950/70 backdrop-blur-sm">
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-gray-100" @click.outside="createModal = false">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold text-navy text-lg">Create New Permission</h3>
                <button @click="createModal = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.permissions.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-navy mb-1">Module Group</label>
                    <input type="text" name="module" required placeholder="e.g. flights, tickets, reports" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-navy mb-1">Action Name</label>
                    <input type="text" name="action" required placeholder="e.g. view, create, edit, archive" class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-navy mb-1">Assign to Roles (Optional)</label>
                    <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                        @foreach($roles as $r)
                            <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                <input type="checkbox" name="roles[]" value="{{ $r->name }}" class="rounded text-navy border-gray-300">
                                <span>{{ $r->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" @click="createModal = false" class="px-4 py-2 rounded-xl text-gray-600 hover:bg-gray-100 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-navy text-white font-bold text-xs hover:bg-[#062663] shadow-md shadow-navy/20">
                        Create Permission
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT PERMISSION MODAL -->
    <div x-show="editModal" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-navy-950/70 backdrop-blur-sm">
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-gray-100" @click.outside="editModal = false">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold text-navy text-lg">Edit Permission</h3>
                <button @click="editModal = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
            </div>

            <form :action="editActionUrl" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-navy mb-1">Permission Name</label>
                    <input type="text" name="name" x-model="editName" required class="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-mono focus:outline-none focus:border-navy">
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" @click="editModal = false" class="px-4 py-2 rounded-xl text-gray-600 hover:bg-gray-100 font-bold text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-navy text-white font-bold text-xs hover:bg-[#062663] shadow-md shadow-navy/20">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
