<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\TeamInvitation;
use Illuminate\Support\Facades\Auth;

class InvitationController extends Controller
{
    /**
     * إنشاء دعوة جديدة
     */
    public function create(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'message' => 'nullable|string',
        ]);

        // التحقق من وجود المستخدم
        $existingUser = User::where('email', $request->email)->first();

        // إنشاء الدعوة
        $invitation = Invitation::create([
            'sender_id' => Auth::id(),
            'email' => $request->email,
            'name' => $request->name,
            'phone' => $request->phone,
            'token' => Str::random(32),
            'team_invitation' => true,
            'message' => $request->message,
            'expires_at' => now()->addDays(7),
        ]);

        // إرسال البريد الإلكتروني
        try {
            Mail::to($request->email)->send(new TeamInvitation($invitation));
        } catch (\Exception $e) {
            // تسجيل الخطأ
        }

        return redirect()->back()->with('success', 'تم إرسال الدعوة بنجاح');
    }

    /**
     * عرض صفحة قبول الدعوة
     */
    public function show($token)
    {
        $invitation = Invitation::where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (!$invitation || $invitation->isExpired()) {
            return redirect()->route('login')->with('error', 'الدعوة غير صالحة أو منتهية الصلاحية');
        }

        return view('invitations.accept', compact('invitation'));
    }

    /**
     * قبول الدعوة
     */
    public function accept(Request $request, $token)
    {
        $invitation = Invitation::where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (!$invitation || $invitation->isExpired()) {
            return redirect()->route('login')->with('error', 'الدعوة غير صالحة أو منتهية الصلاحية');
        }

        // التحقق من وجود المستخدم
        $user = User::where('email', $invitation->email)->first();

        if (!$user) {
            // إنشاء مستخدم جديد
            $request->validate([
                'name' => 'required|string|max:255',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'name' => $request->name ?? $invitation->name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'phone' => $invitation->phone,
            ]);

            $user->assignRole('مستخدم');
        }

        // إضافة المستخدم إلى فريق المرسل
        $sender = $invitation->sender;
        
        // التحقق من وجود فريق للمرسل، وإنشاء واحد إذا لم يكن موجوداً
        $team = Team::where('owner_id', $sender->id)->first();
        
        if (!$team) {
            $team = Team::create([
                'owner_id' => $sender->id,
                'name' => 'فريق ' . $sender->name,
            ]);
        }

        // إضافة المستخدم إلى الفريق إذا لم يكن موجوداً بالفعل
        if (!$team->members()->where('user_id', $user->id)->exists()) {
            $team->members()->attach($user->id);
        }

        // تحديث حالة الدعوة
        $invitation->update(['status' => 'accepted']);

        // تسجيل الدخول للمستخدم
        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'تم قبول الدعوة بنجاح');
    }

    /**
     * رفض الدعوة
     */
    public function reject($token)
    {
        $invitation = Invitation::where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (!$invitation || $invitation->isExpired()) {
            return redirect()->route('login')->with('error', 'الدعوة غير صالحة أو منتهية الصلاحية');
        }

        // تحديث حالة الدعوة
        $invitation->update(['status' => 'rejected']);

        return redirect()->route('login')->with('success', 'تم رفض الدعوة');
    }
}