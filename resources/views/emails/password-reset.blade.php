<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
            direction: rtl;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
            line-height: 1.6;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
            <p>إعادة تعيين كلمة المرور</p>
        </div>
        
        <div class="content">
            <h2>مرحباً {{ $user->name }}</h2>
            
            <p>لقد تلقيت هذا البريد لأنك طلبت إعادة تعيين كلمة المرور لحسابك.</p>
            
            <p>اضغط على الزر أدناه لإعادة تعيين كلمة المرور:</p>
            
            <div style="text-align: center;">
                <a href="{{ $url }}" class="button">إعادة تعيين كلمة المرور</a>
            </div>
            
            <p>إذا لم تطلب إعادة تعيين كلمة المرور، فلا داعي لاتخاذ أي إجراء.</p>
            
            <p><strong>ملاحظة:</strong> هذا الرابط صالح لمدة 60 دقيقة فقط.</p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }}. جميع الحقوق محفوظة.</p>
            <p>إذا واجهت مشكلة في الضغط على الزر، انسخ الرابط التالي والصقه في المتصفح:</p>
            <p style="word-break: break-all;">{{ $url }}</p>
        </div>
    </div>
</body>
</html>