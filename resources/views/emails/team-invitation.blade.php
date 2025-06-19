<x-mail::message>
# دعوة للانضمام إلى فريق {{ $invitation->sender->name }}

مرحباً،

تمت دعوتك للانضمام إلى فريق {{ $invitation->sender->name }} على منصة معز.

@if($invitation->message)
رسالة من {{ $invitation->sender->name }}:

{{ $invitation->message }}
@endif

<x-mail::button :url="route('invitations.show', $invitation->token)">
قبول الدعوة
</x-mail::button>

تنتهي صلاحية هذه الدعوة في {{ $invitation->expires_at->format('Y-m-d') }}.

شكراً،<br>
{{ config('app.name') }}
</x-mail::message>