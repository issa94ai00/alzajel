/// عنوان الـ API — عدّله حسب بيئة التشغيل:
/// - محاكي Android يصل لسيرفر التطوير على جهازك عبر 10.0.2.2
/// - جهاز حقيقي متصل بالـ USB: استخدم `adb reverse tcp:PORT tcp:PORT` ثم 127.0.0.1
/// - جهاز حقيقي على نفس الشبكة اللاسلكية: استخدم عنوان IP الفعلي للجهاز المضيف
/// - سيرفر إنتاج: ضع دومين الـ API الفعلي
class ApiConfig {
  static const String baseUrl = 'https://alzajel.albahri.sy/api';
}
