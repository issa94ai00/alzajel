<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($receipt['title']); ?> — <?php echo e($receipt['number']); ?></title>
    <style>
        @page {
            size: <?php echo e($receipt['template']['paper_spec']['width']); ?> auto;
            margin: <?php echo e($receipt['template']['is_thermal'] ? '3mm' : '12mm'); ?>;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
            font-family: "Cairo", "Segoe UI", Tahoma, sans-serif;
            font-size: <?php echo e($receipt['template']['paper_spec']['font']); ?>;
            color: #0f172a;
        }

        .sheet {
            width: <?php echo e($receipt['template']['paper_spec']['width']); ?>;
            max-width: 100%;
            margin: 16px auto;
            padding: <?php echo e($receipt['template']['is_thermal'] ? '8px 10px' : '18px 22px'); ?>;
            background: #fff;
            box-shadow: 0 1px 4px rgba(15, 23, 42, .15);
        }

        .head { text-align: center; border-bottom: 1px dashed #94a3b8; padding-bottom: 8px; }
        .logo { font-size: 1.35em; font-weight: 700; letter-spacing: .5px; }
        .company { font-size: 1.1em; font-weight: 700; }
        .muted { color: #475569; font-size: .85em; }
        .title { margin-top: 8px; font-weight: 700; font-size: 1.05em; }

        .meta { display: flex; justify-content: space-between; gap: 8px; font-size: .85em; padding: 6px 0; color: #334155; }

        .lines { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .lines td { padding: 4px 0; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
        .lines td:first-child { color: #475569; white-space: nowrap; }
        .lines td:last-child { text-align: end; font-weight: 600; }
        .lines tr.emphasis td { font-size: 1.1em; font-weight: 700; color: #0f172a; }

        table.grid { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: .9em; }
        table.grid th, table.grid td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: start; }
        table.grid th { background: #f8fafc; }

        .section-title { margin-top: 12px; font-weight: 700; font-size: .95em; }
        .note { margin-top: 10px; text-align: center; font-size: .85em; color: #475569; }
        .currency-note {
            margin-top: 10px;
            padding: 5px 7px;
            border: 1px dashed #64748b;
            text-align: center;
            font-size: .8em;
            font-weight: 700;
            color: #0f172a;
        }
        .signature { margin-top: 26px; display: flex; justify-content: space-between; gap: 16px; font-size: .85em; }
        .signature div { flex: 1; border-top: 1px solid #94a3b8; padding-top: 4px; text-align: center; }
        .cut { text-align: center; color: #94a3b8; margin: 10px 0; }

        .toolbar {
            position: sticky; top: 0; display: flex; gap: 8px; justify-content: center;
            padding: 10px; background: #0f172a;
        }
        .toolbar button, .toolbar a {
            border: 0; border-radius: 6px; padding: 8px 16px; font-family: inherit; font-size: 13px;
            font-weight: 600; cursor: pointer; text-decoration: none; background: #4f46e5; color: #fff;
        }
        .toolbar a.secondary { background: #334155; }
        .toolbar select { border-radius: 6px; border: 0; padding: 8px; font-family: inherit; font-size: 13px; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; padding: 0; }
            .page-break { page-break-after: always; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">طباعة</button>
        <?php if(count($templates) > 1): ?>
            <select onchange="window.location.search = '?template=' + this.value">
                <?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($option->key); ?>" <?php if($option->key === $receipt['template']['key']): echo 'selected'; endif; ?>>
                        <?php echo e($option->name); ?> (<?php echo e($option->paper); ?>)
                    </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        <?php endif; ?>
        <a class="secondary" href="<?php echo e($backUrl); ?>">رجوع</a>
    </div>

    <?php for($copy = 1; $copy <= $receipt['template']['copies']; $copy++): ?>
        <div class="sheet <?php echo e($copy < $receipt['template']['copies'] ? 'page-break' : ''); ?>">
            <div class="head">
                <?php if($receipt['company']['show_logo'] && $receipt['company']['logo_text']): ?>
                    <div class="logo"><?php echo e($receipt['company']['logo_text']); ?></div>
                <?php endif; ?>
                <div class="company"><?php echo e($receipt['company']['name']); ?></div>
                <?php if($receipt['company']['branch']): ?>
                    <div class="muted"><?php echo e($receipt['company']['branch']); ?></div>
                <?php endif; ?>
                <?php if($receipt['company']['address']): ?>
                    <div class="muted"><?php echo e($receipt['company']['address']); ?></div>
                <?php endif; ?>
                <?php if($receipt['company']['phone']): ?>
                    <div class="muted">هاتف: <?php echo e($receipt['company']['phone']); ?></div>
                <?php endif; ?>
                <?php if($receipt['company']['tax_number']): ?>
                    <div class="muted">الرقم الضريبي: <?php echo e($receipt['company']['tax_number']); ?></div>
                <?php endif; ?>
                <?php if($receipt['header_text']): ?>
                    <div class="muted"><?php echo e($receipt['header_text']); ?></div>
                <?php endif; ?>
                <div class="title"><?php echo e($receipt['title']); ?></div>
                <?php if($receipt['template']['copies'] > 1): ?>
                    <div class="muted">نسخة <?php echo e($copy); ?> من <?php echo e($receipt['template']['copies']); ?></div>
                <?php endif; ?>
            </div>

            <div class="meta">
                <span>رقم: <?php echo e($receipt['number']); ?></span>
                <span><?php echo e($receipt['issued_at']); ?></span>
            </div>

            <table class="lines">
                <?php $__currentLoopData = $receipt['lines']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="<?php echo e(($line['emphasis'] ?? false) ? 'emphasis' : ''); ?>">
                        <td><?php echo e($line['label']); ?></td>
                        <td><?php echo e($line['value']); ?> <?php echo e($line['suffix'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </table>

            <?php $__currentLoopData = $receipt['tables']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $table): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="section-title"><?php echo e($table['title']); ?></div>
                <table class="grid">
                    <thead>
                        <tr>
                            <?php $__currentLoopData = $table['headers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $header): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <th><?php echo e($header); ?></th>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $table['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <?php $__currentLoopData = $row; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cell): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <td><?php echo e($cell); ?></td>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="<?php echo e(count($table['headers'])); ?>">لا توجد بيانات.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <?php if($receipt['show_signature']): ?>
                <div class="signature">
                    <div>توقيع العميل</div>
                    <div>توقيع الموظف</div>
                </div>
            <?php endif; ?>

            <?php if(!empty($receipt['currency_note'])): ?>
                <div class="currency-note"><?php echo e($receipt['currency_note']); ?></div>
            <?php endif; ?>

            <?php if($receipt['footer_text']): ?>
                <div class="note"><?php echo e($receipt['footer_text']); ?></div>
            <?php endif; ?>

            <?php if($receipt['template']['is_thermal']): ?>
                <div class="cut">- - - - - - - - - - - - - - -</div>
            <?php endif; ?>
        </div>
    <?php endfor; ?>

    <?php if($autoPrint): ?>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    <?php endif; ?>
</body>
</html>
<?php /**PATH /var/www/bashar.damatech.sy/htdocs/resources/views/receipts/print.blade.php ENDPATH**/ ?>