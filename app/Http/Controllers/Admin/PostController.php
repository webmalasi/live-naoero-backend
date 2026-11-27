<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DropdownOption;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $query = Post::query();

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $posts = $query->latestFirst()->paginate(12)->withQueryString();
        
        $blogCategories = DropdownOption::getValues('blog_categories');
        $newsCategories = DropdownOption::getValues('news_categories');
        $categories = array_unique(array_merge($blogCategories, $newsCategories, DropdownOption::getValues('post_categories')));

        return view('admin.posts.index', compact('posts', 'categories', 'blogCategories', 'newsCategories'));
    }

    public function create(): View
    {
        $blogCategories = DropdownOption::getValues('blog_categories');
        $newsCategories = DropdownOption::getValues('news_categories');
        $categories = array_unique(array_merge($blogCategories, $newsCategories, DropdownOption::getValues('post_categories')));

        return view('admin.posts.create', compact('categories', 'blogCategories', 'newsCategories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:posts,slug'],
            'type' => ['required', 'string', 'in:blog,news'],
            'category' => ['required', 'string', 'max:100'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
            'author' => ['nullable', 'string', 'max:100'],
            'image_file' => ['nullable', 'image', 'max:5120'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $data = $validated;
        $data['slug'] = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        $data['type'] = $validated['type'] ?? 'blog';
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_published'] = $request->boolean('is_published', true);
        $data['published_at'] = $validated['published_at'] ?? now();

        if ($request->hasFile('image_file')) {
            $data['image'] = $request->file('image_file')->store('posts', 'public');
        } elseif (! empty($validated['image_url'])) {
            $data['image'] = $validated['image_url'];
        }

        $post = Post::create($data);

        ActivityLog::log('created', 'posts', "Created {$post->type} article '{$post->title}'");

        return redirect()->route('admin.posts.index')->with('success', ucfirst($post->type) . " article '{$post->title}' created successfully.");
    }

    public function edit(Post $post): View
    {
        $blogCategories = DropdownOption::getValues('blog_categories');
        $newsCategories = DropdownOption::getValues('news_categories');
        $categories = array_unique(array_merge($blogCategories, $newsCategories, DropdownOption::getValues('post_categories')));

        return view('admin.posts.edit', compact('post', 'categories', 'blogCategories', 'newsCategories'));
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($post->id)],
            'type' => ['required', 'string', 'in:blog,news'],
            'category' => ['required', 'string', 'max:100'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
            'author' => ['nullable', 'string', 'max:100'],
            'image_file' => ['nullable', 'image', 'max:5120'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $data = $validated;
        $data['slug'] = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        $data['type'] = $validated['type'] ?? $post->type;
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_published'] = $request->boolean('is_published');

        if ($request->hasFile('image_file')) {
            if ($post->image && ! Str::startsWith($post->image, ['http://', 'https://'])) {
                Storage::disk('public')->delete($post->image);
            }
            $data['image'] = $request->file('image_file')->store('posts', 'public');
        } elseif ($request->filled('image_url')) {
            $data['image'] = $validated['image_url'];
        }

        $post->update($data);

        ActivityLog::log('updated', 'posts', "Updated {$post->type} article '{$post->title}'");

        return redirect()->route('admin.posts.index')->with('success', ucfirst($post->type) . " article '{$post->title}' updated successfully.");
    }

    public function destroy(Post $post): RedirectResponse
    {
        $title = $post->title;
        $type = $post->type;
        if ($post->image && ! Str::startsWith($post->image, ['http://', 'https://'])) {
            Storage::disk('public')->delete($post->image);
        }
        $post->delete();

        ActivityLog::log('deleted', 'posts', "Deleted {$type} article '{$title}'");

        return redirect()->route('admin.posts.index')->with('success', ucfirst($type) . " article '{$title}' deleted successfully.");
    }
}
