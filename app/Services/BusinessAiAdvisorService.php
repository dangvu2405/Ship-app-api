<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Arr;

class BusinessAiAdvisorService
{
    public function __construct(
        private readonly GeminiService $geminiService,
        private readonly ReportService $reportService
    ) {}

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function advise(array $input): array
    {
        $month = (int) ($input['month'] ?? now()->month);
        $year = (int) ($input['year'] ?? now()->year);
        $task = (string) ($input['task'] ?? 'recommendation');
        $language = (string) ($input['language'] ?? 'vi');
        $tone = (string) ($input['tone'] ?? 'executive');
        $companyId = isset($input['company_id']) ? (int) $input['company_id'] : null;

        $dashboardData = $this->reportService->getDashboardData($month, $year);

        $prompt = $this->buildPrompt(
            task: $task,
            language: $language,
            tone: $tone,
            month: $month,
            year: $year,
            dashboardData: $dashboardData,
            question: isset($input['question']) ? (string) $input['question'] : null,
            context: isset($input['context']) && is_array($input['context']) ? $input['context'] : []
        );

        $response = $this->geminiService->generateContent($prompt, [
            'generation_config' => [
                'temperature' => 0.25,
                'topP' => 0.8,
                'responseMimeType' => 'application/json',
            ],
        ]);

        $text = (string) ($response['text'] ?? '');
        $parsed = $this->tryParseJson($text);

        return [
            'task' => $task,
            'period' => [
                'month' => $month,
                'year' => $year,
            ],
            'model' => (string) config('services.gemini.model', 'gemini-2.0-flash'),
            'analysis' => $parsed ?? [
                'summary' => $text,
                'insights' => [],
                'actions' => [],
                'risks' => [],
                'follow_up_questions' => [],
            ],
            'source_metrics' => [
                'dashboard' => $dashboardData,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $dashboardData
     * @param array<string, mixed> $context
     */
    private function buildPrompt(
        string $task,
        string $language,
        string $tone,
        int $month,
        int $year,
        array $dashboardData,
        ?string $question,
        array $context
    ): string {
        $jsonSchema = [
            'summary' => 'string',
            'insights' => [['title' => 'string', 'detail' => 'string', 'impact' => 'high|medium|low']],
            'actions' => [[
                'title' => 'string',
                'priority' => 'P1|P2|P3',
                'owner' => 'ops|hr|finance|admin',
                'due_days' => 'number',
                'expected_kpi' => 'string',
            ]],
            'risks' => [['risk' => 'string', 'mitigation' => 'string']],
            'follow_up_questions' => ['string'],
        ];

        $payload = [
            'task' => $task,
            'language' => $language,
            'tone' => $tone,
            'period' => ['month' => $month, 'year' => $year],
            'dashboard_data' => $dashboardData,
            'extra_context' => $context,
            'user_question' => $question,
        ];

        return implode("\n\n", [
            'Bạn là Business AI Advisor cho hệ thống quản lý vận tải và tài xế.',
            'Mục tiêu: đưa ra insight có thể hành động ngay, ưu tiên hiệu quả vận hành, kiểm soát chi phí, và chất lượng dịch vụ.',
            'Yêu cầu bắt buộc: chỉ trả về JSON hợp lệ, không markdown, không giải thích ngoài JSON.',
            'Schema JSON phải theo đúng cấu trúc sau: ' . json_encode($jsonSchema, JSON_UNESCAPED_UNICODE),
            'Dữ liệu đầu vào: ' . json_encode($payload, JSON_UNESCAPED_UNICODE),
            'Nếu thiếu dữ liệu để kết luận chắc chắn, phải nêu giả định trong summary và thêm câu hỏi làm rõ trong follow_up_questions.',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tryParseJson(string $text): ?array
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'summary' => (string) Arr::get($decoded, 'summary', ''),
            'insights' => Arr::get($decoded, 'insights', []),
            'actions' => Arr::get($decoded, 'actions', []),
            'risks' => Arr::get($decoded, 'risks', []),
            'follow_up_questions' => Arr::get($decoded, 'follow_up_questions', []),
        ];
    }
}
