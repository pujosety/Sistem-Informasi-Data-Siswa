<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SchoolContextController extends Controller
{
    public function switch(Request $request, School $school, SchoolContext $context): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
        abort_unless($school->is_active, 404);

        $context->use($school);
        $request->session()->put('active_school_slug', $school->slug);

        return back()->with('success', 'Sekolah aktif diubah menjadi '.$school->name.'.');
    }
}
