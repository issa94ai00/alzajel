<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($definition->name); ?></title>
    <style>
        @page { size: <?php echo e($paper === 'a5' ? 'A5 landscape' : 'A4 landscape'); ?>; margin: 10mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f1f5f9;
            font-family: "Cairo", "Segoe UI", Tahoma, sans-serif;
            font-size: 12px;
            color: #0f172a;
        }

        .sheet { max-width: 1100px; margin: 16px auto; background: #fff; padding: 20px; box-shadow: 0 1px 4px rgba(15,23,42,.15); }

        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 8px; }
        .company { font-size: 16px; font-weight: 700; }
        .muted { color: #475569; font-size: 11px; }
        h1 { font-size: 15px; margin: 12px 0 4px; }

        .filters { display: flex; flex-wrap: wrap; gap: 6px 18px; font-size: 11px; color: #334155; margin-bottom: 10px; }

        .totals { display: flex; flex-wrap: wrap; gap: 10px; margin: 12px 0; }
        .totals div { border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 12px; min-width: 120px; }
        .totals span { display: block; font-size: 10px; color: #475569; }
        .totals strong { font-size: 14px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: start; }
        thead th { background: #f1f5f9; font-size: 11px; }
        tbody tr:nth-child(even) { background: #f8fafc; }

        .foot { margin-top: 14px; display: flex; justify-content: space-between; font-size: 10px; color: #475569; }

        .toolbar { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #0f172a; }
        .toolbar button, .toolbar a { border: 0; border-radius: 6px; padding: 8px 16px; font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; background: #4f46e5; color: #fff; }
        .toolbar a.secondary { background: #334155; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; padding: 0; max-width: none; }
            thead { display: table-header-group; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">طباعة</button>
        <a class="secondary" href="<?php echo e($backUrl); ?>">رجوع</a>
    </div>

    <div class="sheet">
        <div class="head">
            <div>
                <div class="company"><?php echo e($company['name']); ?></div>
                <?php if($company['branch']): ?><div class="muted"><?php echo e($company['branch']); ?></div><?php endif; ?>
                <?php if($company['phone']): ?><div class="muted">هاتف: <?php echo e($company['phone']); ?></div><?php endif; ?>
            </div>
            <div class="muted" style="text-align: start">
                <div>تاريخ الطباعة: <?php echo e(now()->format('Y-m-d H:i')); ?></div>
                <div>طُبع بواسطة: <?php echo e($printedBy); ?></div>
            </div>
        </div>

        <?php if($headerText): ?><div class="muted" style="margin-top:6px"><?php echo e($headerText); ?></div><?php endif; ?>

        <h1><?php echo e($definition->name); ?></h1>
        <?php if($definition->description): ?><div class="muted"><?php echo e($definition->description); ?></div><?php endif; ?>

        <div class="filters">
            <?php $__empty_1 = true; $__currentLoopData = $appliedFilters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <span><strong><?php echo e($label); ?>:</strong> <?php echo e($value); ?></span>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <span>بدون فلاتر</span>
            <?php endif; ?>
        </div>

        <?php if(count($result->totals)): ?>
            <div class="totals">
                <?php $__currentLoopData = $result->totals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $total): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div>
                        <span><?php echo e($total['label']); ?></span>
                        <strong><?php echo e($format($total['value'], $total['format'] ?? 'text')); ?></strong>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <?php $__currentLoopData = $result->columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <th><?php echo e($column['label']); ?></th>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $result->rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <?php $__currentLoopData = $result->columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <td><?php echo e($format($row[$column['key']] ?? null, $column['format'] ?? 'text')); ?></td>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="<?php echo e(max(1, count($result->columns))); ?>" style="text-align:center; color:#64748b">
                            <?php echo e($result->meta['empty_message'] ?? 'لا توجد بيانات ضمن هذا النطاق.'); ?>

                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="foot">
            <span><?php echo e($footerText); ?></span>
            <span>عدد الصفوف: <?php echo e($result->rows->count()); ?></span>
        </div>
    </div>

    <?php if($autoPrint): ?>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    <?php endif; ?>
</body>
</html>
<?php /**PATH /var/www/bashar.damatech.sy/htdocs/resources/views/reports/print.blade.php ENDPATH**/ ?>