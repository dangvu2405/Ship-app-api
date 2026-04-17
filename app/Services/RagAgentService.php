<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Two-tool RAG agent for Vietnamese business queries.
 *
 * The agent (Groq llama-3.3-70b) decides per-turn which tool to call:
 *
 *   search_semantic  — hybrid vector + keyword search over rag_index
 *                      → for qualitative/descriptive questions
 *
 *   query_database   — Text2SQL via SqlAgentService
 *                      → for quantitative/aggregate questions
 *
 * The agent may call multiple tools in sequence before producing a final
 * answer (up to $maxIterations turns).
 *
 * Requires RAG_AGENT_ENABLED=true in .env to be used from ChatService.
 *
 * Tool use relies on Groq's Chat Completions API (not the OpenAI Responses API
 * used elsewhere), so this service makes its own HTTP call to
 *   POST https://api.groq.com/openai/v1/chat/completions
 */
class RagAgentService
{
    /** Groq model that handles tool use well */
    private const AGENT_MODEL = 'llama-3.3-70b-versatile';

    /** @var list<array<string, mixed>> */
    private readonly array $toolDefinitions;

    public function __construct(
        private readonly RetrievalService $retriever,
        private readonly SqlAgentService  $sqlAgent,
    ) {
        $this->toolDefinitions = $this->buildToolDefinitions();
    }

    /**
     * Run the agent and return its final answer plus an execution trace.
     *
     * @return array{answer: string, trace: list<array{tool: string, args: array, result: string}>}
     */
    public function ask(string $userQuery, ?int $companyId = null, int $maxIterations = 5): array
    {
        $apiKey  = (string) config('services.groq.api_key');
        $baseUrl = rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');

        if ($apiKey === '' || ! str_contains($baseUrl, 'groq.com')) {
            return [
                'answer' => 'Agent không khả dụng (thiếu GROQ_API_KEY).',
                'trace'  => [],
            ];
        }

        $messages = [
            [
                'role'    => 'system',
                'content' => $this->buildSystemPrompt(),
            ],
            [
                'role'    => 'user',
                'content' => $userQuery,
            ],
        ];

        $trace = [];

        for ($i = 0; $i < $maxIterations; $i++) {
            try {
                $response = Http::timeout(30)
                    ->withToken($apiKey)
                    ->post("{$baseUrl}/chat/completions", [
                        'model'       => self::AGENT_MODEL,
                        'messages'    => $messages,
                        'tools'       => $this->toolDefinitions,
                        'tool_choice' => 'auto',
                        'temperature' => 0.1,
                        'max_tokens'  => 1024,
                    ]);

                if ($response->failed()) {
                    Log::warning('RagAgentService: Groq error', ['status' => $response->status()]);

                    return [
                        'answer' => 'Lỗi kết nối đến AI agent.',
                        'trace'  => $trace,
                    ];
                }

                /** @var array<string, mixed> $decoded */
                $decoded = $response->json() ?? [];
                /** @var array<string, mixed> $message */
                $message  = $decoded['choices'][0]['message'] ?? [];
                $messages[] = $message;

                // No tool call → final answer
                if (empty($message['tool_calls'])) {
                    $answer = (string) ($message['content'] ?? '');

                    return ['answer' => $answer, 'trace' => $trace];
                }

                // Execute each tool call
                foreach ((array) $message['tool_calls'] as $toolCall) {
                    /** @var array<string, mixed> $toolCall */
                    $name   = (string) ($toolCall['function']['name'] ?? '');
                    $rawArgs = (string) ($toolCall['function']['arguments'] ?? '{}');
                    /** @var array<string, mixed> $args */
                    $args   = json_decode($rawArgs, true) ?? [];
                    $callId = (string) ($toolCall['id'] ?? '');

                    $result = $this->dispatchTool($name, $args, $companyId);

                    $trace[] = [
                        'tool'   => $name,
                        'args'   => $args,
                        'result' => $result,
                    ];

                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $callId,
                        'content'      => $result,
                    ];
                }
            } catch (Throwable $e) {
                Log::error('RagAgentService: exception', ['error' => $e->getMessage()]);

                return [
                    'answer' => 'Đã xảy ra lỗi khi xử lý câu hỏi.',
                    'trace'  => $trace,
                ];
            }
        }

        return [
            'answer' => 'Đã đạt giới hạn vòng lặp của agent, không thể tổng hợp câu trả lời.',
            'trace'  => $trace,
        ];
    }

    // ─── Tool dispatch ────────────────────────────────────────────────────────

    private function dispatchTool(string $name, array $args, ?int $companyId): string
    {
        return match ($name) {
            'search_semantic' => $this->handleSemantic(
                (string) ($args['query'] ?? ''),
                $companyId,
                isset($args['source_table']) ? (string) $args['source_table'] : null,
            ),
            'query_database'  => $this->sqlAgent->answer(
                (string) ($args['question'] ?? ''),
                $companyId,
            ),
            default => "Tool '{$name}' không tồn tại.",
        };
    }

    private function handleSemantic(string $query, ?int $companyId, ?string $sourceTable): string
    {
        $filters = [];
        if ($companyId !== null) {
            $filters['company_id'] = $companyId;
        }
        if ($sourceTable !== null && $sourceTable !== '') {
            $filters['source_table'] = $sourceTable;
        }

        $results = $this->retriever->hybridSearch($query, $filters, 6);

        if (empty($results)) {
            return 'Không tìm thấy dữ liệu liên quan trong kho thông tin.';
        }

        return collect($results)
            ->map(fn (array $r): string => "[{$r['source']}] {$r['content']}")
            ->implode("\n\n");
    }

    // ─── Prompt & tool definitions ────────────────────────────────────────────

    private function buildSystemPrompt(): string
    {
        return implode("\n", [
            'Bạn là trợ lý phân tích dữ liệu nội bộ của hệ thống quản trị vận tải Company Ship.',
            '',
            'Bạn có 2 công cụ:',
            '• search_semantic — tìm kiếm ngữ nghĩa trong mô tả chuyến xe, tài xế, vi phạm.',
            '  Dùng khi câu hỏi mang tính định tính: "tài xế nào hay vi phạm", "chuyến nào có vấn đề".',
            '• query_database — sinh SQL và chạy trên database thực.',
            '  Dùng khi câu hỏi cần số liệu chính xác: "tổng doanh thu", "đếm số chuyến", "lương trung bình".',
            '',
            'Quy tắc:',
            '- Gọi đúng tool. Có thể gọi nhiều lần liên tiếp nếu cần tổng hợp.',
            '- Luôn trích dẫn dữ liệu thực tế trong câu trả lời cuối.',
            '- Trả lời bằng tiếng Việt, ngắn gọn, dùng gạch đầu dòng.',
            '- Không bịa đặt — nếu không có dữ liệu, nói rõ.',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildToolDefinitions(): array
    {
        return [
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'search_semantic',
                    'description' => 'Tìm kiếm ngữ nghĩa trong dữ liệu mô tả (chuyến xe, tài xế, vi phạm). '
                        . 'Dùng cho câu hỏi định tính như "tài xế nào thường xuyên vi phạm", '
                        . '"chuyến nào có sự cố", "tài xế nào được đánh giá cao". '
                        . 'Không dùng cho câu hỏi số liệu chính xác.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'query' => [
                                'type'        => 'string',
                                'description' => 'Câu truy vấn bằng tiếng Việt tự nhiên',
                            ],
                            'source_table' => [
                                'type'        => 'string',
                                'description' => 'Lọc theo bảng: drivers | trips | violations | payroll_lines (tùy chọn)',
                            ],
                        ],
                        'required'   => ['query'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'query_database',
                    'description' => 'Truy vấn database để lấy số liệu chính xác. '
                        . 'Dùng cho câu hỏi định lượng như "tổng doanh thu tháng X", '
                        . '"có bao nhiêu chuyến", "lương trung bình", "top N tài xế". '
                        . 'Input là câu hỏi tiếng Việt — hệ thống sẽ tự sinh SQL.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'question' => [
                                'type'        => 'string',
                                'description' => 'Câu hỏi định lượng bằng tiếng Việt',
                            ],
                        ],
                        'required'   => ['question'],
                    ],
                ],
            ],
        ];
    }
}
