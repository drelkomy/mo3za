<?php

/**
 * اختبار رفع الملف لـ endpoint إكمال المرحلة
 * 
 * استخدم هذا الكود لاختبار رفع الملف:
 */

// مثال على استخدام cURL لاختبار رفع الملف
$curl_example = '
curl -X POST "https://moezez.com/api/tasks/complete-stage" \
-H "Authorization: Bearer YOUR_TOKEN" \
-H "Accept: application/json" \
-F "stage_id=521" \
-F "proof_notes=تم إكمال المرحلة بنجاح" \
-F "proof_image=@/path/to/your/image.jpg"
';

// مثال على استخدام JavaScript/FormData
$js_example = '
const formData = new FormData();
formData.append("stage_id", "521");
formData.append("proof_notes", "تم إكمال المرحلة بنجاح");
formData.append("proof_image", fileInput.files[0]); // من input file

fetch("/api/tasks/complete-stage", {
    method: "POST",
    headers: {
        "Authorization": "Bearer " + token,
        "Accept": "application/json"
    },
    body: formData
})
.then(response => response.json())
.then(data => console.log(data));
';

// التحقق من إعدادات PHP لرفع الملفات
echo "إعدادات PHP لرفع الملفات:\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
echo "file_uploads: " . (ini_get('file_uploads') ? 'enabled' : 'disabled') . "\n";

// التحقق من صلاحيات المجلد
$storageDir = '/mnt/81773bef-713d-42a3-b06e-133f9cc6f06e/mo3az-app/storage/app/public/stages';
echo "\nصلاحيات مجلد التخزين:\n";
echo "المجلد: $storageDir\n";
echo "موجود: " . (is_dir($storageDir) ? 'نعم' : 'لا') . "\n";
echo "قابل للكتابة: " . (is_writable($storageDir) ? 'نعم' : 'لا') . "\n";

echo "\nنصائح لحل مشكلة رفع الملف:\n";
echo "1. تأكد من أن Content-Type هو multipart/form-data\n";
echo "2. تأكد من أن حجم الملف أقل من 5MB\n";
echo "3. تأكد من أن نوع الملف jpeg, png, أو jpg\n";
echo "4. تأكد من صلاحيات مجلد storage\n";
echo "5. تحقق من logs Laravel للأخطاء\n";