<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\AuthActionsRequest;
use App\Http\Requests\Auth\AuthLogsRequest;
use App\Http\Requests\Auth\CheckOtpRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SocialLoginRequest;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
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
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email","password"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="admin@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password")
     *         )
     *     ),
     *
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

            return $this->successResponse($result, 'api.auth.login_success');
        } catch (AuthenticationException $e) {
            return $this->authLoginErrorResponse($e);
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.login_failed');
        }
    }

    private function authLoginErrorResponse(AuthenticationException $exception): JsonResponse
    {
        $reason = strtoupper(trim((string) $exception->getMessage()));

        [$messageKey, $statusCode] = match ($reason) {
            'ACCOUNT_NOT_FOUND' => ['api.auth.account_not_found', 401],
            'INVALID_PASSWORD' => ['api.auth.invalid_password', 401],
            'ACCOUNT_INACTIVE' => ['api.auth.account_inactive', 403],
            default => ['api.auth.login_failed', 401],
        };

        return $this->errorResponse($messageKey, $statusCode);
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

            return $this->successResponse($result, 'api.auth.social_login_success');
        } catch (AuthenticationException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.social_login_failed');
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->authService->sendPasswordResetLink($validated['email']);

            return $this->successResponse(null, 'api.auth.password_reset_link_sent');
        } catch (TooManyRequestsHttpException $e) {
            return $this->errorResponse($e->getMessage(), 429);
        } catch (AuthenticationException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.forgot_password_failed');
        }
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->authService->resetPassword($validated);

            return $this->successResponse(null, 'api.auth.password_reset_success');
        } catch (AuthenticationException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.reset_password_failed');
        }
    }

    public function checkOtp(CheckOtpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->authService->checkPasswordResetOtp($validated);

            return $this->successResponse($result, 'api.auth.otp_verified');
        } catch (AuthenticationException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.otp_verification_failed');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/logout",
     *     tags={"Auth"},
     *     summary="Đăng xuất",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Đăng xuất thành công"),
     *     @OA\Response(response=401, description="Chưa đăng nhập")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request->user());

            return $this->successResponse(null, 'api.auth.logout_success');
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.logout_failed');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     tags={"Auth"},
     *     summary="Đăng ký tài khoản (chỉ admin)",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"username","email","password","password_confirmation"},
     *
     *             @OA\Property(property="username", type="string", example="newuser"),
     *             @OA\Property(property="email", type="string", format="email", example="newuser@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
     *         )
     *     ),
     *
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

            return $this->successResponse($user, 'api.auth.registration_success', 201);
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.registration_failed');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     tags={"Auth"},
     *     summary="Làm mới token",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Token mới"),
     *     @OA\Response(response=401, description="Chưa đăng nhập")
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $tokens = $this->authService->refresh($request->user());

            return $this->successResponse($tokens, 'api.auth.token_refresh_success');
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.token_refresh_failed');
        }
    }

    public function refreshByToken(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            /** @var array{token: string, refreshToken: string} $tokens */
            $tokens = $this->authService->refreshWithRefreshToken((string) $validated['refresh_token']);

            return $this->successResponse($tokens, 'api.auth.token_refresh_success');
        } catch (AuthenticationException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.token_refresh_failed');
        }
    }

    public function sessionsSummary(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->unauthorizedResponse('api.unauthenticated');
            }

            $summary = $this->authService->sessionsSummary($user);

            return $this->successResponse($summary, 'api.auth.session_summary_retrieved');
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.session_summary_failed');
        }
    }

    public function sessions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->unauthorizedResponse('api.unauthenticated');
            }

            $perPage = (int) $request->input('per_page', 10);
            $result = $this->authService->sessions($user, $perPage);

            return $this->successResponse($result, 'api.auth.sessions_retrieved');
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.sessions_query_failed');
        }
    }

    public function logs(AuthLogsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->unauthorizedResponse('api.unauthenticated');
            }

            $date = $request->input('date');
            $result = $this->authService->logs($user, is_string($date) ? $date : null);

            return $this->successResponse($result, 'api.auth.logs_retrieved');
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.logs_query_failed');
        }
    }

    public function actions(AuthActionsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->unauthorizedResponse('api.unauthenticated');
            }

            $result = $this->authService->actions($user, $request->validated());

            return $this->successResponse($result, 'api.auth.actions_retrieved');
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.actions_query_failed');
        }
    }

    public function revokeSession(Request $request, string $sessionId): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->unauthorizedResponse('api.unauthenticated');
            }

            $this->authService->revokeSession($user, $sessionId);

            return $this->successResponse(null, 'api.auth.session_revoked');
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('api.auth.session_not_found');
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.revoke_session_failed');
        }
    }

    public function lockAccountForSession(Request $request, string $sessionId): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return $this->unauthorizedResponse('api.unauthenticated');
            }

            $this->authService->lockAccountForSession($user, $sessionId);

            return $this->successResponse(null, 'api.auth.account_locked_for_session');
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse('api.auth.session_not_found');
        } catch (Throwable $e) {
            return $this->handleException($e, 'api.auth.lock_account_failed');
        }
    }
}
