<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Notice;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteSettingController extends Controller
{
    public function index(): View
    {
        $settings = SiteSetting::all()->groupBy('group');
        $notices = Notice::latest()->get();

        return view('admin.settings.index', compact('settings', 'notices'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        // 1. Handle Logo Image Upload or Text Path
        if ($request->hasFile('site_logo_file')) {
            $request->validate([
                'site_logo_file' => ['image', 'mimes:png,jpg,jpeg,svg,webp', 'max:5120'],
            ]);

            $path = $request->file('site_logo_file')->store('settings', 'public');
            SiteSetting::updateOrCreate(
                ['key' => 'site_logo'],
                [
                    'value' => '/storage/' . $path,
                    'group' => 'branding',
                    'type' => 'image',
                    'label' => 'Header & Brand Logo Image',
                ]
            );
        } elseif ($request->filled('site_logo')) {
            SiteSetting::updateOrCreate(
                ['key' => 'site_logo'],
                [
                    'value' => $request->input('site_logo'),
                    'group' => 'branding',
                    'type' => 'image',
                    'label' => 'Header & Brand Logo Image',
                ]
            );
        }

        // 2. Handle Top Header Welcome Text
        if ($request->has('top_header_text')) {
            SiteSetting::updateOrCreate(
                ['key' => 'top_header_text'],
                [
                    'value' => $request->input('top_header_text', 'Welcome to Naoero Airline'),
                    'group' => 'header',
                    'type' => 'text',
                    'label' => 'Top Header Welcome Announcement',
                ]
            );
        }

        // 3. Handle Dynamic Top Header Menu
        if ($request->has('menu_labels') && is_array($request->input('menu_labels'))) {
            $labels = $request->input('menu_labels', []);
            $routes = $request->input('menu_routes', []);
            $icons = $request->input('menu_icons', []);
            $externals = $request->input('menu_externals', []);

            $menuItems = [];
            foreach ($labels as $idx => $label) {
                if (trim($label) !== '') {
                    $isExternal = isset($externals[$idx]) && (
                        $externals[$idx] === '1' || 
                        $externals[$idx] === 1 || 
                        $externals[$idx] === true || 
                        $externals[$idx] === 'true'
                    );

                    $menuItems[] = [
                        'label' => trim($label),
                        'route' => trim($routes[$idx] ?? '#'),
                        'icon' => trim($icons[$idx] ?? 'Ticket'),
                        'is_external' => $isExternal,
                    ];
                }
            }

            SiteSetting::updateOrCreate(
                ['key' => 'top_header_menu'],
                [
                    'value' => json_encode($menuItems, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                    'group' => 'header',
                    'type' => 'json',
                    'label' => 'Top Header Quick Menu Navigation',
                ]
            );
        }

        // 4. Process remaining regular key-value settings
        $excluded = ['_token', '_method', 'site_logo_file', 'site_logo', 'top_header_text', 'menu_labels', 'menu_routes', 'menu_icons', 'menu_externals'];
        $inputs = $request->except($excluded);

        foreach ($inputs as $key => $value) {
            if ($key !== 'top_header_menu') {
                SiteSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
        }

        ActivityLog::log('updated', 'settings', 'Updated global site settings, logo, header text, and top menu navigation');

        return back()->with('success', 'Site settings, logo, header text, and navigation updated successfully.');
    }

    public function updateNotice(Request $request, Notice $notice): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:1000'],
            'link' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $notice->update($validated);

        ActivityLog::log('updated', 'notices', 'Updated notice bar alert');

        return back()->with('success', 'Notice bar updated.');
    }
}
