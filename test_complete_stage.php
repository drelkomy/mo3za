<?php

/**
 * ملف اختبار endpoint إكمال المرحلة
 * 
 * استخدام:
 * POST /api/tasks/complete-stage
 * 
 * البيانات المطلوبة:
 * - stage_id: رقم المرحلة (مطلوب)
 * - proof_notes: ملاحظات الإثبات (اختياري)
 * - proof_image: صورة الإثبات (اختياري)
 * 
 * مثال على البيانات:
 */

$exampleData = [
    'stage_id' => 521,
    'proof_notes' => 'تمام التمام',
    // proof_image: ملف صورة (jpeg, png, jpg) بحد أقصى 5MB
];

/**
 * مثال على الاستجابة المتوقعة:
 */
$expectedResponse = [
    'message' => 'تم إكمال المرحلة بنجاح',
    'data' => [
        'id' => 521,
        'title' => 'المرحلة 1',
        'description' => 'المرحلة 1 من المهمة',
        'status' => 'completed',
        'stage_number' => 1,
        'completed_at' => '2025-06-28 00:00:00',
        'proof_notes' => 'تمام التمام',
        'proof_files' => [
            'path' => 'stages/xyz.jpg',
            'name' => 'proof.jpg',
            'size' => 12345
        ]
    ]
];

/**
 * الميزات المضافة:
 * - Rate Limiting: 30 طلب في الدقيقة
 * - التحقق من الصلاحيات
 * - التحقق من حالة المرحلة
 * - Database Transaction
 * - Cache Clearing
 * - معالجة الأخطاء
 * - تحسين الاستعلامات
 */

echo "Endpoint جاهز للاستخدام: POST /api/tasks/complete-stage\n";