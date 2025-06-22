<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\RegisterResource;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\Models\Area;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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
        $cacheKey = 'user_me_' . $request->user()->id;
        
        $userData = Cache::remember($cacheKey, 600, function () use ($request) {
            return new UserResource($request->user());
        });
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $userData,
            ]
        ])->setMaxAge(600)->setPublic();
    }

    /**
     * جلب بيانات البروفايل (معلومات المستخدم)
     */
    public function showProfile(Request $request)
    {
        $cacheKey = 'user_profile_' . $request->user()->id;
        
        $profileData = Cache::remember($cacheKey, 600, function () use ($request) {
            $user = $request->user()->load(['area', 'city', 'subscriptions.package']);
            return new ProfileResource($user);
        });
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $profileData,
            ]
        ])->setMaxAge(600)->setPublic();
    }

    /**
     * تحديث بيانات البروفايل
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        // Hash password only if it's provided and not empty
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            // Remove password from data array so it's not updated if empty
            unset($data['password']);
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar_url && Storage::disk('public')->exists($user->avatar_url)) {
                Storage::disk('public')->delete($user->avatar_url);
            }
            
            // Store the new file
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar_url = $path;

            // Remove the avatar file from the data array to prevent mass assignment issues
            unset($data['avatar']);
        }

        // Use fill for all other validated data
        $user->fill($data);
        $user->save();
        
        // تنظيف cache عند تحديث البروفايل
        Cache::forget('user_me_' . $user->id);
        Cache::forget('user_profile_' . $user->id);
        
        $user->load(['area', 'city']);
        return response()->json([
            'status' => 'success',
            'message' => 'تم تحديث البيانات بنجاح',
            'data' => [
                'user' => new ProfileResource($user),
            ]
        ]);
    }

    /**
     * تسجيل مستخدم جديد
     */
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'user_type' => 'member', //  النوع الافتراضي للعضو
            'is_active' => true, // or false if you want manual activation
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل المستخدم بنجاح',
            'data' => [
                'user' => new RegisterResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 201);
    }
}