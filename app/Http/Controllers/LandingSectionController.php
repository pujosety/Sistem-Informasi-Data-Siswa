<?php

namespace App\Http\Controllers;

use App\Models\LandingSection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingSectionController extends Controller
{
    public function index(): View
    {
        return view('admin.landing.index', [
            'sections' => LandingSection::query()
                ->where('page_key', 'home')
                ->orderBy('position')
                ->get(),
        ]);
    }

    public function edit(LandingSection $landingSection): View
    {
        return view('admin.landing.edit', ['section' => $landingSection]);
    }

    public function update(Request $request, LandingSection $landingSection): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(LandingSection::TYPES)],
            'title' => ['nullable', 'string', 'max:190'],
            'subtitle' => ['nullable', 'string', 'max:190'],
            'body' => ['nullable', 'string', 'max:10000'],
            'position' => ['required', 'numeric', 'min:0'],
            'is_enabled' => ['nullable', 'boolean'],
            'content' => ['nullable', 'json'],
            'media_id' => ['nullable', 'integer', 'exists:cms_media,id'],
        ]);

        $landingSection->update([
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'subtitle' => $data['subtitle'] ?? null,
            'body' => $data['body'] ?? null,
            'position' => $data['position'],
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'content' => json_decode($data['content'] ?? '{}', true) ?: [],
            'media_id' => $data['media_id'] ?? null,
        ]);

        return redirect()->route('admin.landing.index')->with('success', 'Bagian halaman utama diperbarui.');
    }
}
