{{--
    Every permission for one staff account: as its roles give it, allowed
    for this account, or blocked for this account even when a role gives it.
    Shows live whether each one ends up on, following the role boxes above.
    Needs: $roles (with permissions), $permissions (grouped), $direct, $blocked.
--}}
@php
    $rolePermissionMap = $roles->mapWithKeys(fn ($role) => [$role->name => $role->permissions->pluck('name')->values()])->all();
    $initialStates = [];
    foreach ($permissions->flatten() as $perm) {
        $initialStates[$perm->name] = in_array($perm->name, $blocked, true) ? 'block' : (in_array($perm->name, $direct, true) ? 'allow' : 'role');
    }
    $initialStates = array_merge($initialStates, array_intersect_key((array) old('access', []), $initialStates));
@endphp

<script>
    function staffAccessMatrix(rolePermissions, states) {
        return {
            rolePermissions,
            states,
            selectedRoles: [],
            init() {
                const boxes = document.querySelectorAll('input[name="roles[]"]');
                const read = () => { this.selectedRoles = Array.from(boxes).filter(b => b.checked).map(b => b.value); };
                boxes.forEach(b => b.addEventListener('change', read));
                read();
            },
            get isSuperAdmin() { return this.selectedRoles.includes(@js(\App\Services\Auth\AdminAccess::SUPER_ADMIN)); },
            fromRole(name) { return this.selectedRoles.some(r => (this.rolePermissions[r] || []).includes(name)); },
            isOn(name) {
                if (this.isSuperAdmin) { return true; }
                const state = this.states[name];
                return state === 'allow' || (state === 'role' && this.fromRole(name));
            },
            setAll(names, state) { names.forEach(n => this.states[n] = state); },
            get changedCount() { return Object.values(this.states).filter(s => s !== 'role').length; },
        };
    }
</script>

<div class="naoero-card p-6 bg-white border border-slate-200 space-y-4"
     x-data="staffAccessMatrix(@js($rolePermissionMap), @js($initialStates))">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-3">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">5</div>
            <div>
                <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Permissions for this account only</h2>
                <p class="text-xs text-ink-muted">Changes here apply to this person only, not to everyone with the same role.
                    <b>Role</b> = as the roles above give it, <b>Allow</b> = on for this account, <b>Block</b> = off for this account even if a role gives it.</p>
            </div>
        </div>
        <span class="shrink-0 text-[11px] font-bold px-2.5 py-1 rounded-full bg-amber-50 text-amber-900 border border-amber-200"
              x-text="changedCount + ' set for this account'"></span>
    </div>

    <div x-show="isSuperAdmin" x-cloak class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900">
        Super Admin always has every permission: blocks are ignored while that role is ticked.
    </div>

    <div class="space-y-4">
        @foreach($permissions as $module => $modulePermissions)
            @php $names = $modulePermissions->pluck('name')->values(); @endphp
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200/60 pb-1.5">
                    <span class="text-xs font-black text-navy uppercase tracking-wider">{{ $module }}</span>
                    <div class="flex items-center gap-1 text-[10px] font-bold">
                        <button type="button" @click="setAll(@js($names), 'role')" class="px-2 py-0.5 rounded bg-white border border-slate-200 text-slate-600 hover:text-navy">All: role</button>
                        <button type="button" @click="setAll(@js($names), 'allow')" class="px-2 py-0.5 rounded bg-white border border-slate-200 text-emerald-700 hover:bg-emerald-50">All: allow</button>
                        <button type="button" @click="setAll(@js($names), 'block')" class="px-2 py-0.5 rounded bg-white border border-slate-200 text-rose-700 hover:bg-rose-50">All: block</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-2">
                    @foreach($modulePermissions as $perm)
                        @php $n = $perm->name; @endphp
                        <div class="flex items-center justify-between gap-2 p-2 rounded-lg bg-white border border-slate-100">
                            <div class="flex items-start gap-2 min-w-0">
                                <span class="mt-0.5 shrink-0 text-[9px] font-black px-1.5 py-0.5 rounded"
                                      :class="isOn(@js($n)) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-500'"
                                      x-text="isOn(@js($n)) ? 'ON' : 'OFF'"></span>
                                @include('admin.partials.permission-label', ['perm' => $perm, 'size' => 'text-[11px]'])
                            </div>
                            <div class="shrink-0 inline-flex rounded-lg border border-slate-200 overflow-hidden text-[10px] font-bold">
                                <label class="px-2 py-1 cursor-pointer" :class="states[@js($n)] === 'role' ? 'bg-navy text-white' : 'text-slate-500 hover:bg-slate-50'"
                                       :title="fromRole(@js($n)) ? 'A role above gives this' : 'No role above gives this'">
                                    <input type="radio" class="sr-only" name="access[{{ $n }}]" value="role" x-model="states[@js($n)]" @checked($initialStates[$n] === 'role')>
                                    Role <span x-text="fromRole(@js($n)) ? '✓' : '✗'"></span>
                                </label>
                                <label class="px-2 py-1 cursor-pointer border-l border-slate-200" :class="states[@js($n)] === 'allow' ? 'bg-emerald-600 text-white' : 'text-emerald-700 hover:bg-emerald-50'">
                                    <input type="radio" class="sr-only" name="access[{{ $n }}]" value="allow" x-model="states[@js($n)]" @checked($initialStates[$n] === 'allow')>
                                    Allow
                                </label>
                                <label class="px-2 py-1 cursor-pointer border-l border-slate-200" :class="states[@js($n)] === 'block' ? 'bg-rose-600 text-white' : 'text-rose-700 hover:bg-rose-50'">
                                    <input type="radio" class="sr-only" name="access[{{ $n }}]" value="block" x-model="states[@js($n)]" @checked($initialStates[$n] === 'block')>
                                    Block
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
