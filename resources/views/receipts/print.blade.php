<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $receipt['title'] }} — {{ $receipt['number'] }}</title>
    <style>
        @page {
            size: {{ $receipt['template']['paper_spec']['width'] }} auto;
            margin: {{ $receipt['template']['is_thermal'] ? '3mm' : '12mm' }};
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
            font-family: "Cairo", "Segoe UI", Tahoma, sans-serif;
            font-size: {{ $receipt['template']['paper_spec']['font'] }};
            color: #0f172a;
        }

        .sheet {
            width: {{ $receipt['template']['paper_spec']['width'] }};
            max-width: 100%;
            margin: 16px auto;
            padding: {{ $receipt['template']['is_thermal'] ? '8px 10px' : '18px 22px' }};
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
        @if (count($templates) > 1)
            <select onchange="window.location.search = '?template=' + this.value">
                @foreach ($templates as $option)
                    <option value="{{ $option->key }}" @selected($option->key === $receipt['template']['key'])>
                        {{ $option->name }} ({{ $option->paper }})
                    </option>
                @endforeach
            </select>
        @endif
        <a class="secondary" href="{{ $backUrl }}">رجوع</a>
    </div>

    @for ($copy = 1; $copy <= $receipt['template']['copies']; $copy++)
        <div class="sheet {{ $copy < $receipt['template']['copies'] ? 'page-break' : '' }}">
            <div class="head">
                @if ($receipt['company']['show_logo'] && $receipt['company']['logo_text'])
                    <div class="logo">{{ $receipt['company']['logo_text'] }}</div>
                @endif
                <div class="company">{{ $receipt['company']['name'] }}</div>
                @if ($receipt['company']['branch'])
                    <div class="muted">{{ $receipt['company']['branch'] }}</div>
                @endif
                @if ($receipt['company']['address'])
                    <div class="muted">{{ $receipt['company']['address'] }}</div>
                @endif
                @if ($receipt['company']['phone'])
                    <div class="muted">هاتف: {{ $receipt['company']['phone'] }}</div>
                @endif
                @if ($receipt['company']['tax_number'])
                    <div class="muted">الرقم الضريبي: {{ $receipt['company']['tax_number'] }}</div>
                @endif
                @if ($receipt['header_text'])
                    <div class="muted">{{ $receipt['header_text'] }}</div>
                @endif
                <div class="title">{{ $receipt['title'] }}</div>
                @if ($receipt['template']['copies'] > 1)
                    <div class="muted">نسخة {{ $copy }} من {{ $receipt['template']['copies'] }}</div>
                @endif
            </div>

            <div class="meta">
                <span>رقم: {{ $receipt['number'] }}</span>
                <span>{{ $receipt['issued_at'] }}</span>
            </div>

            <table class="lines">
                @foreach ($receipt['lines'] as $line)
                    <tr class="{{ ($line['emphasis'] ?? false) ? 'emphasis' : '' }}">
                        <td>{{ $line['label'] }}</td>
                        <td>{{ $line['value'] }} {{ $line['suffix'] ?? '' }}</td>
                    </tr>
                @endforeach
            </table>

            @foreach ($receipt['tables'] as $table)
                <div class="section-title">{{ $table['title'] }}</div>
                <table class="grid">
                    <thead>
                        <tr>
                            @foreach ($table['headers'] as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($table['rows'] as $row)
                            <tr>
                                @foreach ($row as $cell)
                                    <td>{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($table['headers']) }}">لا توجد بيانات.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endforeach

            @if ($receipt['show_signature'])
                <div class="signature">
                    <div>توقيع العميل</div>
                    <div>توقيع الموظف</div>
                </div>
            @endif

            @if (!empty($receipt['currency_note']))
                <div class="currency-note">{{ $receipt['currency_note'] }}</div>
            @endif

            @if ($receipt['footer_text'])
                <div class="note">{{ $receipt['footer_text'] }}</div>
            @endif

            @if ($receipt['template']['is_thermal'])
                <div class="cut">- - - - - - - - - - - - - - -</div>
            @endif
        </div>
    @endfor

    @if ($autoPrint)
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
