<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function index(): View
    {
        return view('admin.contact-messages.index', [
            'messages' => ContactMessage::query()->latest()->paginate(20),
        ]);
    }

    public function update(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', [
                ContactMessage::NEW,
                ContactMessage::READ,
                ContactMessage::RESOLVED,
            ])],
        ]);

        $contactMessage->status = $data['status'];
        $contactMessage->read_at = $data['status'] === ContactMessage::NEW
            ? null
            : ($contactMessage->read_at ?? now());
        $contactMessage->save();

        return redirect()->route('admin.contact-messages')->with('success', 'Status pesan diperbarui.');
    }
}
