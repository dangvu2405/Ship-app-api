<?php

declare(strict_types=1);

arch('API controllers extend BaseController', function () {
    expect('App\Http\Controllers\Api')
        ->classes()
        ->toExtend(\App\Http\Controllers\Api\BaseController::class)
        ->ignoring([\App\Http\Controllers\Api\BaseController::class]);
});

arch('API controllers use strict types', function () {
    expect('App\Http\Controllers\Api')
        ->classes()
        ->toUseStrictTypes();
});
