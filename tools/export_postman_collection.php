<?php

declare(strict_types=1);

/**
 * Build a Postman Collection v2.1 from Laravel routes and OpenAPI examples.
 *
 * Usage:
 *   php tools/export_postman_collection.php
 *
 * Output:
 *   postman/Company-Ship-API.postman_collection.json
 */
function runRouteList(string $root): array
{
    $proc = proc_open(
        [PHP_BINARY, 'artisan', 'route:list', '--json', '--path=api', '--except-vendor'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $root
    );

    if (! is_resource($proc)) {
        fwrite(STDERR, "Failed to run artisan route:list\n");
        exit(1);
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    foreach ($pipes as $pipe) {
        fclose($pipe);
    }

    $code = proc_close($proc);
    if ($code !== 0) {
        fwrite(STDERR, "route:list exited {$code}\n{$stderr}\n");
        exit(1);
    }

    return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
}

function loadOpenApi(string $root): array
{
    $file = $root.'/storage/api-docs/api-docs.json';
    if (! is_file($file)) {
        return [];
    }

    return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
}

function routeMethods(array $route): array
{
    return array_values(array_filter(
        explode('|', (string) ($route['method'] ?? 'GET')),
        static fn (string $method): bool => $method !== '' && $method !== 'HEAD'
    ));
}

function routeMiddlewareText(array $route): string
{
    return implode(', ', array_map('strval', (array) ($route['middleware'] ?? [])));
}

function routeUsesAuth(array $route): bool
{
    $middleware = routeMiddlewareText($route);

    return str_contains($middleware, 'Authenticate')
        || str_contains($middleware, 'auth:sanctum');
}

function routeUsesTenant(array $route): bool
{
    $middleware = routeMiddlewareText($route);

    return str_contains($middleware, 'EnsureTenantContext')
        || str_contains($middleware, 'tenant.context');
}

function routeFolderName(array $route): string
{
    $uri = (string) ($route['uri'] ?? '');
    $action = (string) ($route['action'] ?? '');

    if ($action === 'Closure') {
        return 'System';
    }

    $parts = explode('/', $uri);
    $name = $parts[1] ?? 'Root';

    return match ($name) {
        '', 'api' => 'Root',
        'auth' => 'Auth',
        'ai' => 'AI',
        default => titleFromSlug($name),
    };
}

function titleFromSlug(string $value): string
{
    $value = str_replace(['-', '_'], ' ', $value);

    return str_replace(' ', ' ', ucwords($value));
}

function variableNameForParam(string $param, ?string $previousSegment): string
{
    $param = rtrim($param, '?');
    if (str_ends_with($param, 'Id')) {
        return $param;
    }

    if ($param !== 'id') {
        return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $param)))).'Id';
    }

    if ($previousSegment === null || $previousSegment === '') {
        return 'resourceId';
    }

    return lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', singularSegment($previousSegment))))).'Id';
}

function singularSegment(string $segment): string
{
    $segment = str_replace(['-', '_'], ' ', $segment);
    $words = explode(' ', $segment);
    $last = array_pop($words) ?? $segment;

    $last = match (true) {
        str_ends_with($last, 'ies') => substr($last, 0, -3).'y',
        str_ends_with($last, 'sses') => substr($last, 0, -2),
        str_ends_with($last, 'ses') => substr($last, 0, -2),
        str_ends_with($last, 's') && strlen($last) > 3 => substr($last, 0, -1),
        default => $last,
    };

    $words[] = $last;

    return implode(' ', $words);
}

function postmanUrlAndVariables(string $uri): array
{
    $segments = explode('/', $uri);
    $variables = [];

    foreach ($segments as $index => $segment) {
        if (preg_match('/^\{([^}]+)\}$/', $segment, $matches) !== 1) {
            continue;
        }

        $previous = $segments[$index - 1] ?? null;
        $variable = variableNameForParam($matches[1], $previous);
        $variables[] = $variable;
        $segments[$index] = '{{'.$variable.'}}';
    }

    return ['{{baseUrl}}/'.implode('/', $segments), $variables];
}

function defaultVariableValue(string $key): string
{
    return match ($key) {
        'baseUrl' => 'http://localhost:8080',
        'loginEmail' => 'admin@example.com',
        'loginPassword' => 'password',
        'accessToken', 'refreshToken', 'chatSessionId' => '',
        'tenantId', 'companyId', 'driverId', 'vehicleId', 'vehicleTypeId',
        'sparePartId', 'customerId', 'customerGroupId', 'tripId', 'invoiceId',
        'leaveRequestId', 'leaveTypeId', 'vehicleAssignmentId', 'driverWorkScheduleId',
        'transportRequestId', 'userId', 'sessionId', 'childId' => '1',
        'notificationId' => '00000000-0000-0000-0000-000000000000',
        default => str_ends_with($key, 'Id') ? '1' : '',
    };
}

function fieldVariable(string $field): ?string
{
    return [
        'company_id' => '{{companyId}}',
        'driver_id' => '{{driverId}}',
        'vehicle_id' => '{{vehicleId}}',
        'vehicle_type_id' => '{{vehicleTypeId}}',
        'spare_part_id' => '{{sparePartId}}',
        'customer_id' => '{{customerId}}',
        'customer_group_id' => '{{customerGroupId}}',
        'group_id' => '{{customerGroupId}}',
        'trip_id' => '{{tripId}}',
        'transport_request_id' => '{{transportRequestId}}',
        'invoice_id' => '{{invoiceId}}',
        'leave_type_id' => '{{leaveTypeId}}',
        'user_id' => '{{userId}}',
        'session_id' => '{{chatSessionId}}',
    ][$field] ?? null;
}

function openApiOperation(array $openApi, string $uri, string $method): ?array
{
    $paths = $openApi['paths'] ?? [];
    $withoutApi = preg_replace('#^api/#', '', $uri);
    $candidates = [
        '/'.$uri,
        '/'.$withoutApi,
    ];

    foreach ($candidates as $path) {
        if (isset($paths[$path][strtolower($method)])) {
            return $paths[$path][strtolower($method)];
        }
    }

    return null;
}

function exampleFromSchema(array $schema, ?string $field = null): mixed
{
    if (array_key_exists('example', $schema)) {
        return normalizeExampleValue($field, $schema['example']);
    }

    if (isset($schema['enum'][0])) {
        return $schema['enum'][0];
    }

    $type = $schema['type'] ?? (isset($schema['properties']) ? 'object' : 'string');

    if ($type === 'object') {
        $value = [];
        foreach ((array) ($schema['properties'] ?? []) as $name => $propertySchema) {
            if (! is_array($propertySchema)) {
                continue;
            }

            $value[$name] = exampleFromSchema($propertySchema, (string) $name);
        }

        if ($value === []) {
            return (object) [];
        }

        return $value;
    }

    if ($type === 'array') {
        $items = $schema['items'] ?? ['type' => 'string'];

        return [is_array($items) ? exampleFromSchema($items, $field) : 'sample'];
    }

    return sampleValueForField($field, $type, $schema['format'] ?? null);
}

function normalizeExampleValue(?string $field, mixed $value): mixed
{
    if (! is_string($field) || $field === '') {
        return $value;
    }

    $lower = strtolower($field);
    $variable = fieldVariable($lower);
    if ($variable !== null) {
        return $variable;
    }

    if ($lower === 'email') {
        return 'postman+{{$timestamp}}@example.com';
    }

    if (str_contains($lower, 'password')) {
        return 'password';
    }

    if ($lower === 'code' || str_ends_with($lower, '_code')) {
        return 'PM-{{$timestamp}}';
    }

    if ($lower === 'plate_number') {
        return 'PM{{$timestamp}}';
    }

    if ($lower === 'tax_code') {
        return 'TAX{{$timestamp}}';
    }

    return $value;
}

function sampleValueForField(?string $field, string $type, mixed $format = null): mixed
{
    $field = strtolower((string) $field);
    $variable = fieldVariable($field);
    if ($variable !== null) {
        return $variable;
    }

    if (str_contains($field, 'email')) {
        return 'postman+{{$timestamp}}@example.com';
    }

    if (str_contains($field, 'password')) {
        return 'password';
    }

    if (str_contains($field, 'phone')) {
        return '0912345678';
    }

    if ($field === 'code' || str_ends_with($field, '_code')) {
        return 'PM-{{$timestamp}}';
    }

    if ($field === 'plate_number') {
        return 'PM{{$timestamp}}';
    }

    if ($field === 'tax_code') {
        return 'TAX{{$timestamp}}';
    }

    if ($format === 'date') {
        return '2026-05-03';
    }

    if ($format === 'date-time') {
        return '2026-05-03T08:00:00Z';
    }

    return match ($type) {
        'integer' => 1,
        'number' => 100000,
        'boolean' => true,
        'array' => [],
        default => match (true) {
            str_contains($field, 'status') => 'active',
            str_contains($field, 'type') => 'other',
            str_contains($field, 'name') => 'Postman Sample',
            str_contains($field, 'address') => '123 Postman Street',
            str_contains($field, 'reason') => 'Created from Postman test collection',
            str_contains($field, 'note') => 'Postman test note',
            default => 'sample',
        },
    };
}

function manualBody(string $uri, string $method): ?array
{
    $exact = [
        'api/auth/login' => ['email' => '{{loginEmail}}', 'password' => '{{loginPassword}}'],
        'api/auth/refresh-token' => ['refreshToken' => '{{refreshToken}}'],
        'api/auth/forgot-password' => ['email' => '{{loginEmail}}'],
        'api/auth/check-otp' => ['email' => '{{loginEmail}}', 'otp' => '123456'],
        'api/auth/reset-password' => [
            'email' => '{{loginEmail}}',
            'otp' => '123456',
            'password' => '{{loginPassword}}',
            'password_confirmation' => '{{loginPassword}}',
        ],
        'api/auth/password' => [
            'current_password' => '{{loginPassword}}',
            'password' => '{{loginPassword}}',
            'password_confirmation' => '{{loginPassword}}',
        ],
        'api/auth/social/login' => [
            'provider' => 'google',
            'access_token' => 'paste-provider-access-token-here',
            'id_token' => null,
        ],
        'api/chat/messages' => [
            'message' => 'Xin chao, tom tat tinh hinh van hanh hom nay.',
            'session_id' => '{{chatSessionId}}',
        ],
        'api/chat/messages/stream' => [
            'message' => 'Xin chao, tom tat tinh hinh van hanh hom nay.',
            'session_id' => '{{chatSessionId}}',
        ],
        'api/ai/business-assist' => [
            'task' => 'dashboard_insight',
            'company_id' => '{{companyId}}',
            'month' => 5,
            'year' => 2026,
            'language' => 'vi',
            'tone' => 'executive',
            'question' => 'Nen uu tien toi uu diem nao tuan nay?',
            'context' => ['source' => 'postman'],
        ],
        'api/companies' => [
            'code' => 'COMP-{{$timestamp}}',
            'name' => 'Postman Transport {{$timestamp}}',
            'tax_code' => 'TAX{{$timestamp}}',
            'address' => '123 Postman Street',
            'phone' => '0912345678',
            'email' => 'company+{{$timestamp}}@example.com',
            'status' => 'active',
        ],
        'api/customers' => [
            'company_id' => '{{companyId}}',
            'type' => 'company',
            'name' => 'Postman Customer {{$timestamp}}',
            'tax_code' => 'CUS{{$timestamp}}',
            'phone' => '0912345678',
            'email' => 'customer+{{$timestamp}}@example.com',
            'address' => '123 Customer Street',
        ],
        'api/drivers' => [
            'code' => 'DRV-{{$timestamp}}',
            'name' => 'Postman Driver',
            'email' => 'driver+{{$timestamp}}@example.com',
            'phone' => '0912345678',
            'dob' => '1990-01-01',
            'gender' => 'male',
            'address' => '123 Driver Street',
            'team_id' => null,
            'status' => 'active',
            'join_date' => '2026-05-03',
            'license_no' => 'LIC-{{$timestamp}}',
            'license_class' => 'B2',
            'expired_date' => '2028-12-31',
            'available_status' => 'available',
        ],
        'api/vehicles' => [
            'vehicle_type_id' => '{{vehicleTypeId}}',
            'plate_number' => 'PM{{$timestamp}}',
            'type' => 'truck',
            'brand' => 'Isuzu',
            'model' => 'Postman Truck',
            'year' => 2026,
            'capacity' => 5000,
            'status' => 'active',
        ],
        'api/trips' => [
            'code' => 'TRIP-{{$timestamp}}',
            'customer_id' => '{{customerId}}',
            'driver_id' => '{{driverId}}',
            'vehicle_id' => '{{vehicleId}}',
            'start_point' => 'Ha Noi',
            'end_point' => 'Hai Phong',
            'distance_km' => 120,
            'start_time' => '2026-05-03 08:00:00',
            'price' => 2500000,
            'status' => 'pending',
        ],
        'api/invoices' => [
            'code' => 'INV-{{$timestamp}}',
            'trip_id' => null,
            'customer_id' => '{{customerId}}',
            'subtotal' => 1000000,
            'vat_rate' => 10,
            'vat_amount' => 100000,
            'total_amount' => 1100000,
            'status' => 'draft',
            'issued_at' => null,
            'paid_at' => null,
        ],
        'api/spare-parts' => [
            'code' => 'SP-{{$timestamp}}',
            'name' => 'Postman spare part',
            'replacement_interval_km' => 10000,
            'replacement_interval_days' => 180,
            'estimated_unit_cost' => 500000,
            'is_active' => true,
        ],
        'api/transport-requests' => [
            'customer_id' => '{{customerId}}',
            'code' => 'TRQ-{{$timestamp}}',
            'pickup_location' => 'Ha Noi',
            'delivery_location' => 'Hai Phong',
            'cargo_weight' => 1,
            'requested_delivery_date' => '2026-05-05',
            'status' => 'draft',
        ],
        'api/vehicle_assignments' => [
            'vehicle_id' => '{{vehicleId}}',
            'driver_id' => '{{driverId}}',
            'from_date' => '2026-05-03',
            'to_date' => null,
        ],
        'api/leave-requests' => [
            'company_id' => '{{companyId}}',
            'driver_id' => '{{driverId}}',
            'leave_type_id' => '{{leaveTypeId}}',
            'from_date' => '2026-05-04',
            'to_date' => '2026-05-04',
            'total_days' => 1,
            'reason' => 'Postman leave request',
            'attachment_urls' => [],
        ],
        'api/reports/export' => [
            'type' => 'trips',
            'format' => 'json',
            'from' => '2026-05-01',
            'to' => '2026-05-31',
            'company_id' => '{{companyId}}',
        ],
    ];

    if (isset($exact[$uri])) {
        return $exact[$uri];
    }

    if (preg_match('#/(approve|submit|confirm|issue|mark-paid|send-cqt|read|read-all|lock|revoke)$#', $uri, $matches) === 1) {
        return ['note' => 'Postman '.$matches[1].' action'];
    }

    if (preg_match('#/(reject|cancel|dispute|resolve-dispute|waive)$#', $uri, $matches) === 1) {
        return ['reason' => 'Postman '.$matches[1].' reason'];
    }

    if (preg_match('#/(assign|change-driver)$#', $uri) === 1) {
        return ['driver_id' => '{{driverId}}'];
    }

    if (preg_match('#/change-vehicle$#', $uri) === 1) {
        return ['vehicle_id' => '{{vehicleId}}'];
    }

    if (preg_match('#/delay$#', $uri) === 1) {
        return ['reason' => 'Traffic delay', 'delayed_until' => '2026-05-03 12:00:00'];
    }

    if (preg_match('#/(pickup|arrive|transit|deliver|start|resume|complete)$#', $uri, $matches) === 1) {
        return ['note' => 'Postman '.$matches[1].' milestone'];
    }

    return null;
}

function requestBody(array $openApi, string $uri, string $method): ?array
{
    if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        return null;
    }

    if (str_starts_with($uri, 'api/upload')) {
        return formDataBody([
            ['key' => 'file', 'type' => 'file', 'src' => ''],
        ]);
    }

    $manual = manualBody($uri, $method);
    if ($manual !== null) {
        return rawJsonBody($manual);
    }

    $operation = openApiOperation($openApi, $uri, $method);
    $content = $operation['requestBody']['content'] ?? null;
    if (! is_array($content)) {
        return rawJsonBody((object) []);
    }

    if (isset($content['multipart/form-data'])) {
        $schema = $content['multipart/form-data']['schema'] ?? [];
        $fields = [];
        foreach ((array) ($schema['properties'] ?? []) as $name => $property) {
            $fields[] = [
                'key' => (string) $name,
                'type' => (($property['format'] ?? null) === 'binary') ? 'file' : 'text',
                'src' => (($property['format'] ?? null) === 'binary') ? '' : null,
                'value' => (($property['format'] ?? null) === 'binary') ? null : (string) sampleValueForField((string) $name, (string) ($property['type'] ?? 'string')),
            ];
        }

        return formDataBody($fields);
    }

    $schema = $content['application/json']['schema'] ?? null;
    if (is_array($schema)) {
        return rawJsonBody(exampleFromSchema($schema));
    }

    return rawJsonBody((object) []);
}

function rawJsonBody(mixed $body): array
{
    return [
        'mode' => 'raw',
        'raw' => json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'options' => [
            'raw' => [
                'language' => 'json',
            ],
        ],
    ];
}

function formDataBody(array $fields): array
{
    return [
        'mode' => 'formdata',
        'formdata' => array_map(
            static function (array $field): array {
                return array_filter([
                    'key' => $field['key'],
                    'type' => $field['type'] ?? 'text',
                    'src' => $field['src'] ?? null,
                    'value' => $field['value'] ?? null,
                ], static fn (mixed $value): bool => $value !== null);
            },
            $fields
        ),
    ];
}

function requestHeaders(bool $usesTenant, ?array $body, bool $usesAuth): array
{
    $headers = [
        ['key' => 'Accept', 'value' => 'application/json'],
    ];

    if ($usesAuth) {
        $headers[] = [
            'key' => 'Authorization',
            'value' => 'Bearer {{accessToken}}',
            'type' => 'text',
        ];
    }

    if (($body['mode'] ?? null) === 'raw') {
        $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
    }

    if ($usesTenant) {
        $headers[] = [
            'key' => 'X-Tenant-Id',
            'value' => '{{companyId}}',
            'type' => 'text',
        ];
    }

    return $headers;
}

function bearerAuth(): array
{
    return [
        'type' => 'bearer',
        'bearer' => [
            ['key' => 'token', 'value' => '{{accessToken}}', 'type' => 'string'],
        ],
    ];
}

function noAuth(): array
{
    return ['type' => 'noauth'];
}

function postmanItem(array $route, string $method, array $openApi, array &$variables): array
{
    $uri = (string) ($route['uri'] ?? '');
    [$url, $pathVariables] = postmanUrlAndVariables($uri);
    foreach ($pathVariables as $pathVariable) {
        $variables[$pathVariable] = defaultVariableValue($pathVariable);
    }

    $body = requestBody($openApi, $uri, $method);
    $request = [
        'method' => $method,
        'header' => requestHeaders(routeUsesTenant($route), $body, true),
        'url' => $url,
        'auth' => bearerAuth(),
        'description' => trim((string) ($route['action'] ?? '')."\n".routeMiddlewareText($route)),
    ];

    if ($body !== null) {
        $request['body'] = $body;
    }

    $item = [
        'name' => $method.' /'.$uri,
        'request' => $request,
    ];

    if ($uri === 'api/auth/login' && $method === 'POST') {
        $item['event'] = [
            ['listen' => 'test', 'script' => script(loginTestScript())],
        ];
    }

    return $item;
}

function script(string $source): array
{
    return [
        'type' => 'text/javascript',
        'exec' => explode("\n", trim($source)),
    ];
}

function collectionPreRequestScript(): string
{
    return <<<'JS'
const publicPaths = [
  '/api',
  '/api/health',
  '/api/auth/login',
  '/api/auth/social/login',
  '/api/auth/forgot-password',
  '/api/auth/check-otp',
  '/api/auth/reset-password',
  '/api/auth/refresh-token'
];

let rawUrl = pm.request.url.toString();
rawUrl = rawUrl.replace(pm.collectionVariables.get('baseUrl') || '', '').replace('{{baseUrl}}', '');
const isPublic = publicPaths.some((path) => rawUrl === path || rawUrl.startsWith(path + '?'));
const accessToken = pm.collectionVariables.get('accessToken');
const hasToken = Boolean(accessToken);

function applyAuthHeader(token) {
  if (token && !isPublic) {
    pm.request.headers.upsert({
      key: 'Authorization',
      value: 'Bearer ' + token
    });
  }
}

applyAuthHeader(accessToken);

if (!isPublic && !hasToken) {
  pm.sendRequest({
    url: (pm.collectionVariables.get('baseUrl') || 'http://localhost:8080') + '/api/auth/login',
    method: 'POST',
    header: {
      Accept: 'application/json',
      'Content-Type': 'application/json'
    },
    body: {
      mode: 'raw',
      raw: JSON.stringify({
        email: pm.collectionVariables.get('loginEmail') || 'admin@abctransport.com',
        password: pm.collectionVariables.get('loginPassword') || 'password'
      })
    }
  }, function (err, res) {
    if (err || !res) {
      return;
    }

    const json = res.json();
    const data = json && json.data ? json.data : json;
    if (data && data.token) {
      pm.collectionVariables.set('accessToken', data.token);
      applyAuthHeader(data.token);
    }
    if (data && data.refreshToken) {
      pm.collectionVariables.set('refreshToken', data.refreshToken);
    }
    if (data && Array.isArray(data.tenants) && data.tenants[0] && data.tenants[0].id) {
      const tenantId = String(data.tenants[0].id);
      pm.collectionVariables.set('tenantId', tenantId);
      pm.collectionVariables.set('companyId', tenantId);
    } else {
      pm.collectionVariables.set('tenantId', '');
      pm.collectionVariables.set('companyId', '');
    }
  });
}
JS;
}

function collectionTestScript(): string
{
    return <<<'JS'
pm.test('No 5xx server error', function () {
  pm.expect(pm.response.code).to.be.below(500);
});

let json = null;
try {
  json = pm.response.json();
} catch (e) {
  json = null;
}

if (json && Object.prototype.hasOwnProperty.call(json, 'success')) {
  pm.test('Response has boolean success flag', function () {
    pm.expect(json.success).to.be.a('boolean');
  });
}

if (json) {
  const payload = Object.prototype.hasOwnProperty.call(json, 'data') ? json.data : json;
  const rows = Array.isArray(payload) ? payload : (payload && Array.isArray(payload.data) ? payload.data : []);
  const item = rows[0] || (payload && !Array.isArray(payload) ? payload : null);
  const rawUrl = pm.request.url.toString();

  const mappings = [
    [/\/companies/, 'companyId'],
    [/\/vehicle-types/, 'vehicleTypeId'],
    [/\/spare-parts/, 'sparePartId'],
    [/\/customer-groups/, 'customerGroupId'],
    [/\/drivers/, 'driverId'],
    [/\/vehicles/, 'vehicleId'],
    [/\/customers/, 'customerId'],
    [/\/transport-requests/, 'transportRequestId'],
    [/\/trips/, 'tripId'],
    [/\/invoices/, 'invoiceId'],
    [/\/leave-requests/, 'leaveRequestId'],
    [/\/vehicle_assignments/, 'vehicleAssignmentId'],
    [/\/driver-schedules/, 'driverWorkScheduleId'],
    [/\/users/, 'userId']
  ];

  if (item && item.id) {
    mappings.forEach(function (mapping) {
      if (mapping[0].test(rawUrl)) {
        pm.collectionVariables.set(mapping[1], String(item.id));
      }
    });
  }

  if (payload && payload.session_id) {
    pm.collectionVariables.set('chatSessionId', String(payload.session_id));
  }

  if (payload && payload.company && payload.company.id) {
    const companyId = String(payload.company.id);
    pm.collectionVariables.set('tenantId', companyId);
    pm.collectionVariables.set('companyId', companyId);
  }
}
JS;
}

function loginTestScript(): string
{
    return <<<'JS'
pm.test('Login returns 200', function () {
  pm.response.to.have.status(200);
});

const json = pm.response.json();
const data = json && json.data ? json.data : json;

pm.test('Login returns access token', function () {
  pm.expect(data.token).to.be.a('string').and.not.empty;
});

pm.collectionVariables.set('accessToken', data.token);
if (data.refreshToken) {
  pm.collectionVariables.set('refreshToken', data.refreshToken);
}
if (Array.isArray(data.tenants) && data.tenants[0] && data.tenants[0].id) {
  const tenantId = String(data.tenants[0].id);
  pm.collectionVariables.set('tenantId', tenantId);
  pm.collectionVariables.set('companyId', tenantId);
} else {
  pm.collectionVariables.set('tenantId', '');
  pm.collectionVariables.set('companyId', '');
}
if (data.user && data.user.id) {
  pm.collectionVariables.set('userId', String(data.user.id));
}
JS;
}

/**
 * @return array<string, string>
 */
function fetchLoginDefaults(string $baseUrl, string $email, string $password): array
{
    $payload = json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Accept: application/json\r\nContent-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents(rtrim($baseUrl, '/').'/api/auth/login', false, $context);
    if (! is_string($response) || $response === '') {
        return [];
    }

    try {
        $json = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }

    $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
    if (! is_array($data) || empty($data['token']) || ! is_string($data['token'])) {
        return [];
    }

    $defaults = [
        'accessToken' => $data['token'],
    ];

    if (! empty($data['refreshToken']) && is_string($data['refreshToken'])) {
        $defaults['refreshToken'] = $data['refreshToken'];
    }

    if (! empty($data['user']['id'])) {
        $defaults['userId'] = (string) $data['user']['id'];
    }

    if (! empty($data['tenants'][0]['id'])) {
        $tenantId = (string) $data['tenants'][0]['id'];
        $defaults['tenantId'] = $tenantId;
        $defaults['companyId'] = $tenantId;
    }

    return $defaults;
}

function smokeFolder(array $itemsByKey): array
{
    $wanted = [
        ['POST', 'api/auth/login', 'Login - save token'],
        ['GET', 'api/auth/me', 'Me - verify token'],
        ['GET', 'api/health', 'Health'],
        ['GET', 'api/drivers', 'Drivers list'],
        ['GET', 'api/vehicles', 'Vehicles list'],
        ['GET', 'api/customers', 'Customers list'],
        ['GET', 'api/trips', 'Trips list'],
        ['GET', 'api/invoices', 'Invoices list'],
        ['GET', 'api/notifications/unread-count', 'Notifications unread count'],
    ];

    $items = [];
    foreach ($wanted as [$method, $uri, $name]) {
        $key = $method.' '.$uri;
        if (! isset($itemsByKey[$key])) {
            continue;
        }

        $item = $itemsByKey[$key];
        $item['name'] = $name;
        $items[] = $item;
    }

    return [
        'name' => '00 Smoke Run',
        'description' => 'Run this folder after import. It logs in with admin@example.com / password, saves tokens, then checks safe read-only endpoints.',
        'item' => $items,
    ];
}

$root = dirname(__DIR__);
chdir($root);

/** @var list<array<string, mixed>> $routes */
$routes = runRouteList($root);
$openApi = loadOpenApi($root);

$folders = [];
$itemsByKey = [];
$variables = [
    'baseUrl' => defaultVariableValue('baseUrl'),
    'loginEmail' => defaultVariableValue('loginEmail'),
    'loginPassword' => defaultVariableValue('loginPassword'),
    'accessToken' => defaultVariableValue('accessToken'),
    'refreshToken' => defaultVariableValue('refreshToken'),
    'tenantId' => defaultVariableValue('tenantId'),
    'companyId' => defaultVariableValue('companyId'),
    'driverId' => defaultVariableValue('driverId'),
    'vehicleId' => defaultVariableValue('vehicleId'),
    'vehicleTypeId' => defaultVariableValue('vehicleTypeId'),
    'sparePartId' => defaultVariableValue('sparePartId'),
    'customerId' => defaultVariableValue('customerId'),
    'customerGroupId' => defaultVariableValue('customerGroupId'),
    'tripId' => defaultVariableValue('tripId'),
    'transportRequestId' => defaultVariableValue('transportRequestId'),
    'invoiceId' => defaultVariableValue('invoiceId'),
    'leaveTypeId' => defaultVariableValue('leaveTypeId'),
    'notificationId' => defaultVariableValue('notificationId'),
];

$variables = array_merge(
    $variables,
    fetchLoginDefaults($variables['baseUrl'], $variables['loginEmail'], $variables['loginPassword'])
);

foreach ($routes as $route) {
    $uri = (string) ($route['uri'] ?? '');
    if ($uri === '' || ! str_starts_with($uri, 'api')) {
        continue;
    }

    $folderName = routeFolderName($route);

    foreach (routeMethods($route) as $method) {
        $key = $method.' '.$uri;
        if (isset($itemsByKey[$key])) {
            continue;
        }

        $item = postmanItem($route, $method, $openApi, $variables);
        $itemsByKey[$key] = $item;
        $folders[$folderName][$key] = $item;
    }
}

ksort($folders);

$items = [smokeFolder($itemsByKey)];
foreach ($folders as $name => $requests) {
    ksort($requests);
    $items[] = [
        'name' => '01 API Reference / '.$name,
        'item' => array_values($requests),
    ];
}

ksort($variables);
$collection = [
    'info' => [
        '_postman_id' => bin2hex(random_bytes(8)).'-'.bin2hex(random_bytes(4)),
        'name' => 'Company Ship API',
        'description' => "Auto-generated from `php artisan route:list --json --path=api`.\n\nRun folder `00 Smoke Run` first. It logs in with `admin@abctransport.com / password`, saves `accessToken`, `refreshToken`, `tenantId`, then checks safe endpoints.\n\nAuthenticated requests use Postman Bearer Auth with token `{{accessToken}}` and also include an explicit `Authorization: Bearer {{accessToken}}` header. The exporter pre-fills `accessToken` when `localhost:8080` is reachable.\n\nAll `01 API Reference / ...` folders include sample bodies for POST/PUT/PATCH requests and reusable collection variables.",
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'auth' => bearerAuth(),
    'event' => [
        ['listen' => 'prerequest', 'script' => script(collectionPreRequestScript())],
        ['listen' => 'test', 'script' => script(collectionTestScript())],
    ],
    'variable' => array_map(
        static fn (string $key, string $value): array => ['key' => $key, 'value' => $value],
        array_keys($variables),
        array_values($variables)
    ),
    'item' => $items,
];

$outDir = $root.'/postman';
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$outFile = $outDir.'/Company-Ship-API.postman_collection.json';
file_put_contents($outFile, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

$requestCount = array_sum(array_map('count', $folders));
echo "Wrote {$outFile}\n";
echo 'Folders: '.count($items).", reference requests: {$requestCount}, smoke requests: ".count($items[0]['item'])."\n";
