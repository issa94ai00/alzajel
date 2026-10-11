# التقارير والإيصالات والإعدادات

توثيق للأنظمة الثلاثة المضافة: محرك تقارير قابل للتوسعة، نظام إيصالات متعدّد القوالب،
وصفحة إعدادات يتحكم منها المدير بكليهما.

## 1. الإعدادات

- الجدول: `settings` (مفتاح، مجموعة، قيمة JSON).
- القيم الافتراضية: `config/sahli.php` تحت `settings` — أي مفتاح غير مخزّن في الجدول يعود للقيمة الافتراضية،
  لذلك يعمل النظام بشكل صحيح حتى قبل تشغيل الـ seeder.
- القراءة/الكتابة: `App\Support\SettingsRepository` (singleton، يخزّن مؤقتاً لكل طلب).

```php
$settings = app(App\Support\SettingsRepository::class);
$settings->bool('receipts.enabled');
$settings->set('reports.page_size', 100);
```

- المشاركة مع الواجهة: `HandleInertiaRequests` يمرّر `settings` (اسم الجهة، التنسيق، حالة الإيصالات)،
  و`resources/js/composables/useFormat.js` يستخدمها لتنسيق الأرقام والتواريخ.
- الواجهة: `/settings` (للمدير فقط) — ثلاث تبويبات: عام، التقارير، الإيصالات.
- API: `GET /api/settings`.

المجموعات: `company.*`, `format.*`, `reports.*`, `receipts.*`. زر «استعادة الافتراضي» يحذف صفوف المجموعة
من الجدول فتعود القيم لـ `config/sahli.php`.

## 2. التقارير

### البنية

| الطبقة | الملف | الدور |
| --- | --- | --- |
| تعريف | `App\Models\ReportDefinition` | صف في `report_definitions`: اسم، تفعيل، صلاحية، ترتيب، خيارات |
| مُنشئ | `App\Reports\Builders\*` | يبني الأعمدة والصفوف والإجماليات |
| سجلّ | `App\Reports\ReportRegistry` | يربط `builder` بالصنف المسؤول |
| مشغّل | `App\Services\ReportRunner` | يطبّق الإعدادات (النطاق، الأعمدة المخفية، حجم الصفحة) ويصدّر CSV |

أنواع التقارير الجاهزة: `balances`, `transactions`, `currency_movement`, `shift_summary`,
`currency_totals`, `user_performance`, `customer_activity`, `profit_loss`.

المدير يستطيع إنشاء أكثر من تقرير من نفس النوع (مثلاً «عمليات اليوم» و«عمليات الشهر») بخيارات مختلفة.

### المسارات

```
GET /reports                      قائمة التقارير المتاحة
GET /reports/{key}                عرض تقرير
GET /reports/{key}/export?format=csv|print
GET /api/reports/catalog          الأنواع المتاحة (JSON)
GET /api/reports/run/{key}        تشغيل تقرير (JSON)
```

المسارات القديمة (`reports.balances`, `reports.daily`, `reports.currency-movement`,
`reports.profit-loss`) ما زالت تعمل وتعرض التقرير المقابل عبر نفس المحرك.

### إضافة نوع تقرير جديد

1. أنشئ صنفاً في `app/Reports/Builders` يرث `BaseReportBuilder`:

```php
class ProfitByHourReportBuilder extends BaseReportBuilder
{
    public function filters(): array
    {
        return [...$this->dateRangeFilters()];
    }

    public function build(array $filters, ?User $user = null): ReportResult
    {
        return new ReportResult(
            columns: [['key' => 'hour', 'label' => 'الساعة', 'format' => 'text']],
            rows: collect([...]),
            totals: [],
        );
    }
}
```

2. سجّله في `ReportRegistry::BUILDERS` و`LABELS`.
3. أضف تعريفاً افتراضياً في `config/sahli.php` تحت `reports` (اختياري)، أو أنشئه من صفحة الإعدادات.

صيغ الأعمدة المدعومة: `text`, `number`, `signed`, `integer`, `date`, `datetime`, `type`.
أعمدة الربح تظهر للمدير فقط عبر `$this->canSeeProfit($user)`.

## 3. الإيصالات

- الجدول: `receipt_templates` (نوع الإيصال، حجم الورق، عدد النسخ، افتراضي/مفعّل، خيارات JSON).
- الخدمة: `App\Services\ReceiptService` تبني بيانات الإيصال حسب القالب والإعدادات.
- العرض: `resources/views/receipts/print.blade.php` — RTL، مقاسات 58/80 ملم و A5/A4، زر طباعة،
  وقائمة لتبديل القالب، وتكرار الصفحة حسب عدد النسخ.
- القوالب الجاهزة: `thermal-80`, `thermal-58`, `invoice-a5` للعمليات، و`shift-a5`, `shift-80` للورديات.

### المسارات

```
GET /transactions/{transaction}/receipt?template=&autoprint=1
GET /shifts/{shift}/receipt?template=
GET /api/transactions/{transaction}/receipt      (JSON)
GET /api/shifts/{shift}/receipt                  (JSON)
GET /api/receipts/templates?subject=transaction|shift
```

محتوى الإيصال (العميل، السعر، الموظف، الملاحظات، الربح، التوقيع) يتحكم به المدير من تبويب الإيصالات،
وكذلك فتح الإيصال تلقائياً بعد تسجيل العملية.

## 4. بعد النشر

```bash
php artisan migrate --force
php artisan db:seed --class=SystemDefaultsSeeder --force   # التقارير والقوالب الافتراضية
npm run build
php artisan config:cache && php artisan route:cache
```

أول زيارة لصفحة `/settings` تزرع التعريفات الافتراضية تلقائياً إن لم تكن موجودة.
