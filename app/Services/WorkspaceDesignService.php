<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkspaceDesignAsset;
use App\Models\WorkspaceDesignProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WorkspaceDesignService
{
    public const MAX_BYTES = 2097152;

    public function schoolId(User $owner): int
    {
        abort_unless($owner->isActive(), 403);
        $school = app(SchoolContext::class)->current();
        abort_unless($school && $school->is_active, 404);
        return (int) $school->id;
    }

    public function owned(User $owner): Builder
    {
        return WorkspaceDesignProject::query()->where('owner_id', $owner->id)->where('school_id', $this->schoolId($owner));
    }

    public function project(User $owner, string $publicId): WorkspaceDesignProject
    {
        abort_unless(Str::isUuid($publicId), 404);
        return $this->owned($owner)->where('public_id', $publicId)->firstOrFail();
    }

    public function validateDocument(array $document, WorkspaceDesignProject $project): array
    {
        $fail = static function (string $message): never {
            throw ValidationException::withMessages(['document' => $message]);
        };
        if (strlen(json_encode($document, JSON_THROW_ON_ERROR)) > self::MAX_BYTES) $fail('Project JSON exceeds 2 MiB.');
        if (array_diff(array_keys($document), ['width', 'height', 'pages'])) $fail('Unknown document fields.');
        foreach (['width', 'height'] as $side) {
            if (!isset($document[$side]) || !is_int($document[$side]) || $document[$side] < 50 || $document[$side] > 2400) $fail('Canvas sides must be integers between 50 and 2400.');
        }
        $pages = $document['pages'] ?? null;
        if (!is_array($pages) || !array_is_list($pages) || count($pages) < 1 || count($pages) > 10) $fail('Use between 1 and 10 pages.');
        if ($document['width'] * $document['height'] * count($pages) > 20000000) $fail('Total canvas area exceeds 20 megapixels.');
        $allowedSources = WorkspaceDesignAsset::query()->where('workspace_design_project_id', $project->id)
            ->pluck('public_id')->map(fn ($id) => '/workspace/design/'.$project->public_id.'/assets/'.$id)->all();
        $nodes = 0;
        $objects = 0;
        $types = ['rect', 'circle', 'triangle', 'line', 'textbox', 'text', 'itext', 'i-text', 'image', 'group', 'path', 'polygon', 'polyline', 'ellipse', 'linear', 'radial'];
        $walk = function ($value, int $depth = 0) use (&$walk, &$nodes, &$objects, $allowedSources, $types, $fail): void {
            if (++$nodes > 50000 || $depth > 32) $fail('Project structure exceeds safe limits.');
            if (is_float($value) && (!is_finite($value) || abs($value) > 1000000)) $fail('Invalid numeric value.');
            if (is_int($value) && abs($value) > 1000000) $fail('Numeric value exceeds bounds.');
            if (!is_array($value)) return;
            if (isset($value['type']) && (!is_string($value['type']) || !in_array(strtolower($value['type']), $types, true))) $fail('Unsupported Fabric object type.');
            if (isset($value['objects'])) {
                if (!is_array($value['objects']) || !array_is_list($value['objects'])) $fail('Invalid object list.');
                $objects += count($value['objects']);
                if ($objects > 1000) $fail('Use at most 1000 objects per project.');
            }
            foreach ($value as $key => $child) {
                if (in_array($key, ['url', 'href', 'source'], true)) $fail('External or inline asset sources are disabled.');
                if ($key === 'src' && (!is_string($child) || !in_array($child, $allowedSources, true))) $fail('Only this project’s authorized private assets are permitted.');
                if (($value['type'] ?? '') && strtolower((string) $value['type']) === 'image' && !isset($value['src'])) $fail('Images require a private asset source.');
                $walk($child, $depth + 1);
            }
        };
        $ids = [];
        foreach ($pages as $page) {
            if (!is_array($page) || array_diff(array_keys($page), ['id', 'title', 'canvas'])) $fail('Invalid page fields.');
            if (!is_string($page['id'] ?? null) || !Str::isUuid($page['id']) || in_array($page['id'], $ids, true)) $fail('Page IDs must be unique UUIDs.');
            $ids[] = $page['id'];
            if (!is_string($page['title'] ?? null) || mb_strlen($page['title']) < 1 || mb_strlen($page['title']) > 100) $fail('Page titles must have 1–100 characters.');
            if (!is_array($page['canvas'] ?? null) || !isset($page['canvas']['objects']) || !is_array($page['canvas']['objects'])) $fail('Each page requires Fabric canvas JSON with objects.');
            $walk($page['canvas']);
        }
        return $document;
    }
}
