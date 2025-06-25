<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إعادة تعيين كلمة المرور</title>
</head>
<body>
    <h2>إعادة تعيين كلمة المرور</h2>

    @if (session('status'))
        <p style="color: green">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('filament.admin.auth.password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="email" name="email" placeholder="البريد الإلكتروني" required>
        @error('email')
            <div style="color: red">{{ $message }}</div>
        @enderror
        <input type="password" name="password" placeholder="كلمة المرور الجديدة" required>
        @error('password')
            <div style="color: red">{{ $message }}</div>
        @enderror
        <input type="password" name="password_confirmation" placeholder="تأكيد كلمة المرور" required>
        <button type="submit">إعادة تعيين كلمة المرور</button>
    </form>
</body>
</html>
