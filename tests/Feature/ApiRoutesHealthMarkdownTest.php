<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Quét toàn bộ route có prefix `/api` (đăng ký qua routes/api.php và các file ceta_* được require từ đó),
 * ghi docs/API_ROUTE_HEALTH.md: HTTP status, trường JSON `success`, và thống kê route không trả success=true.
 *
 * Điều kiện: sqlite :memory: + migrate (RefreshDatabase), withoutMiddleware (bỏ auth/tenant để quét hàng loạt).
 * Kết quả phản ánh wiring + DB schema test; nhiều endpoint cần token/tenant đúng sẽ vẫn báo FAIL trong báo cáo này.
 */
final class ApiRoutesHealthMarkdownTest extends TestCase
{
    use RefreshDatabase;

    private const FIXED_UUID = '00000000-0000-4000-8000-000000000001';

    private const OUTPUT = 'docs/API_ROUTE_HEALTH.md';

    public function test_writes_api_route_health_markdown_report(): void
    {
        $this->withoutMiddleware();

        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        config(['app.debug' => true]);

        $rows = [];
        foreach (RouteFacade::getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }
            if (! $this->isApiSurfaceUri($route->uri())) {
                continue;
            }

            $controllerClass = $route->getControllerClass();
            if (is_string($controllerClass) && str_contains($controllerClass, 'L5Swagger\\')) {
                continue;
            }

            $path = '/'.ltrim($this->substitutePathParameters($route->uri()), '/');
            $actionLabel = $this->actionLabel($route);

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
                $content = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? '{}' : null;

                try {
                    $response = $this->call($method, $path, [], [], [], $server, $content);
                    $status = $response->getStatusCode();
                    $body = $response->getContent();
                } catch (\Throwable $e) {
                    $rows[] = [
                        'method' => $method,
                        'path' => $path,
                        'status' => 0,
                        'success' => null,
                        'message' => $e::class.': '.$e->getMessage(),
                        'action' => $actionLabel,
                        'ok' => false,
                    ];

                    continue;
                }

                $decoded = json_decode($body, true);
                $success = is_array($decoded) && array_key_exists('success', $decoded) ? $decoded['success'] : null;
                $message = is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])
                    ? $decoded['message']
                    : (is_string($body) && strlen($body) < 200 ? $body : '');

                $ok = $status >= 200 && $status < 300 && $success === true;

                $rows[] = [
                    'method' => $method,
                    'path' => $path,
                    'status' => $status,
                    'success' => $success,
                    'message' => $message,
                    'action' => $actionLabel,
                    'ok' => $ok,
                ];
            }
        }

        $markdown = $this->buildMarkdown($rows);
        $outPath = base_path(self::OUTPUT);
        if (! is_dir(dirname($outPath))) {
            mkdir(dirname($outPath), 0755, true);
        }
        file_put_contents($outPath, $markdown);

        $this->assertFileExists($outPath);
        $this->assertNotSame('', trim($markdown));
    }

    private function isApiSurfaceUri(string $uri): bool
    {
        return $uri === 'api' || str_starts_with($uri, 'api/');
    }

    private function substitutePathParameters(string $uri): string
    {
        return (string) preg_replace_callback(
            '/\{([^}]+)\}/',
            function (array $m) use ($uri): string {
                $name = strtolower($m[1]);
                if (str_contains($name, 'token') || str_contains($name, 'uuid') || str_contains($name, 'session')) {
                    return self::FIXED_UUID;
                }
                if ($name === 'id' && str_contains($uri, 'notifications')) {
                    return self::FIXED_UUID;
                }

                return '1';
            },
            $uri
        );
    }

    private function actionLabel(Route $route): string
    {
        $uses = $route->getAction('uses');
        if ($uses instanceof \Closure) {
            return 'closure';
        }
        if (is_array($uses) && isset($uses[0], $uses[1]) && is_string($uses[0]) && is_string($uses[1])) {
            return $uses[0].'@'.$uses[1];
        }
        if (is_string($uses)) {
            return $uses;
        }

        return 'unknown';
    }

    /**
     * @param  list<array{method: string, path: string, status: int, success: mixed, message: string, action: string, ok: bool}>  $rows
     */
    private function buildMarkdown(array $rows): string
    {
        $total = count($rows);
        $okCount = count(array_filter($rows, static fn (array $r): bool => $r['ok']));
        $fail = array_filter($rows, static fn (array $r): bool => ! $r['ok']);
        $failCount = count($fail);

        $byStatus = [];
        foreach ($rows as $r) {
            $k = (string) $r['status'];
            $byStatus[$k] = ($byStatus[$k] ?? 0) + 1;
        }
        ksort($byStatus, SORT_NATURAL);
        $statusLines = '';
        foreach ($byStatus as $code => $cnt) {
            $statusLines .= sprintf("| HTTP %s | %d |\n", $code, $cnt);
        }

        $lines = [];
        $lines[] = '# Báo cáo sức khỏe API (prefix `/api`)';
        $lines[] = '';
        $lines[] = 'Phạm vi: mọi route đăng ký với URI `api` hoặc `api/...` — tương ứng cây route trong `routes/api.php` (kèm `routes/ceta_office.php`, `ceta_company.php`, `ceta_platform.php` được `require` từ đó).';
        $lines[] = 'Không gồm `GET /docs` (Swagger UI) và không gồm callback L5-Swagger dưới `/api` (controller `L5Swagger\\*`).';
        $lines[] = '';
        $lines[] = '## Điều kiện chạy';
        $lines[] = '';
        $lines[] = '- PHPUnit `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`';
        $lines[] = '- `RefreshDatabase` + `withoutMiddleware()` (không áp dụng `auth:sanctum`, `tenant.context`, …)';
        $lines[] = '- Body JSON tối thiểu `{}` cho POST/PUT/PATCH/DELETE';
        $lines[] = '- Tham số path: số nguyên `1`, UUID cố định cho segment kiểu session/token/notification id';
        $lines[] = '';
        $lines[] = '**Ý nghĩa `success`:** response JSON có `success === true` và HTTP 2xx → cột *OK*. Các trường hợp khác (401, 403, 404, 422, 500, JSON không có `success`, HTML, …) đều ghi *FAIL* — có thể do thiếu token/tenant, thiếu bảng sau migrate, hoặc validation.';
        $lines[] = '';
        $lines[] = sprintf('**Sinh tự động:** %s (UTC)', gmdate('Y-m-d H:i:s'));
        $lines[] = '';
        $lines[] = '## Tóm tắt';
        $lines[] = '';
        $lines[] = '| Chỉ số | Giá trị |';
        $lines[] = '| --- | ---: |';
        $lines[] = sprintf('| Tổng số lần gọi (method × path) | %d |', $total);
        $lines[] = sprintf('| OK (`success: true` + HTTP 2xx) | %d |', $okCount);
        $lines[] = sprintf('| FAIL (không đạt OK) | %d |', $failCount);
        $lines[] = '';
        $lines[] = '### Phân bố HTTP status';
        $lines[] = '';
        $lines[] = '| Status | Số lượng |';
        $lines[] = '| --- | ---: |';
        $lines[] = $statusLines;
        $lines[] = '';
        $lines[] = '## Danh sách FAIL — không trả `success: true` với HTTP 2xx';
        $lines[] = '';
        $lines[] = '| Method | Path | HTTP | success (JSON) | Action | Message (rút gọn) |';
        $lines[] = '| --- | --- | ---: | --- | --- | --- |';

        if ($failCount === 0) {
            $lines[] = '| — | — | — | — | — | Không có |';
        } else {
            foreach ($fail as $r) {
                $succ = $r['success'] === null ? '∅ (không parse / không có key)' : json_encode($r['success'], JSON_UNESCAPED_UNICODE);
                $lines[] = sprintf(
                    '| %s | `%s` | %d | %s | `%s` | %s |',
                    $r['method'],
                    $this->mdEscapeCell($r['path']),
                    $r['status'],
                    $this->mdEscapeCell($succ),
                    $this->mdEscapeCell($r['action']),
                    $this->mdEscapeCell(Str::limit((string) $r['message'], 120))
                );
            }
        }

        $lines[] = '';
        $lines[] = '## Danh sách OK';
        $lines[] = '';
        $lines[] = '| Method | Path | HTTP | Action |';
        $lines[] = '| --- | --- | ---: | --- |';

        foreach ($rows as $r) {
            if (! $r['ok']) {
                continue;
            }
            $lines[] = sprintf(
                '| %s | `%s` | %d | `%s` |',
                $r['method'],
                $this->mdEscapeCell($r['path']),
                $r['status'],
                $this->mdEscapeCell($r['action'])
            );
        }

        $lines[] = '';
        $lines[] = '## Tái tạo file';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = './vendor/bin/phpunit tests/Feature/ApiRoutesHealthMarkdownTest.php';
        $lines[] = '```';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function mdEscapeCell(string $s): string
    {
        $s = str_replace(["\r", "\n"], ' ', $s);

        return str_replace('|', '\\|', $s);
    }
}
