<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>نسيت كلمة المرور</title>
</head>
<body>
    <h2>نسيت كلمة المرور</h2>

    @if (session('status'))
        <p style="color: green">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('filament.admin.auth.password.email') }}">
        @csrf
        <input type="email" name="email" placeholder="البريد الإلكتروني" required>
        @error('email')
            <div style="color: red">{{ $message }}</div>
        @enderror
        <button type="submit">إرسال رابط إعادة التعيين</button>
    </form>
</body>
</html>
