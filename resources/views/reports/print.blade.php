<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $definition->name }}</title>
    <style>
        @page { size: {{ $paper === 'a5' ? 'A5 landscape' : 'A4 landscape' }}; margin: 10mm; }

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
        <a class="secondary" href="{{ $backUrl }}">رجوع</a>
    </div>

    <div class="sheet">
        <div class="head">
            <div>
                <div class="company">{{ $company['name'] }}</div>
                @if ($company['branch'])<div class="muted">{{ $company['branch'] }}</div>@endif
                @if ($company['phone'])<div class="muted">هاتف: {{ $company['phone'] }}</div>@endif
            </div>
            <div class="muted" style="text-align: start">
                <div>تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}</div>
                <div>طُبع بواسطة: {{ $printedBy }}</div>
            </div>
        </div>

        @if ($headerText)<div class="muted" style="margin-top:6px">{{ $headerText }}</div>@endif

        <h1>{{ $definition->name }}</h1>
        @if ($definition->description)<div class="muted">{{ $definition->description }}</div>@endif

        <div class="filters">
            @forelse ($appliedFilters as $label => $value)
                <span><strong>{{ $label }}:</strong> {{ $value }}</span>
            @empty
                <span>بدون فلاتر</span>
            @endforelse
        </div>

        @if (count($result->totals))
            <div class="totals">
                @foreach ($result->totals as $total)
                    <div>
                        <span>{{ $total['label'] }}</span>
                        <strong>{{ $format($total['value'], $total['format'] ?? 'text') }}</strong>
                    </div>
                @endforeach
            </div>
        @endif

        <table>
            <thead>
                <tr>
                    @foreach ($result->columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($result->rows as $row)
                    <tr>
                        @foreach ($result->columns as $column)
                            <td>{{ $format($row[$column['key']] ?? null, $column['format'] ?? 'text') }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(1, count($result->columns)) }}" style="text-align:center; color:#64748b">
                            {{ $result->meta['empty_message'] ?? 'لا توجد بيانات ضمن هذا النطاق.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="foot">
            <span>{{ $footerText }}</span>
            <span>عدد الصفوف: {{ $result->rows->count() }}</span>
        </div>
    </div>

    @if ($autoPrint)
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
