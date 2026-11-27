@extends('layouts.admin')

@section('title', 'News & Blog Posts Management')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">News & Blog Articles</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $posts->total() }} Articles
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage destination travel guides, airline announcements, and Pacific Island stories.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.posts.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Write New Article</span>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="naoero-card p-4 bg-white border border-slate-200">
        <form method="GET" action="{{ route('admin.posts.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div class="relative">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search title, author, snippet..." 
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <div>
                <select name="type" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <option value="">All Types (Blog & News)</option>
                    <option value="blog" {{ request('type') === 'blog' ? 'selected' : '' }}>✍️ Blog Articles (/api/blogs)</option>
                    <option value="news" {{ request('type') === 'news' ? 'selected' : '' }}>📰 News Hub (/api/news)</option>
                </select>
            </div>

            <div>
                <select name="category" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-4 py-2 rounded-xl bg-navy text-white text-sm font-bold hover:bg-navy-700 transition-colors">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'type', 'category']))
                    <a href="{{ route('admin.posts.index') }}" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Posts Table -->
    <div class="naoero-card bg-white border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase font-extrabold text-slate-500 border-b border-slate-200 tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Article</th>
                        <th class="px-5 py-3.5 text-center">Type</th>
                        <th class="px-5 py-3.5">Category</th>
                        <th class="px-5 py-3.5">Author</th>
                        <th class="px-5 py-3.5 text-center">Published Date</th>
                        <th class="px-5 py-3.5 text-center">Featured</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($posts as $post)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-14 h-10 rounded-lg overflow-hidden bg-slate-100 shrink-0 border border-slate-200">
                                        <img src="{{ $post->image_url }}" 
                                             alt="{{ $post->title }}" 
                                             class="w-full h-full object-cover"
                                             onerror="this.src='https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80'">
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.posts.edit', $post) }}" class="font-bold text-navy hover:text-accent-hover transition-colors">
                                            {{ $post->title }}
                                        </a>
                                        <p class="text-xs text-slate-500 line-clamp-1 max-w-xs">{{ $post->excerpt ?: 'No excerpt provided.' }}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-3.5 text-center">
                                @if($post->type === 'news')
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                        📰 News
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200">
                                        ✍️ Blog
                                    </span>
                                @endif
                            </td>

                            <td class="px-5 py-3.5">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-navy-50 text-navy border border-navy-100">
                                    {{ $post->category }}
                                </span>
                            </td>

                            <td class="px-5 py-3.5 text-slate-600 font-medium">
                                {{ $post->author ?: 'Naoero Airlines' }}
                            </td>

                            <td class="px-5 py-3.5 text-center text-xs text-slate-500 font-mono">
                                {{ $post->published_at ? $post->published_at->format('d M Y') : 'Draft' }}
                            </td>

                            <td class="px-5 py-3.5 text-center">
                                @if($post->is_featured)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                        <svg class="w-3 h-3 fill-amber-500" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        Featured
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">Regular</span>
                                @endif
                            </td>

                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold {{ $post->is_published ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $post->is_published ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    <span>{{ $post->is_published ? 'Published' : 'Draft' }}</span>
                                </span>
                            </td>

                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.posts.edit', $post) }}" 
                                       class="p-1.5 text-slate-500 hover:text-accent-hover hover:bg-amber-50 rounded-lg transition-colors" 
                                       title="Edit Article">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>

                                    <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" 
                                          onsubmit="return confirm('Are you sure you want to delete post \'{{ $post->title }}\'?');" 
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" 
                                                title="Delete Article">
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                                </svg>
                                <p class="text-base font-bold text-slate-600">No articles found</p>
                                <p class="text-xs text-slate-400 mt-1">Start writing blog and news stories for your visitors.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($posts->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $posts->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
