{{-- A customer's email and, when it differs, the username they sign in with (one email can have several accounts). --}}
@php
    $identityEmail = $email ?? null;
    $identityLogin = $login ?? null;
@endphp
<span>{{ $identityEmail ?? $identityLogin ?? '—' }}</span>@if ($identityLogin && strcasecmp($identityLogin, (string) $identityEmail) !== 0)<span class="block text-[11px] text-slate-400">Username: <span class="font-mono">{{ $identityLogin }}</span></span>@endif
