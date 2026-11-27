@extends('layouts.admin')

@section('title', 'Careers & Job Postings')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Careers & Job Openings</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $careers->total() }} Positions
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage flight crew, engineering, corporate, and ground handling vacancies.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.job-applications.index') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-navy text-white hover:bg-navy-700 font-bold text-sm shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>View Applications</span>
            </a>

            <a href="{{ route('admin.careers.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Post Job</span>
            </a>
        </div>
    </div>

    <!-- Career Positions Table -->
    <div class="naoero-card bg-white border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase font-extrabold text-slate-500 border-b border-slate-200 tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Position Title</th>
                        <th class="px-5 py-3.5">Department</th>
                        <th class="px-5 py-3.5">Location</th>
                        <th class="px-5 py-3.5">Type</th>
                        <th class="px-5 py-3.5 text-center">Applications</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($careers as $job)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-5 py-3.5">
                                <div>
                                    <a href="{{ route('admin.careers.edit', $job) }}" class="font-bold text-navy hover:text-accent-hover transition-colors">
                                        {{ $job->title }}
                                    </a>
                                    <p class="text-xs text-slate-500 line-clamp-1 max-w-xs">{{ $job->short_description }}</p>
                                </div>
                            </td>

                            <td class="px-5 py-3.5 font-medium text-slate-700">
                                {{ $job->department ?: 'Operations' }}
                            </td>

                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center gap-1 text-slate-600 text-xs font-semibold">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    {{ $job->location }}
                                </span>
                            </td>

                            <td class="px-5 py-3.5">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700">
                                    {{ $job->employment_type }}
                                </span>
                            </td>

                            <td class="px-5 py-3.5 text-center">
                                <a href="{{ route('admin.job-applications.index', ['career_id' => $job->id]) }}" 
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-navy-50 text-navy hover:bg-navy-100 transition-colors">
                                    <span>{{ $job->applications_count }}</span>
                                    <span class="text-[10px] text-slate-400">candidatures</span>
                                </a>
                            </td>

                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold {{ $job->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $job->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                    <span>{{ $job->is_active ? 'Open' : 'Closed' }}</span>
                                </span>
                            </td>

                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.careers.edit', $job) }}" 
                                       class="p-1.5 text-slate-400 hover:text-navy hover:bg-slate-100 rounded-lg transition-colors"
                                       title="Edit Job">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>

                                    <form method="POST" action="{{ route('admin.careers.destroy', $job) }}" 
                                          onsubmit="return confirm('Are you sure you want to delete \'{{ $job->title }}\'?');"
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                                title="Delete Job">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-base font-bold text-slate-600">No vacancies currently open</p>
                                <p class="text-xs text-slate-400 mt-1">Post a new job opening to attract qualified aviation talent.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($careers->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $careers->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
