<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Auth", description="Đăng nhập, đăng xuất, token")
 */
class AuthController extends BaseController
{
    public function __construct(private readonly AuthService $authService) {}

    /**
     * Đăng nhập - lấy token
     *
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

            return $this->successResponse($result, 'Login successful');
        } catch (AuthenticationException $e) {
            return $this->errorResponse($e->getMessage(), 401);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Login failed');
        }
    }

    /**
     * Đăng xuất - thu hồi token hiện tại
     *
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

            return $this->successResponse(null, 'Logout successful');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Logout failed');
        }
    }

    /**
     * Đăng ký tài khoản mới
     *
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

            return $this->successResponse($user, 'Registration successful', 201);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Registration failed');
        }
    }

    /**
     * Làm mới token
     *
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
            $token = $this->authService->refresh($request->user());

            return $this->successResponse([
                'token' => $token,
            ], 'Token refreshed successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Token refresh failed');
        }
    }

    /**
     * Danh sách tài khoản dùng cho màn dev (không trả mật khẩu).
     * Bật khi APP_ENV là local/testing hoặc SHOW_TEST_ACCOUNTS=true (config ship.expose_test_accounts).
     */
    public function testAccounts(): JsonResponse
    {
        if (! app()->environment('local', 'testing') && ! (bool) config('ship.expose_test_accounts', false)) {
            return $this->notFoundResponse('Test accounts are not available in this environment');
        }

        $rows = User::query()
            ->with(['roles:id,name'])
            ->where('status', 'active')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'username', 'email', 'status']);

        $accounts = $rows->map(static function (User $user): array {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values()->all(),
            ];
        });

        return $this->successResponse([
            'accounts' => $accounts,
            'hint' => 'Passwords are not returned; use your seeder / README credentials for local login.',
        ], 'OK');
    }
}
