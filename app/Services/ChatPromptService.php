<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChatMessage;
use Illuminate\Support\Collection;

final class ChatPromptService
{
    private const SYSTEM_PROMPT = <<<PROMPT
Bạn là trợ lý nghiệp vụ của hệ thống quản trị vận tải Company Ship.
Phạm vi: đơn hàng, chuyến xe, định mức nhiên liệu, bảng lương tài xế, tuân thủ chứng chỉ, nhân sự, chấm công.
Trả lời ngắn gọn, rõ ràng, đúng nghiệp vụ. Không dùng markdown.
BẮT BUỘC luôn trả lời bằng tiếng Việt, kể cả khi người dùng hỏi bằng tiếng Anh.
Khi có dữ liệu thực tế từ hệ thống, LUÔN dựa vào đó để trả lời — không được bịa đặt hay suy đoán.
Nếu thiếu dữ liệu, hỏi đúng 1 câu ngắn nhất có thể.
Ưu tiên trình bày theo từng gạch đầu dòng để người dùng dễ đọc.
Khi trả lời thông tin nghiệp vụ, dùng cấu trúc:
- Kết quả chính: ...
- Dữ liệu liên quan: ...
- Đề xuất tiếp theo: ...
Nếu thiếu dữ liệu, thay bằng:
- Trạng thái dữ liệu: Chưa đủ dữ liệu.
- Cần bổ sung: <1 thông tin cụ thể>.
PROMPT;

    private const TEMPLATES = [
        'classify' => <<<T
Phân loại câu sau vào đúng 1 nhãn: ORDER | TRACKING | PAYROLL | FUEL | COMPLIANCE | PRICE | OTHER
Chỉ trả nhãn, không giải thích.
Câu: "{{message}}"
T,
        'extract' => <<<T
Trả về JSON hợp lệ duy nhất, không có text ngoài JSON.
Schema: {"sender_name":null,"sender_phone":null,"receiver_name":null,"receiver_phone":null,"from_address":null,"to_address":null,"item_description":null,"weight_kg":null,"note":null}
Nội dung: "{{message}}"
T,
        'trip_advice' => <<<T
Hàng: "{{item}}", từ "{{from}}" đến "{{to}}", phương tiện: {{vehicle_type}}, quãng đường: {{distance_km}} km.
Đưa ra 1 khuyến nghị đóng gói hoặc lưu ý vận chuyển, tối đa 25 từ.
T,
        'fuel_check' => <<<T
Phương tiện: {{vehicle_type}}, quãng đường: {{distance_km}} km.
Định mức: {{fuel_quota_l}} lít. Thực tế: {{fuel_actual_l}} lít.
Nhận xét kết quả đối soát và hành động tiếp theo. Tối đa 3 câu.
T,
        'payroll_query' => <<<T
Tài xế: {{driver_name}}.
Lương cơ bản: {{base_salary}} VNĐ. Ngày công: {{working_days}} / {{standard_days}} ngày chuẩn.
Thưởng km: {{bonus_km}} VNĐ. Khấu trừ: {{deductions}}.
Giải thích cách tính lương, nêu rõ nếu có proration. Tối đa 4 câu.
T,
        'compliance' => <<<T
Tài xế: {{driver_name}}. Chứng chỉ: {{cert_name}}. Hết hạn: {{expiry_date}}.
Cảnh báo rủi ro và hành động cần làm ngay. Tài xế có bị hạn chế phân công không?
T,
        'chat' => <<<T
Câu hỏi: "{{message}}"
Dựa vào dữ liệu thực tế từ hệ thống ở trên (nếu có), trả lời chính xác, ngắn gọn.
Không được suy đoán nếu dữ liệu không có trong context. Nếu thiếu dữ liệu, nói rõ.
Bắt buộc xuất câu trả lời cuối cùng bằng tiếng Việt.
Định dạng bắt buộc:
- Mỗi ý là 1 dòng bắt đầu bằng "- ".
- Tối đa 4 gạch đầu dòng.
- Mỗi dòng tối đa 1 câu ngắn, dễ hành động.
T,
        'fallback' => 'Viết 1 câu xin lỗi lịch sự, cực ngắn, báo hệ thống đang bận và mời thử lại sau.',
    ];

    private const DEFAULTS = [
        'item' => 'hàng hóa chưa xác định',
        'from' => 'điểm lấy hàng',
        'to' => 'điểm giao hàng',
        'vehicle_type' => 'xe tải',
        'distance_km' => 0,
        'fuel_quota_l' => 0,
        'fuel_actual_l' => 0,
        'driver_name' => 'tài xế',
        'base_salary' => 0,
        'working_days' => 0,
        'standard_days' => 22,
        'bonus_km' => 0,
        'deductions' => 'không có',
        'cert_name' => 'chứng chỉ',
        'expiry_date' => 'chưa xác định',
    ];

    /**
     * Build a structured prompt for the AI.
     *
     * @param Collection<int, ChatMessage> $history
     * @param array<string, mixed> $context
     * @param list<array{title: string, snippet: string, category: string}> $docs
     * @param array<string, mixed> $metadata
     * @return array{system: string, user: string, turns: list<array{role: string, text: string}>}
     */
    public function build(Collection $history, string $message, array $context, string $task, array $docs = [], array $metadata = []): array
    {
        $template = self::TEMPLATES[$task] ?? self::TEMPLATES['chat'];
        $variables = array_merge(self::DEFAULTS, ['message' => $message], $this->flattenContext($context));

        $body = (string) preg_replace_callback('/\{\{(\w+)\}\}/', static function (array $match) use ($variables): string {
            return (string) ($variables[$match[1]] ?? '');
        }, $template);

        // System instruction: role + extra context (not the data block)
        $systemLines = [self::SYSTEM_PROMPT];
        $extraContext = array_diff_key($context, array_flip([
            'task', 'actor', 'trip', 'fuel', 'payroll', 'compliance', 'data',
        ]));
        if ($extraContext !== []) {
            $systemLines[] = 'Context nghiệp vụ: '.json_encode($extraContext, JSON_UNESCAPED_UNICODE);
        }

        // User turn: tài liệu nghiệp vụ (RAG) + dữ liệu thực tế (DB) + câu hỏi
        $userLines = [];

        $docsBlock = $this->formatDocsBlock($docs);
        if ($docsBlock !== '') {
            $userLines[] = $docsBlock;
        }

        $metadataBlock = $this->formatMetadataBlock($metadata);
        if ($metadataBlock !== '') {
            $userLines[] = $metadataBlock;
        }

        $contextSnapshotBlock = $this->formatContextSnapshotBlock($context);
        if ($contextSnapshotBlock !== '') {
            $userLines[] = $contextSnapshotBlock;
        }

        $dataBlock = $this->formatDataBlock($context['data'] ?? []);
        if ($dataBlock !== '') {
            $userLines[] = $dataBlock;
        }

        $userLines[] = trim($body);

        // Conversation history as proper role/text turns
        $turns = [];
        foreach ($history as $item) {
            $turns[] = ['role' => 'user', 'text' => (string) $item->message];
            if (! empty($item->response)) {
                $turns[] = ['role' => 'model', 'text' => (string) $item->response];
            }
        }

        return [
            'system' => implode("\n\n", $systemLines),
            'user'   => implode("\n\n", $userLines),
            'turns'  => $turns,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function detectMissingContext(string $task, array $context): ?string
    {
        $checks = [
            'payroll_query' => static fn (): bool => ! isset($context['payroll']['base_salary']),
            'fuel_check'    => static fn (): bool => ! isset($context['fuel']['fuel_quota_l']) || ! isset($context['fuel']['fuel_actual_l']),
            'compliance'    => static fn (): bool => ! isset($context['compliance']['expiry_date']),
            'trip_advice'   => static fn (): bool => empty($context['trip']['from']) || empty($context['trip']['to']),
        ];

        if (isset($checks[$task]) && $checks[$task]()) {
            return 'Hệ thống cần thêm thông tin để trả lời chính xác. Bạn có thể cung cấp thêm không?';
        }

        return null;
    }

    /**
     * @param list<array{title: string, snippet: string, category: string}> $docs
     */
    private function formatDocsBlock(array $docs): string
    {
        if ($docs === []) {
            return '';
        }

        $lines = ['--- Tài liệu nghiệp vụ liên quan ---'];
        foreach ($docs as $doc) {
            $lines[] = '['.($doc['title'] ?? '').']';
            $lines[] = $doc['snippet'] ?? '';
        }
        $lines[] = '--- Hết tài liệu ---';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function formatMetadataBlock(array $metadata): string
    {
        if ($metadata === []) {
            return '';
        }

        $lines = ['--- Metadata hội thoại ---'];
        foreach ($metadata as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $label = str_replace('_', ' ', ucfirst((string) $key));
            $lines[] = $label.': '.(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE));
        }
        $lines[] = '--- Hết metadata ---';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function formatContextSnapshotBlock(array $context): string
    {
        $snapshot = [];
        foreach (['actor', 'trip', 'fuel', 'payroll', 'compliance'] as $key) {
            if (isset($context[$key]) && is_array($context[$key]) && $context[$key] !== []) {
                $snapshot[$key] = $context[$key];
            }
        }

        if ($snapshot === []) {
            return '';
        }

        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        if (! is_string($json) || $json === '') {
            return '';
        }

        return "--- Ngữ cảnh nghiệp vụ chi tiết ---\n".$json."\n--- Hết ngữ cảnh ---";
    }

    /**
     * Render context['data'] as a readable block for the AI.
     *
     * @param array<string, mixed> $data
     */
    private function formatDataBlock(array $data): string
    {
        if ($data === []) {
            return '';
        }

        $lines = ['--- Dữ liệu thực tế từ hệ thống ---'];

        foreach ($data as $key => $value) {
            $label = str_replace('_', ' ', ucfirst((string) $key));

            if (is_array($value)) {
                $lines[] = $label.':';
                foreach ($value as $index => $item) {
                    if (is_array($item)) {
                        $parts = [];
                        foreach ($item as $k => $v) {
                            if ($v !== null && $v !== '') {
                                $parts[] = str_replace('_', ' ', (string) $k).': '.$v;
                            }
                        }
                        $lines[] = '  '.($index + 1).'. '.implode(' | ', $parts);
                    } else {
                        $lines[] = '  - '.(string) $item;
                    }
                }
            } else {
                $lines[] = $label.': '.(string) $value;
            }
        }

        $lines[] = '--- Hết dữ liệu ---';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function flattenContext(array $context): array
    {
        return array_filter([
            'driver_name'  => $context['actor']['driver_name'] ?? $context['payroll']['driver_name'] ?? null,
            'from'         => $context['trip']['from'] ?? null,
            'to'           => $context['trip']['to'] ?? null,
            'item'         => $context['trip']['item'] ?? null,
            'vehicle_type' => $context['trip']['vehicle_type'] ?? $context['fuel']['vehicle_type'] ?? null,
            'distance_km'  => $context['trip']['distance_km'] ?? $context['fuel']['distance_km'] ?? null,
            'fuel_quota_l' => $context['fuel']['fuel_quota_l'] ?? null,
            'fuel_actual_l'=> $context['fuel']['fuel_actual_l'] ?? null,
            'base_salary'  => $context['payroll']['base_salary'] ?? null,
            'working_days' => $context['payroll']['working_days'] ?? null,
            'standard_days'=> $context['payroll']['standard_days'] ?? null,
            'bonus_km'     => $context['payroll']['bonus_km'] ?? null,
            'deductions'   => $context['payroll']['deductions'] ?? null,
            'cert_name'    => $context['compliance']['cert_name'] ?? null,
            'expiry_date'  => $context['compliance']['expiry_date'] ?? null,
        ]);
    }
}
