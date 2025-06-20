<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دعوة انضمام للفريق</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; direction: rtl; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #006E82; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 30px; }
        .button { background: #006E82; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>دعوة انضمام للفريق</h1>
        </div>
        <div class="content">
            <h2>مرحباً!</h2>
            <p>تم دعوتك للانضمام إلى فريق <strong>{{ $invitation->team->name }}</strong></p>
            <p>من قبل: <strong>{{ $invitation->sender->name }}</strong></p>
            
            <a href="{{ $acceptUrl }}" class="button">قبول الدعوة</a>
            
            <p>إذا لم تكن تتوقع هذه الدعوة، يمكنك تجاهل هذا الإيميل.</p>
            
            <hr>
            <small>نظام إدارة المعزز</small>
        </div>
    </div>
</body>
</html>