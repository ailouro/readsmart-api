<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard — ReadSmart</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
            background: #f1f5f9; color: #0f172a;
        }
        header {
            background: #1e293b; color: #fff; padding: 16px 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        header h1 { margin: 0; font-size: 18px; }
        header form { margin: 0; }
        header button {
            background: #334155; color: #fff; border: 0; padding: 8px 14px;
            border-radius: 6px; cursor: pointer; font-size: 14px;
        }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px; }
        .tabs { display: flex; gap: 4px; border-bottom: 2px solid #cbd5e1; margin-bottom: 24px; }
        .tabs a {
            padding: 10px 20px; text-decoration: none; color: #475569;
            font-weight: 600; font-size: 14px; border-radius: 8px 8px 0 0;
        }
        .tabs a.active { background: #fff; color: #2563eb; border: 2px solid #cbd5e1; border-bottom: 2px solid #fff; margin-bottom: -2px; }
        .panel { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .panel h2 { margin: 0 0 4px; font-size: 16px; }
        .panel p.hint { margin: 0 0 14px; color: #64748b; font-size: 13px; }
        textarea {
            width: 100%; min-height: 130px; padding: 12px; font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 13px; border: 1px solid #cbd5e1; border-radius: 8px; resize: vertical;
        }
        .btn {
            margin-top: 12px; background: #16a34a; color: #fff; border: 0;
            padding: 11px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;
        }
        .btn:hover { background: #15803d; }
        .btn-sm { padding: 6px 13px; font-size: 13px; margin: 0; }
        .btn-red { background: #dc2626; }
        .btn-red:hover { background: #b91c1c; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: left; background: #f8fafc; padding: 10px; border-bottom: 2px solid #e2e8f0; font-size: 13px; color: #475569; }
        td { padding: 10px; border-bottom: 1px solid #f1f5f9; }
        .empty { text-align: center; color: #94a3b8; padding: 28px; }
        .pill { display: inline-block; padding: 3px 9px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .pill-ok { background: #dcfce7; color: #15803d; }
        .pill-wait { background: #fef3c7; color: #b45309; }
        .flash { background: #dcfce7; border: 1px solid #86efac; color: #15803d; padding: 11px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
        code { background: #f1f5f9; padding: 1px 5px; border-radius: 4px; font-size: 12px; }

        .roster-hidden { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
        .grid-wrap { overflow-x: auto; border: 1px solid #cbd5e1; border-radius: 8px; }
        table.grid { width: 100%; border-collapse: collapse; font-size: 13px; }
        table.grid th {
            background: #f8fafc; text-align: left; padding: 8px 10px; font-size: 12px;
            color: #475569; border-bottom: 1px solid #e2e8f0; white-space: nowrap;
        }
        table.grid td { padding: 0; border-bottom: 1px solid #f1f5f9; }
        table.grid td input {
            width: 100%; border: 0; padding: 9px 10px; font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 13px; background: transparent; outline: none; box-sizing: border-box;
        }
        table.grid td input:focus { background: #eff6ff; }
        table.grid tr:last-child td { border-bottom: 0; }
        .grid-action-col { width: 34px; }
        .row-remove {
            width: 26px; height: 26px; border: 0; background: #fee2e2; color: #b91c1c;
            border-radius: 6px; cursor: pointer; font-size: 15px; line-height: 1; margin: 0 8px;
        }
        .row-remove:hover { background: #fecaca; }
        .btn-add-row {
            margin-top: 10px; background: #e2e8f0; color: #334155; border: 0;
            padding: 8px 14px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px;
        }
        .btn-add-row:hover { background: #cbd5e1; }
    </style>
</head>
<body>
    <header>
        <h1>ReadSmart Admin</h1>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit">Sign out</button>
        </form>
    </header>

    <div class="wrap">
        @if (session('success'))
            <div class="flash">{{ session('success') }}</div>
        @endif

        <div class="tabs">
            <a href="{{ route('admin.dashboard', ['tab' => 'students']) }}" class="{{ $tab === 'students' ? 'active' : '' }}">Students ({{ $students->count() }})</a>
            <a href="{{ route('admin.dashboard', ['tab' => 'parents']) }}" class="{{ $tab === 'parents' ? 'active' : '' }}">Parents ({{ $parents->count() }})</a>
            <a href="{{ route('admin.dashboard', ['tab' => 'teachers']) }}" class="{{ $tab === 'teachers' ? 'active' : '' }}">Teachers ({{ $teachers->count() }})</a>
        </div>

        {{-- ================= STUDENTS ================= --}}
        @if ($tab === 'students')
            <div class="panel">
                <h2>Bulk create student accounts</h2>
                <p class="hint">
                    Fill in a row per student. You can also paste straight from a spreadsheet (Excel/Google Sheets) —
                    select a block of cells there, copy, click the first cell below, and paste; it'll fill across and down automatically.<br>
                    Students log in using their <strong>LRN</strong>. Passwords are generated automatically and shown once on the printout.
                </p>
                <form method="POST" action="{{ route('admin.students.bulk') }}" onsubmit="return syncRoster('student-grid', 'student-roster', 5)">
                    @csrf
                    <textarea name="roster" id="student-roster" class="roster-hidden"></textarea>
                    <div class="grid-wrap">
                        <table class="grid" id="student-grid" data-cols="5">
                            <thead>
                                <tr>
                                    <th>First name</th>
                                    <th>Last name</th>
                                    <th>LRN</th>
                                    <th>Grade level</th>
                                    <th>Section</th>
                                    <th class="grid-action-col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @for ($i = 0; $i < 3; $i++)
                                    <tr>
                                        <td><input type="text" placeholder="Maria"></td>
                                        <td><input type="text" placeholder="Santos"></td>
                                        <td><input type="text" placeholder="123456789012"></td>
                                        <td><input type="text" placeholder="Grade 5"></td>
                                        <td><input type="text" placeholder="Mabini"></td>
                                        <td><button type="button" class="row-remove" onclick="removeGridRow(this)" title="Remove row">&times;</button></td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn-add-row" onclick="addGridRow('student-grid', 5)">+ Add row</button>
                    <br>
                    <button type="submit" class="btn">Create accounts &amp; print slips</button>
                </form>
            </div>

            <div class="panel">
                <h2>Existing students</h2>
                <table>
                    <thead>
                        <tr><th>Name</th><th>LRN</th><th>Grade</th><th>Section</th><th>Parent linked</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($students as $s)
                            <tr>
                                <td>{{ $s->name ?: trim($s->first_name . ' ' . $s->last_name) }}</td>
                                <td>{{ $s->lrn }}</td>
                                <td>{{ $s->grade_level }}</td>
                                <td>{{ $s->section }}</td>
                                <td>
                                    @if ($s->parent_id)
                                        <span class="pill pill-ok">Linked</span>
                                    @else
                                        <span class="pill pill-wait">None</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">No student accounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- ================= PARENTS ================= --}}
        @if ($tab === 'parents')
            <div class="panel">
                <h2>Bulk create parent accounts</h2>
                <p class="hint">
                    Fill in a row per parent, or paste from a spreadsheet the same way as the Students tab.<br>
                    The child's LRN links this parent to an existing student, so <strong>create the student first</strong>. Parents log in using their <strong>email</strong>.
                </p>
                <form method="POST" action="{{ route('admin.parents.bulk') }}" onsubmit="return syncRoster('parent-grid', 'parent-roster', 4)">
                    @csrf
                    <textarea name="roster" id="parent-roster" class="roster-hidden"></textarea>
                    <div class="grid-wrap">
                        <table class="grid" id="parent-grid" data-cols="4">
                            <thead>
                                <tr>
                                    <th>First name</th>
                                    <th>Last name</th>
                                    <th>Email</th>
                                    <th>Child's LRN</th>
                                    <th class="grid-action-col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @for ($i = 0; $i < 3; $i++)
                                    <tr>
                                        <td><input type="text" placeholder="Ana"></td>
                                        <td><input type="text" placeholder="Santos"></td>
                                        <td><input type="text" placeholder="ana.santos@gmail.com"></td>
                                        <td><input type="text" placeholder="123456789012"></td>
                                        <td><button type="button" class="row-remove" onclick="removeGridRow(this)" title="Remove row">&times;</button></td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn-add-row" onclick="addGridRow('parent-grid', 4)">+ Add row</button>
                    <br>
                    <button type="submit" class="btn">Create accounts &amp; print slips</button>
                </form>
            </div>

            <div class="panel">
                <h2>Existing parents</h2>
                <table>
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Children</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($parents as $p)
                            <tr>
                                <td>{{ $p->name ?: trim($p->first_name . ' ' . $p->last_name) }}</td>
                                <td>{{ $p->email }}</td>
                                <td>
                                    @php $kids = $childrenByParent[$p->id] ?? collect(); @endphp
                                    @if ($kids->isEmpty())
                                        <span class="pill pill-wait">None linked</span>
                                    @else
                                        {{ $kids->pluck('name')->filter()->implode(', ') }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">No parent accounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- ================= TEACHERS ================= --}}
        @if ($tab === 'teachers')
            <div class="panel">
                <h2>Teacher accounts</h2>
                <p class="hint">
                    Teachers register themselves through the app. Approve an account here before they can sign in.
                </p>
                <table>
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Registered</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($teachers as $t)
                            <tr>
                                <td>{{ $t->name }}</td>
                                <td>{{ $t->email }}</td>
                                <td>{{ optional($t->created_at)->format('Y-m-d') }}</td>
                                <td>
                                    @if ($t->email_verified_at)
                                        <span class="pill pill-ok">Approved</span>
                                    @else
                                        <span class="pill pill-wait">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($t->email_verified_at)
                                        <form method="POST" action="{{ route('admin.teachers.revoke', $t->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-red">Revoke</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.teachers.approve', $t->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm">Approve</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">No teacher accounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <script>
        // ---- add / remove rows -------------------------------------------------
        function addGridRow(gridId, cols) {
            const tbody = document.querySelector('#' + gridId + ' tbody');
            const tr = document.createElement('tr');
            for (let i = 0; i < cols; i++) {
                const td = document.createElement('td');
                const input = document.createElement('input');
                input.type = 'text';
                td.appendChild(input);
                tr.appendChild(td);
            }
            const actionTd = document.createElement('td');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'row-remove';
            btn.title = 'Remove row';
            btn.innerHTML = '&times;';
            btn.onclick = function () { removeGridRow(btn); };
            actionTd.appendChild(btn);
            tr.appendChild(actionTd);
            tbody.appendChild(tr);
        }

        function removeGridRow(btn) {
            const tbody = btn.closest('tbody');
            if (tbody.rows.length > 1) {
                btn.closest('tr').remove();
            } else {
                // keep at least one row, just clear it
                btn.closest('tr').querySelectorAll('input').forEach(i => i.value = '');
            }
        }

        // ---- serialize grid -> hidden textarea before submit -------------------
        function syncRoster(gridId, textareaId, cols) {
            const rows = document.querySelectorAll('#' + gridId + ' tbody tr');
            const lines = [];
            rows.forEach(row => {
                const inputs = row.querySelectorAll('input');
                const values = Array.from(inputs).map(i => i.value.trim());
                if (values.some(v => v !== '')) {
                    lines.push(values.join(', '));
                }
            });
            document.getElementById(textareaId).value = lines.join('\n');
            return true; // let the form submit
        }

        // ---- spreadsheet-style paste --------------------------------------------
        // Paste a block copied from Excel/Google Sheets into any cell and it
        // fills across columns (tab-separated) and down rows (newline-separated),
        // adding grid rows automatically if the pasted block runs past the bottom.
        document.querySelectorAll('table.grid').forEach(function (grid) {
            const cols = parseInt(grid.dataset.cols, 10);
            const gridId = grid.id;

            grid.addEventListener('paste', function (e) {
                const text = (e.clipboardData || window.clipboardData).getData('text');
                if (!text || (!text.includes('\t') && !text.includes('\n'))) {
                    return; // plain single value — let the browser paste normally
                }
                e.preventDefault();

                const startInput = e.target;
                const startTd = startInput.closest('td');
                const startRow = startInput.closest('tr');
                let startColIndex = Array.from(startRow.children).indexOf(startTd);
                let startRowIndex = Array.from(grid.querySelector('tbody').children).indexOf(startRow);

                const gridRows = text.replace(/\r/g, '').split('\n').filter((_, i, arr) =>
                    !(i === arr.length - 1 && arr[i] === '') // drop trailing empty line
                );

                gridRows.forEach(function (lineText, r) {
                    const targetRowIndex = startRowIndex + r;
                    let tbody = grid.querySelector('tbody');
                    while (tbody.children.length <= targetRowIndex) {
                        addGridRow(gridId, cols);
                        tbody = grid.querySelector('tbody');
                    }
                    const targetRow = tbody.children[targetRowIndex];
                    const cells = lineText.split('\t');
                    cells.forEach(function (val, c) {
                        const targetColIndex = startColIndex + c;
                        if (targetColIndex >= cols) return; // ignore extra columns
                        const input = targetRow.children[targetColIndex].querySelector('input');
                        if (input) input.value = val.trim();
                    });
                });
            });
        });
    </script>
</body>
</html>
