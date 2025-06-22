# إعداد Cron Job لمعالجة Queue الإيميلات

## إضافة Cron Job:

```bash
# فتح crontab
crontab -e

# إضافة هذا السطر لتشغيل كل دقيقة
* * * * * cd /mnt/81773bef-713d-42a3-b06e-133f9cc6f06e/mo3az-app && php artisan queue:process-emails --timeout=30 >> /dev/null 2>&1

# أو لتشغيل كل 5 دقائق
*/5 * * * * cd /mnt/81773bef-713d-42a3-b06e-133f9cc6f06e/mo3az-app && php artisan queue:process-emails --timeout=60 >> /dev/null 2>&1
```

## اختبار Command:

```bash
# اختبار مباشر
php artisan queue:process-emails

# اختبار مع timeout مخصص
php artisan queue:process-emails --timeout=60
```

## مميزات هذا الحل:

- ✅ **يتوقف تلقائياً**: عند انتهاء المهام
- ✅ **خفيف على السيرفر**: يعمل لفترة محددة فقط
- ✅ **آمن**: لا يستهلك ذاكرة زائدة
- ✅ **بسيط**: سهل الإدارة والمراقبة