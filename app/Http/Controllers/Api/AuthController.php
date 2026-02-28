<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(name="Auth", description="Đăng nhập, đăng xuất, token")
 */
class AuthController extends BaseController
{
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

            // Create token using Sanctum
            $token = $user->createToken('auth-token')->plainTextToken;

            // Update last login
            $user->update(['last_login_at' => now()]);

            // Load relationships
            $user->load(['employee', 'roles.permissions']);

            return $this->successResponse([
                'user' => $user,
                'token' => $token,
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
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

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
     * Làm mới token
     *
     * @OA\Post(
     *     path="/api/auth/refresh",
     *     tags={"Auth"},
     *     summary="Làm mới token",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Token mới"),
     *     @OA\Response(response=401, description="Chưa đăng nhập")
     * )
     */
    public function refresh(Request $request)
    {
        try {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            // Create new token
            $token = $request->user()->createToken('auth-token')->plainTextToken;

            return $this->successResponse([
                'token' => $token,
            ], 'Token refreshed successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Token refresh failed');
        }
    }
}
