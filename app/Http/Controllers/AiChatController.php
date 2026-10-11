<?php

namespace App\Http\Controllers;

use App\Services\AI\CompanionContextService;
use App\Services\AI\SchoolAssistantService;
use App\Services\SchoolContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AiChatController extends Controller
{
    public function __invoke(
        Request $request,
        SchoolAssistantService $assistant,
        SchoolContext $context,
        CompanionContextService $companionContext,
    ): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:1200'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $school = $context->current();
        abort_unless($school, 503, 'School context is unavailable.');

        try {
            return response()->json($assistant->answer(
                $school,
                trim($data['message']),
                $data['conversation_id'] ?? null,
                $companionContext->forRequest($request, $school),
            ));
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'LYFLA AI sedang tidak dapat mengambil informasi. Coba lagi sebentar.',
            ], 503);
        }
    }
}
