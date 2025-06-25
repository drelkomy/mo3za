<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تم إعادة تعيين كلمة المرور</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            max-width: 400px;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        h2 {
            color: #343a40;
            margin-bottom: 20px;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #006E82;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #005160;
        }
    </style>
    <script>
        setTimeout(function() {
            window.location.href = "/";
        }, 3000);
    </script>
</head>
<body>
    <div class="container">
        <h2>تم إعادة تعيين كلمة المرور</h2>
        <div class="alert alert-success">
            تم تغيير كلمة المرور بنجاح. سيتم توجيهك إلى الصفحة الرئيسية تلقائيًا خلال لحظات.
        </div>
        <button onclick="window.location.href = '/';">الانتقال إلى الصفحة الرئيسية</button>
    </div>
</body>
</html>
