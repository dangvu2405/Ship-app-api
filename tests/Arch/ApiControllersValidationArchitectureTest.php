<?php

declare(strict_types=1);

arch('API controllers avoid inline validation', function () {
    $controllerFiles = glob(__DIR__ . '/../../app/Http/Controllers/Api/*.php') ?: [];

    foreach ($controllerFiles as $file) {
        $content = file_get_contents($file);

        if ($content === false) {
            continue;
        }

        expect($content)
            ->not->toContain('$request->validate(')
            ->and($content)
            ->not->toContain('Validator::make(');
    }
});
