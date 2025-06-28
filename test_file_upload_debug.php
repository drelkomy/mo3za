<?php

/**
 * ملف اختبار رفع الملف - تشخيص المشكلة
 */

echo "=== تشخيص مشكلة رفع الملف ===\n\n";

// 1. فحص إعدادات PHP
echo "1. إعدادات PHP:\n";
echo "   file_uploads: " . (ini_get('file_uploads') ? 'enabled' : 'disabled') . "\n";
echo "   upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "   post_max_size: " . ini_get('post_max_size') . "\n";
echo "   max_file_uploads: " . ini_get('max_file_uploads') . "\n";
echo "   memory_limit: " . ini_get('memory_limit') . "\n\n";

// 2. فحص مجلد التخزين
$storageDir = '/mnt/81773bef-713d-42a3-b06e-133f9cc6f06e/mo3az-app/storage/app/public/stages';
echo "2. مجلد التخزين:\n";
echo "   المسار: $storageDir\n";
echo "   موجود: " . (is_dir($storageDir) ? 'نعم' : 'لا') . "\n";
echo "   قابل للكتابة: " . (is_writable($storageDir) ? 'نعم' : 'لا') . "\n";
echo "   الصلاحيات: " . substr(sprintf('%o', fileperms($storageDir)), -4) . "\n\n";

// 3. مثال على الطلب الصحيح
echo "3. مثال على الطلب الصحيح:\n";
echo 'curl -X POST "https://moezez.com/api/tasks/complete-stage" \\' . "\n";
echo '-H "Authorization: Bearer YOUR_TOKEN" \\' . "\n";
echo '-H "Accept: application/json" \\' . "\n";
echo '-F "stage_id=533" \\' . "\n";
echo '-F "proof_notes=تم إكمال المرحلة" \\' . "\n";
echo '-F "proof_image=@/path/to/image.jpg"' . "\n\n";

// 4. فحص آخر logs
echo "4. للتحقق من logs:\n";
echo "   tail -f storage/logs/laravel.log | grep 'Complete stage\\|File\\|proof'\n\n";

// 5. نصائح التشخيص
echo "5. نصائح التشخيص:\n";
echo "   - تأكد من أن Content-Type هو multipart/form-data\n";
echo "   - تأكد من أن اسم الحقل هو 'proof_image' بالضبط\n";
echo "   - تأكد من أن الملف أقل من 5MB\n";
echo "   - تأكد من أن نوع الملف jpeg, png, أو jpg\n";
echo "   - تحقق من logs Laravel للأخطاء\n";

echo "\n=== انتهى التشخيص ===\n";