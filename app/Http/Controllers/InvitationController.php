<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvitationController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'team_id' => 'required|exists:teams,id',
        ]);

        // التحقق من أن المستخدم هو مالك الفريق
        $team = \App\Models\Team::findOrFail($request->team_id);
        if ($team->owner_id !== Auth::id()) {
            return response()->json(['message' => 'غير مصرح لك بإرسال دعوات لهذا الفريق'], 403);
        }

        // التحقق من عدم وجود دعوة سابقة
        $existingInvitation = Invitation::where('team_id', $request->team_id)
            ->where('email', $request->email)
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            return response()->json(['message' => 'تم إرسال دعوة لهذا البريد الإلكتروني مسبقاً'], 422);
        }

        // إنشاء وإرسال الدعوة
        $invitation = Invitation::createAndSend(
            $request->team_id,
            Auth::id(),
            $request->email
        );

        return response()->json([
            'message' => 'تم إرسال الدعوة بنجاح',
            'invitation' => $invitation
        ]);
    }

    public function show(string $token)
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();
        
        if ($invitation->status !== 'pending') {
            return view('invitations.expired', ['invitation' => $invitation]);
        }
        
        return view('invitations.show', ['invitation' => $invitation]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();
        
        if ($invitation->status !== 'pending') {
            return redirect()->route('invitations.show', $token)
                ->with('error', 'هذه الدعوة غير صالحة أو تم استخدامها مسبقاً');
        }
        
        // التحقق من وجود المستخدم
        $user = User::where('email', $invitation->email)->first();
        
        if (!$user) {
            // إنشاء حساب جديد
            return redirect('/admin/register')
                ->with('invitation_token', $token)
                ->with('email', $invitation->email);
        }
        
        // إضافة المستخدم للفريق
        $invitation->team->addMember($user->id);
        $invitation->accept();
        
        return redirect('/admin')
            ->with('success', 'تم قبول الدعوة بنجاح!');
    }

    public function reject(string $token)
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();
        
        if ($invitation->status === 'pending') {
            $invitation->reject();
        }
        
        return redirect('/admin')
            ->with('info', 'تم رفض الدعوة');
    }

    // اختبار إرسال الدعوة بدون طابور (للاختبار فقط)
    public function testSendWithoutQueue(int $invitationId)
    {
        $invitation = Invitation::findOrFail($invitationId);
        $invitation->sendWithoutQueue();
        
        return response()->json([
            'message' => 'تم إرسال الدعوة مباشرة بدون طابور',
            'invitation' => $invitation
        ]);
    }
}