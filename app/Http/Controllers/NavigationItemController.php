<?php

namespace App\Http\Controllers;

use App\Models\NavigationItem;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NavigationItemController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(): View
    {
        return view('admin.cms.navigation.index', [
            'items' => NavigationItem::query()->orderBy('position')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $item = NavigationItem::create($data);
        $this->audit->log('cms.navigation.created', $item, "Menambahkan menu publik: {$item->label}");

        return redirect()->route('admin.cms.navigation.index')->with('success', 'Menu berhasil ditambahkan.');
    }

    public function update(Request $request, NavigationItem $navigationItem): RedirectResponse
    {
        $data = $this->validated($request);
        $navigationItem->update($data);
        $this->audit->log('cms.navigation.updated', $navigationItem, "Mengubah menu publik: {$navigationItem->label}");

        return redirect()->route('admin.cms.navigation.index')->with('success', 'Menu berhasil diperbarui.');
    }

    public function destroy(NavigationItem $navigationItem): RedirectResponse
    {
        $label = $navigationItem->label;
        $navigationItem->delete();
        $this->audit->log('cms.navigation.deleted', $navigationItem, "Menghapus menu publik: {$label}");

        return redirect()->route('admin.cms.navigation.index')->with('success', 'Menu berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                $value = trim((string) $value);
                if (preg_match('/[\x00-\x20\\\\]/', $value)) {
                    $fail('URL tidak boleh memuat spasi, karakter kontrol, atau backslash.');

                    return;
                }

                if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
                    return;
                }

                $parts = parse_url($value);
                if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
                    $fail('Gunakan path internal (diawali /) atau URL HTTPS yang lengkap.');
                }
            }],
            'position' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $data['url'] = trim($data['url']);
        $data['is_enabled'] = $request->boolean('is_enabled');
        $data['open_in_new_tab'] = $request->boolean('open_in_new_tab');

        return $data;
    }
}
