@extends('layouts.admin')

@section('title', 'Testimonials Management')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Passenger Testimonials</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $testimonials->total() }} Testimonials
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage passenger stories and reviews showcased across the website.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.testimonials.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Testimonial</span>
            </a>
        </div>
    </div>

    <!-- Testimonials Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($testimonials as $item)
            <div class="naoero-card p-6 bg-white border border-slate-200 flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <!-- Rating Stars & Status -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1 text-amber-400">
                            @for($i = 1; $i <= 5; $i++)
                                <svg class="w-4 h-4 {{ $i <= $item->rating ? 'fill-amber-400 text-amber-400' : 'text-slate-200 fill-slate-200' }}" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            @endfor
                        </div>
                        <span class="px-2 py-0.5 text-xs rounded-full font-bold {{ $item->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                            {{ $item->is_active ? 'Active' : 'Hidden' }}
                        </span>
                    </div>

                    <!-- Quote -->
                    <p class="text-sm text-slate-700 italic leading-relaxed">
                        "{{ $item->quote }}"
                    </p>
                </div>

                <!-- Passenger Info & Actions -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden bg-navy-50 flex items-center justify-center shrink-0 border border-navy/10">
                            @if($item->image_url)
                                <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-xs font-bold text-navy">{{ substr($item->name, 0, 2) }}</span>
                            @endif
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-navy">{{ $item->name }}</h4>
                            <p class="text-[11px] text-slate-400">{{ $item->location ?: 'Passenger' }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        <a href="{{ route('admin.testimonials.edit', $item) }}" 
                           class="p-1.5 text-slate-400 hover:text-navy hover:bg-slate-100 rounded-lg transition-colors"
                           title="Edit Testimonial">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('admin.testimonials.destroy', $item) }}" 
                              onsubmit="return confirm('Are you sure you want to delete this testimonial?');"
                              class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                    title="Delete Testimonial">
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p class="text-base font-bold text-slate-600">No testimonials found</p>
                <p class="text-xs text-slate-400 mt-1">Add real passenger feedback to display on the home page.</p>
            </div>
        @endforelse
    </div>

    @if($testimonials->hasPages())
        <div class="naoero-card p-4 bg-white border border-slate-200">
            {{ $testimonials->links() }}
        </div>
    @endif

</div>
@endsection
