<?php

/*
|--------------------------------------------------------------------------
| إعدادات النظام الافتراضية
|--------------------------------------------------------------------------
|
| القيم هنا هي المرجع الافتراضي لكل مفتاح إعداد. جدول `settings` يخزّن فقط
| القيم التي عدّلها المدير، وأي مفتاح غير موجود في الجدول يعود لهذه القيم.
|
*/

return [

    'settings' => [
        // معلومات الجهة — تظهر في ترويسة الإيصالات والتقارير
        'company.name' => 'Sahli Soft',
        'company.branch' => '',
        'company.phone' => '',
        'company.address' => '',
        'company.tax_number' => '',
        'company.logo_text' => '',

        // التنسيق العام
        'format.decimals' => 2,
        'format.thousands_separator' => true,
        'format.locale' => 'ar-SY',

        // التقارير
        'reports.default_range' => 'today',      // today | yesterday | this_week | this_month | last_30
        'reports.page_size' => 50,
        'reports.show_totals' => true,
        'reports.export_formats' => ['print', 'csv'],
        'reports.print_paper' => 'a4',           // a4 | a5
        'reports.print_header' => '',
        'reports.print_footer' => '',

        // الإيصالات
        'receipts.enabled' => true,
        'receipts.auto_open_after_transaction' => false,
        'receipts.default_transaction_template' => 'thermal-80',
        'receipts.default_shift_template' => 'shift-a5',
        'receipts.footer_note' => 'شكراً لتعاملكم معنا',
        'receipts.show_operator' => true,
        'receipts.show_customer' => true,
        'receipts.show_notes' => true,
        'receipts.show_rate' => true,
        'receipts.show_profit' => false,
        'receipts.show_signature' => false,
        'receipts.number_prefix' => 'TX-',

        // الليرة السورية الجديدة — حُذف صفران من العملة القديمة.
        // إرشادات انتقالية للصرّاف؛ تُطفأ عند انتهاء فترة الاعتياد.
        'currency.new_lira_notice' => true,
        // تحذير عند إدخال مبلغ ليرة كبير يوحي بأنه أُدخل بالعملة القديمة.
        // القيمة بالليرة الجديدة، و0 تعني تعطيل التحذير.
        'currency.old_lira_warn_above' => 100000,
    ],

    /*
    | أوراق الطباعة المدعومة للإيصالات والتقارير.
    */
    'papers' => [
        '58mm' => ['label' => 'حراري 58 ملم', 'width' => '58mm', 'font' => '11px'],
        '80mm' => ['label' => 'حراري 80 ملم', 'width' => '80mm', 'font' => '12px'],
        'a5' => ['label' => 'A5', 'width' => '148mm', 'font' => '13px'],
        'a4' => ['label' => 'A4', 'width' => '210mm', 'font' => '13px'],
    ],

    /*
    | التقارير الافتراضية التي يزرعها Seeder — المدير يستطيع تفعيلها أو
    | إخفاءها أو إعادة ترتيبها من صفحة الإعدادات.
    */
    'reports' => [
        [
            'key' => 'balances',
            'builder' => 'balances',
            'name' => 'أرصدة الوردية',
            'description' => 'أرصدة العملات في الوردية المفتوحة حالياً.',
            'admin_only' => false,
            'sort_order' => 10,
        ],
        [
            'key' => 'daily-operations',
            'builder' => 'transactions',
            'name' => 'العمليات اليومية',
            'description' => 'كل عمليات الصرف ضمن نطاق تاريخي.',
            'admin_only' => false,
            'sort_order' => 20,
        ],
        [
            'key' => 'currency-movement',
            'builder' => 'currency_movement',
            'name' => 'حركة عملة',
            'description' => 'حركة الشراء والبيع لعملة محددة.',
            'admin_only' => false,
            'sort_order' => 30,
        ],
        [
            'key' => 'shift-summary',
            'builder' => 'shift_summary',
            'name' => 'ملخص الورديات',
            'description' => 'عدد العمليات وحجم التداول لكل وردية.',
            'admin_only' => false,
            'sort_order' => 40,
        ],
        [
            'key' => 'currency-totals',
            'builder' => 'currency_totals',
            'name' => 'إجماليات العملات',
            'description' => 'إجمالي المشتريات والمبيعات لكل عملة.',
            'admin_only' => false,
            'sort_order' => 50,
        ],
        [
            'key' => 'user-performance',
            'builder' => 'user_performance',
            'name' => 'أداء الموظفين',
            'description' => 'عدد العمليات والأرباح لكل موظف.',
            'admin_only' => true,
            'sort_order' => 60,
        ],
        [
            'key' => 'customer-activity',
            'builder' => 'customer_activity',
            'name' => 'نشاط العملاء',
            'description' => 'العملاء الأكثر تعاملاً خلال الفترة.',
            'admin_only' => true,
            'sort_order' => 70,
        ],
        [
            'key' => 'profit-loss',
            'builder' => 'profit_loss',
            'name' => 'الأرباح والخسائر',
            'description' => 'أرباح كل وردية ضمن النطاق المحدد.',
            'admin_only' => true,
            'sort_order' => 80,
        ],
    ],

    /*
    | قوالب الإيصالات الافتراضية.
    */
    'receipt_templates' => [
        [
            'key' => 'thermal-80',
            'subject' => 'transaction',
            'name' => 'إيصال حراري 80 ملم',
            'paper' => '80mm',
            'is_default' => true,
            'copies' => 1,
            'options' => [
                'title' => 'إيصال عملية صرف',
                'show_logo' => true,
                'show_qr' => false,
                'header_text' => '',
                'footer_text' => '',
            ],
        ],
        [
            'key' => 'thermal-58',
            'subject' => 'transaction',
            'name' => 'إيصال حراري 58 ملم',
            'paper' => '58mm',
            'is_default' => false,
            'copies' => 1,
            'options' => [
                'title' => 'إيصال عملية صرف',
                'show_logo' => false,
                'show_qr' => false,
                'header_text' => '',
                'footer_text' => '',
            ],
        ],
        [
            'key' => 'invoice-a5',
            'subject' => 'transaction',
            'name' => 'فاتورة A5',
            'paper' => 'a5',
            'is_default' => false,
            'copies' => 2,
            'options' => [
                'title' => 'فاتورة صرف عملات',
                'show_logo' => true,
                'show_qr' => false,
                'header_text' => '',
                'footer_text' => 'نسخة العميل / نسخة المكتب',
            ],
        ],
        [
            'key' => 'shift-a5',
            'subject' => 'shift',
            'name' => 'تقرير إغلاق وردية A5',
            'paper' => 'a5',
            'is_default' => true,
            'copies' => 1,
            'options' => [
                'title' => 'كشف إغلاق وردية',
                'show_logo' => true,
                'show_qr' => false,
                'header_text' => '',
                'footer_text' => '',
            ],
        ],
        [
            'key' => 'shift-80',
            'subject' => 'shift',
            'name' => 'كشف وردية حراري 80 ملم',
            'paper' => '80mm',
            'is_default' => false,
            'copies' => 1,
            'options' => [
                'title' => 'كشف إغلاق وردية',
                'show_logo' => false,
                'show_qr' => false,
                'header_text' => '',
                'footer_text' => '',
            ],
        ],
    ],
];
