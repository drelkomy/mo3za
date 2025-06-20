<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * تسجيل الدخول وإنشاء رمز الوصول
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الاعتماد المقدمة غير صحيحة.'],
            ]);
        }

        // التحقق من أن المستخدم نشط
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['هذا الحساب غير نشط. يرجى التواصل مع الإدارة.'],
            ]);
        }

        // إنشاء رمز وصول جديد
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    /**
     * تسجيل الخروج وإلغاء رمز الوصول الحالي
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الخروج بنجاح',
        ]);
    }

    /**
     * الحصول على بيانات المستخدم الحالي
     */
    public function me(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => new UserResource($request->user()),
            ]
        ]);
    }

    /**
     * جلب بيانات البروفايل (معلومات المستخدم)
     */
    public function showProfile(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => new ProfileResource($request->user()),
            ]
        ]);
    }

    /**
     * تحديث بيانات البروفايل
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        $user->fill($data);
        $user->save();
        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث البيانات بنجاح',
            'data' => [
                'user' => new ProfileResource($user),
            ]
        ]);
    }
}