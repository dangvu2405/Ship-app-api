<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Ai\BusinessAssistRequest;
use App\Services\BusinessAiAdvisorService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * @OA\Tag(name="AI", description="AI phân tích nghiệp vụ")
 */
class AiAdvisorController extends BaseController
{
    public function __construct(private readonly BusinessAiAdvisorService $advisorService) {}

    /**
     * @OA\Post(
     *     path="/api/ai/business-assist",
     *     tags={"AI"},
     *     summary="AI tư vấn nghiệp vụ vận hành",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"task"},
     *             @OA\Property(property="task", type="string", example="dashboard_insight"),
     *             @OA\Property(property="company_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="month", type="integer", nullable=true, example=4),
     *             @OA\Property(property="year", type="integer", nullable=true, example=2026),
     *             @OA\Property(property="language", type="string", nullable=true, example="vi"),
     *             @OA\Property(property="tone", type="string", nullable=true, example="executive"),
     *             @OA\Property(property="question", type="string", nullable=true, example="Nên ưu tiên tối ưu điểm nào tuần này?"),
     *             @OA\Property(property="context", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Phân tích thành công"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function businessAssist(BusinessAssistRequest $request): JsonResponse
    {
        try {
            $result = $this->advisorService->advise($request->validated());

            return $this->successResponse($result, 'api.ai.analysis_generated');
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.ai.analysis_failed');
        }
    }
}
