<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * @OA\Tag(name="Auth", description="Đăng nhập, đăng xuất, token")
 */
class AuthController extends BaseController
{
    // Token expiration times (in minutes)
    private const ACCESS_TOKEN_EXPIRATION = 60; // 1 hour
    private const REFRESH_TOKEN_EXPIRATION = 60 * 24 * 7; // 7 days
    /**
     * Đăng nhập - lấy token
     *
     * @OA\Post(
     *     path="/api/auth/login",
     *     tags={"Auth"},
     *     summary="Đăng nhập",
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
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = User::where('email', $request->email)
                ->where('status', 'active')
                ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->errorResponse('Invalid credentials', 401);
            }

            // Tạo access token với Sanctum (lưu trong personal_access_tokens để validate)
            $accessToken = $user->createToken(
                'auth-token',
                ['*'],
                Carbon::now()->addMinutes(self::ACCESS_TOKEN_EXPIRATION)
            );

            // Tạo refresh token (lưu vào database)
            $refreshToken = RefreshToken::create([
                'user_id' => $user->id,
                'token' => RefreshToken::generateToken(),
                'access_token_id' => $accessToken->accessToken->id,
                'expires_at' => Carbon::now()->addMinutes(self::REFRESH_TOKEN_EXPIRATION),
                'is_revoked' => false,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Update last login
            $user->update(['last_login_at' => now()]);

            // Load relationships
            $user->load(['employee', 'roles.permissions']);

            return $this->successResponse([
                'user' => $user,
                'access_token' => $accessToken->plainTextToken, // Client lưu vào localStorage
                'refresh_token' => $refreshToken->token, // Client lưu vào localStorage
                'token_type' => 'Bearer',
                'expires_in' => self::ACCESS_TOKEN_EXPIRATION * 60, // seconds
            ], 'Login successful');
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
     *     @OA\Response(response=200, description="Đăng xuất thành công"),
     *     @OA\Response(response=401, description="Chưa đăng nhập")
     * )
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            
            if ($user) {
                // Revoke current access token
                $currentToken = $user->currentAccessToken();
                if ($currentToken) {
                    // Revoke associated refresh token
                    RefreshToken::where('access_token_id', $currentToken->id)
                        ->update(['is_revoked' => true]);
                    
                    $currentToken->delete();
                }
            }

            // Nếu có refresh_token trong request, revoke nó
            if ($request->has('refresh_token')) {
                RefreshToken::where('token', $request->refresh_token)
                    ->update(['is_revoked' => true]);
            }

            return $this->successResponse(null, 'Logout successful');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Logout failed');
        }
    }

    /**
     * Revoke all refresh tokens của user (logout all devices)
     *
     * @OA\Post(
     *     path="/api/auth/logout-all",
     *     tags={"Auth"},
     *     summary="Đăng xuất tất cả thiết bị",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Đăng xuất thành công"),
     *     @OA\Response(response=401, description="Chưa đăng nhập")
     * )
     */
    public function logoutAll(Request $request)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Unauthenticated');
            }

            $user = $request->user();
            
            // Revoke all refresh tokens
            RefreshToken::where('user_id', $user->id)
                ->where('is_revoked', false)
                ->update(['is_revoked' => true]);
            
            // Delete all access tokens
            $user->tokens()->delete();

            return $this->successResponse(null, 'Logged out from all devices');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Logout all failed');
        }
    }

    /**
     * Đăng ký tài khoản mới
     *
     * @OA\Post(
     *     path="/api/auth/register",
     *     tags={"Auth"},
     *     summary="Đăng ký tài khoản",
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
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => 'active',
            ]);

            // Load relationships
            $user->load(['employee', 'roles.permissions']);

            return $this->successResponse($user, 'Registration successful', 201);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Registration failed');
        }
    }

    /**
     * Refresh token - chỉ cần refresh_token từ request
     *
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     tags={"Auth"},
     *     summary="Làm mới access token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"refresh_token"},
     *             @OA\Property(property="refresh_token", type="string", example="refresh_token_string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Token mới"),
     *     @OA\Response(response=401, description="Refresh token không hợp lệ")
     * )
     */
    public function refresh(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator->errors());
        }

        try {
            // Tìm refresh token trong database
            $refreshToken = RefreshToken::where('token', $request->refresh_token)
                ->where('is_revoked', false)
                ->first();

            // Validate refresh token
            if (!$refreshToken || !$refreshToken->isValid()) {
                return $this->errorResponse('Invalid or expired refresh token', 401);
            }

            $user = $refreshToken->user;

            // Kiểm tra user còn active không
            if ($user->status !== 'active') {
                $refreshToken->revoke();
                return $this->errorResponse('User account is inactive', 401);
            }

            // Revoke old access token nếu có
            if ($refreshToken->access_token_id) {
                $refreshToken->accessToken?->delete();
            }

            // Revoke old refresh token (token rotation - bảo mật hơn)
            $refreshToken->revoke();

            // Tạo access token mới (lưu trong personal_access_tokens)
            $newAccessToken = $user->createToken(
                'auth-token',
                ['*'],
                Carbon::now()->addMinutes(self::ACCESS_TOKEN_EXPIRATION)
            );

            // Tạo refresh token mới (lưu vào database)
            $newRefreshToken = RefreshToken::create([
                'user_id' => $user->id,
                'token' => RefreshToken::generateToken(),
                'access_token_id' => $newAccessToken->accessToken->id,
                'expires_at' => Carbon::now()->addMinutes(self::REFRESH_TOKEN_EXPIRATION),
                'is_revoked' => false,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->successResponse([
                'access_token' => $newAccessToken->plainTextToken, // Client cập nhật vào localStorage
                'refresh_token' => $newRefreshToken->token, // Client cập nhật vào localStorage
                'token_type' => 'Bearer',
                'expires_in' => self::ACCESS_TOKEN_EXPIRATION * 60, // seconds
            ], 'Token refreshed successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Token refresh failed');
        }
    }
}
