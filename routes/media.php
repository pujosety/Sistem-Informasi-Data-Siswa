<?php

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CMS-MEDIA
|--------------------------------------------------------------------------
| The media library, in its own file.
|
| `cms.media.manage` is a SEPARATE permission from `cms.posts.edit`, and the
| split is enforced here rather than inside the controller. The library is a
| shared resource — the school logo, the headmaster's photo — so an editor who
| can fix a comma in a headline must not be able to delete the logo. Reusing
| `cms.posts.edit` on the delete route would have been one line shorter and
| would have handed a bounded permission an unbounded surface, invisible in the
| role matrix.
|
| Reading and attaching are wider than managing on purpose: an article cannot
| have an image unless its writer may choose one. Uploading is gated on
| `create` in the controller (media.manage OR posts.edit), the destructive
| route on `cms.media.manage` itself.
|
| These routes sit under the admin prefix and the admin middleware group, so
| they are auth + dashboard.admin.view + the permission below.
|
*/

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:dashboard.admin.view'])->group(function () {
    // --- read / write, wider than managing -------------------------------
    Route::middleware('can:cms.view')->group(function () {
        Route::get('/media', [MediaController::class, 'index'])->name('media.index');
    });

    // --- attach images to an article -------------------------------------
    // Gated on editing the ARTICLE, not on managing the library: this writes
    // to cms_post_media, not to cms_media.
    Route::middleware('can:cms.posts.edit')->group(function () {
        Route::post('/konten/{post}/gambar', [MediaController::class, 'attachToPost'])->name('cms.media.attach');
    });

    // --- the library itself ----------------------------------------------
    Route::middleware('can:cms.media.manage')->group(function () {
        Route::post('/media', [MediaController::class, 'store'])->name('media.store');
        Route::put('/media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
        Route::post('/media/{media}/kembalikan', [MediaController::class, 'restore'])->name('media.restore');
    });
});
