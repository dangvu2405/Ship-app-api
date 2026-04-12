<?php

declare(strict_types=1);

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Company Ship API",
 *     version="1.0",
 *     description="API quản lý vận tải - công ty, nhân sự, xe, chuyến đi, lương. Hầu hết endpoint yêu cầu đăng nhập Sanctum và role admin."
 * )
 *
 * @OA\Server(
 *     url="/api",
 *     description="API Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Dùng token Bearer trả về từ POST /api/v1/auth/login (email + password)"
 * )
 *
 * @OA\SecurityRequirement(
 *     securityScheme="sanctum"
 * )
 *
 * @OA\PathItem(
 *     path="/health",
 *
 *     @OA\Get(
 *         tags={"System"},
 *         summary="Health check",
 *
 *         @OA\Response(response=200, description="OK")
 *     )
 * )
 */
abstract class Controller
{
    //
}
