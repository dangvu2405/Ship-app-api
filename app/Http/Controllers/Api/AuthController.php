<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SocialLoginRequest;
use App\Services\AuthService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * @OA\Tag(name="Auth", description="Authentication endpoints")
 */
class AuthController extends BaseController
{
    public function __construct(private readonly AuthService $authService) {}

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Auth"},
     *     summary="Đăng nhập",
     *     security={},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Thành công - trả về user và token"),
     *     @OA\Response(response=401, description="Sai email hoặc mật khẩu"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->authService->login($validated['email'], $validated['password']);

            return $this->successResponse($result, 'Login successful');
        } catch (AuthenticationException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Login failed');
        }
    }

    /**
     * Đăng nhập bằng social provider (Google/Facebook/Apple)
     */
    public function socialLogin(SocialLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->authService->socialLogin(
                $validated['provider'],
                $validated['access_token'] ?? null,
                $validated['id_token'] ?? null,
            );

            return $this->successResponse($result, 'Social login successful');
        } catch (AuthenticationException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Social login failed');
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->authService->sendPasswordResetLink($validated['email']);

            return $this->successResponse(null, 'Password reset link sent');
        } catch (AuthenticationException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Forgot password failed');
        }
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->authService->resetPassword($validated);

            return $this->successResponse(null, 'Password reset successful');
        } catch (AuthenticationException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Reset password failed');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Auth"},
     *     summary="Đăng xuất",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Đăng xuất thành công"),
     *     @OA\Response(response=401, description="Chưa đăng nhập")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request->user());

            return $this->successResponse(null, 'Logout successful');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Logout failed');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     tags={"Auth"},
     *     summary="Đăng ký tài khoản (chỉ admin)",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","email","password","password_confirmation"},
     *             @OA\Property(property="username", type="string", example="newuser"),
     *             @OA\Property(property="email", type="string", format="email", example="newuser@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Đăng ký thành công"),
     *     @OA\Response(response=403, description="Chỉ admin được phép"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $user = $this->authService->register($validated);

            return $this->successResponse($user, 'Registration successful', 201);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Registration failed');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     tags={"Auth"},
     *     summary="Làm mới token",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Token mới"),
     *     @OA\Response(response=401, description="Chưa đăng nhập")
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $tokens = $this->authService->refresh($request->user());

            return $this->successResponse($tokens, 'Token refreshed successfully');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Token refresh failed');
        }
    }

    public function sessionsSummary(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }

            $summary = $this->authService->sessionsSummary($user);

            return $this->successResponse($summary, 'Session summary retrieved');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Session summary failed');
        }
    }

    public function sessions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }

            $perPage = (int) $request->input('per_page', 10);
            $result = $this->authService->sessions($user, $perPage);

            return $this->successResponse($result, 'Sessions retrieved');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Sessions query failed');
        }
    }

    public function logs(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }

            $request->validate([
                'date' => ['nullable', 'date'],
            ]);

            $date = $request->input('date');
            $result = $this->authService->logs($user, is_string($date) ? $date : null);

            return $this->successResponse($result, 'Auth logs retrieved');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Auth logs query failed');
        }
    }

    public function actions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }

            $validated = $request->validate([
                'username' => ['nullable', 'string', 'max:100'],
                'action' => ['nullable', 'string', 'max:150'],
                'from' => ['nullable', 'date'],
                'to' => ['nullable', 'date', 'after_or_equal:from'],
                'status_code' => ['nullable', 'integer', 'between:100,599'],
            ]);

            $result = $this->authService->actions($user, $validated);

            return $this->successResponse($result, 'Auth actions retrieved');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Auth actions query failed');
        }
    }

    public function revokeSession(Request $request, string $sessionId): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }

            $this->authService->revokeSession($user, $sessionId);

            return $this->successResponse(null, 'Session revoked');
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Session not found');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Revoke session failed');
        }
    }

    public function lockAccountForSession(Request $request, string $sessionId): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->unauthorizedResponse('Unauthenticated.');
            }

            $this->authService->lockAccountForSession($user, $sessionId);

            return $this->successResponse(null, 'Account locked for this session');
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('Session not found');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Lock account failed');
        }
    }
}
