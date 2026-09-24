<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account Credentials — ReadSmart</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 24px;
            font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
            background: #f1f5f9; color: #0f172a;
        }
        .wrap { max-width: 1000px; margin: 0 auto; }
        .bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        h1 { font-size: 20px; margin: 0; }
        .actions a, .actions button {
            display: inline-block; padding: 10px 18px; border-radius: 8px; border: 0;
            font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; margin-left: 8px;
        }
        .print { background: #2563eb; color: #fff; }
        .back { background: #e2e8f0; color: #334155; }
        .warn {
            background: #fef3c7; border: 1px solid #fcd34d; color: #92400e;
            padding: 12px 14px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;
        }
        .errors {
            background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
            padding: 12px 14px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;
        }
        .errors ul { margin: 6px 0 0; padding-left: 20px; }
        .slips { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; }
        .slip {
            background: #fff; border: 2px dashed #94a3b8; border-radius: 10px; padding: 16px;
            page-break-inside: avoid; break-inside: avoid;
        }
        .slip h3 { margin: 0 0 2px; font-size: 15px; }
        .slip .meta { color: #64748b; font-size: 12px; margin-bottom: 12px; }
        .field { margin-bottom: 8px; }
        .field span { display: block; font-size: 11px; text-transform: uppercase; color: #64748b; letter-spacing: .04em; }
        .field strong {
            display: block; font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 16px; letter-spacing: .04em; word-break: break-all;
        }
        .brand { margin-top: 12px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; }
        .empty { background: #fff; border-radius: 10px; padding: 40px; text-align: center; color: #64748b; }

        @media print {
            body { background: #fff; padding: 0; }
            .bar, .warn, .errors { display: none !important; }
            .slips { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .slip { border-color: #000; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="bar">
        <h1>Account Credentials</h1>
        <div class="actions">
            <a href="{{ route('admin.dashboard') }}" class="back">Back to dashboard</a>
            <button class="print" onclick="window.print()">Print</button>
        </div>
    </div>

    @if (!empty($importErrors) && count($importErrors))
    <div class="errors">
        <strong>Some rows were skipped:</strong>
        <ul>
            @foreach ($importErrors as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

    @if (count($credentials))
        <div class="warn">
            <strong>Print or save this now.</strong> If this page is lost, you can print these slips again from the
            Students tab (tick the students, then "Print selected slips") — but only until the student changes
            their password. After that, the account will need a password reset.
        </div>

        <div class="slips">
            @foreach ($credentials as $c)
                <div class="slip">
                    <h3>{{ $c['name'] }}</h3>
                    <div class="meta">
                        @if ($c['type'] === 'student')
                            {{ $c['grade_level'] }} &middot; {{ $c['section'] }}
                        @else
                            Parent of {{ $c['child_name'] ?: $c['child_lrn'] }}
                        @endif
                    </div>

                    <div class="field">
                        <span>{{ $c['login_label'] }}</span>
                        <strong>{{ $c['login'] }}</strong>
                    </div>
                    <div class="field">
                        <span>Password</span>
                        <strong>{{ $c['password'] }}</strong>
                    </div>

                    <div class="brand">ReadSmart &middot; keep this slip private</div>
                </div>
            @endforeach
        </div>
    @else
        <div class="empty">
            No credentials to show. Slips appear here right after a bulk create or when you use
            "Print selected slips" on the Students tab — reloading this page clears them.
        </div>
    @endif
</div>
</body>
</html>