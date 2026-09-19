<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'logoUrl' => SystemSetting::logoUrl(),
            'loginMediaUrl' => SystemSetting::loginMediaUrl(),
            'loginMediaType' => SystemSetting::valueFor('login_media_type'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'logo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg,ico', 'max:4096'],
        ]);

        $oldPath = SystemSetting::valueFor('brand_logo');
        $path = $validated['logo']->store('branding', 'public');

        SystemSetting::updateOrCreate(['key' => 'brand_logo'], ['value' => $path]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('status', 'Brand logo updated across CrisisPulse AI.');
    }

    public function destroy(): RedirectResponse
    {
        $path = SystemSetting::valueFor('brand_logo');

        if ($path) {
            Storage::disk('public')->delete($path);
        }

        SystemSetting::query()->where('key', 'brand_logo')->delete();

        return back()->with('status', 'Custom logo removed. CrisisPulse AI is using its default mark.');
    }

    public function updateLoginMedia(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login_media' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov,webm', 'max:51200'],
        ]);

        $oldPath = SystemSetting::valueFor('login_media_path');
        $file = $validated['login_media'];
        $path = $file->store('branding/login', 'public');
        $type = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';

        SystemSetting::updateOrCreate(['key' => 'login_media_path'], ['value' => $path]);
        SystemSetting::updateOrCreate(['key' => 'login_media_type'], ['value' => $type]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('status', 'Login page media updated across CrisisPulse AI.');
    }

    public function destroyLoginMedia(): RedirectResponse
    {
        $path = SystemSetting::valueFor('login_media_path');

        if ($path) {
            Storage::disk('public')->delete($path);
        }

        SystemSetting::query()->whereIn('key', ['login_media_path', 'login_media_type'])->delete();

        return back()->with('status', 'Login page media removed.');
    }
}
