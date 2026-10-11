<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Post;
use App\Services\ModuleService;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CmsWorkspaceController extends Controller
{
    public function __construct(
        private readonly ModuleService $modules,
        private readonly WorkspaceService $workspaces,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->can('cms.view'), 403);
        abort_unless($this->modules->isEnabled('cms'), 404);

        return view('workspaces.cms', [
            'workspaces' => $this->workspaces->forUser($user),
            'metrics' => [
                'posts' => Post::query()->posts()->count(),
                'pages' => Post::query()->pages()->count(),
                'drafts' => Post::query()->where('status', Post::DRAFT)->count(),
                'review' => Post::query()->where('status', Post::PENDING)->count(),
                'media' => $user->can('cms.media.manage') ? Media::query()->count() : null,
            ],
            'recentPosts' => Post::query()
                ->orderByDesc('updated_at')
                ->limit(6)
                ->get(),
        ]);
    }
}
