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

        .grid-wrap { overflow-x: auto; border: 1px solid #94a3b8; border-radius: 8px; max-height: 420px; overflow-y: auto; }
        table.grid { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        table.grid th {
            text-align: left; background: #dbeafe; color: #1e3a8a;
            padding: 10px 12px; font-weight: 700;
            border: 1px solid #94a3b8; white-space: nowrap;
            position: sticky; top: 0; z-index: 1;
        }
        table.grid tbody tr:nth-child(even) { background: #f8fafc; }
        table.grid tbody tr:hover { background: #eff6ff; }
        table.grid td {
            padding: 0; border: 1px solid #cbd5e1;
        }
        table.grid td.col-remove, table.grid th.col-remove { border-right: 1px solid #94a3b8; width: 40px; text-align: center; }
        table.grid input, table.grid select {
            width: 100%; border: 0; padding: 9px 12px; font-size: 13.5px;
            font-family: ui-monospace, Menlo, Consolas, monospace;
            background: transparent;
        }
        table.grid input:focus, table.grid select:focus { outline: 2px solid #2563eb; outline-offset: -2px; background: #fff; position: relative; z-index: 2; }
        .row-remove-btn {
            border: 0; background: transparent; color: #cbd5e1; cursor: pointer;
            font-size: 17px; line-height: 1; padding: 9px; width: 100%;
        }
        .row-remove-btn:hover { color: #dc2626; }
        .btn-add {
            background: #fff; color: #2563eb; border: 1px dashed #93c5fd;
            margin-right: 8px;
        }
        .btn-add:hover { background: #eff6ff; }
        .inline-form { display: flex; gap: 6px; align-items: center; margin: 0; }
        .inline-form select {
            padding: 5px 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;
        }
        .btn-teal { background: #0891b2; }
        .btn-teal:hover { background: #0e7490; }
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

@if ($errors->any())
    <div class="errors" style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 11px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 14px;">
        <strong>Please fix the following issues:</strong>
        <ul style="margin: 6px 0 0; padding-left: 20px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
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
                    Fill in each row, or copy a block of cells from Excel/Google Sheets and paste directly into the grid —
                    it will fill across columns and add rows automatically.<br>
                    Students log in using their <strong>LRN</strong>. Passwords are generated automatically and shown once on the printout.
                </p>
                <form method="POST" action="{{ route('admin.students.bulk') }}">
                    @csrf
                    <div class="grid-wrap">
                        <table class="grid" id="studentGrid" data-columns="first_name,last_name,lrn,grade_level,section,teacher_id">
                            <thead>
                                <tr>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>LRN</th>
                                    <th>Grade Level</th>
                                    <th>Section</th>
                                    <th>Assign to Teacher</th>
                                    <th class="col-remove"></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-add" onclick="addGridRow('studentGrid')">+ Add row</button>
                    <button type="submit" class="btn">Create accounts &amp; print slips</button>
                </form>
            </div>

            <div class="panel">
                <h2>Existing students</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th><th>LRN</th><th>Grade</th><th>Section</th><th>Parent linked</th>
                            <th>Teacher</th><th>Enrollment</th><th>Reset password</th>
                        </tr>
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
                                <td>
                                    <form class="inline-form" method="POST" action="{{ route('admin.students.reassign-teacher', $s->id) }}">
                                        @csrf
                                        <select name="teacher_id">
                                            <option value="">— Unassigned —</option>
                                            @foreach ($approvedTeachers as $t)
                                                <option value="{{ $t->id }}" {{ (string) $s->teacher_id === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm">Save</button>
                                    </form>
                                </td>
                                <td>
                                    @if ($s->enrollment_status === 'enrolled')
                                        <span class="pill pill-ok">Enrolled</span>
                                    @elseif ($s->enrollment_status === 'pending')
                                        <span class="pill pill-wait">Pending teacher</span>
                                    @else
                                        <span class="pill pill-wait">Unassigned</span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.users.reset-password', $s->id) }}"
                                          onsubmit="return confirm('Reset password for {{ $s->name }}? The old password will stop working immediately.');" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-teal">Reset password</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="empty">No student accounts yet.</td></tr>
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
                    Fill in each row, or paste a block copied from a spreadsheet.<br>
                    <strong>Child's LRN</strong> links this parent to an existing student — create the student first.
                    Parents log in using their <strong>email</strong>.
                </p>
                <form method="POST" action="{{ route('admin.parents.bulk') }}">
                    @csrf
                    <div class="grid-wrap">
                        <table class="grid" id="parentGrid" data-columns="first_name,last_name,email,child_lrn">
                            <thead>
                                <tr>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Email</th>
                                    <th>Child's LRN</th>
                                    <th class="col-remove"></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-add" onclick="addGridRow('parentGrid')">+ Add row</button>
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
        // List of approved teachers, injected from the server, used to
        // populate the "Assign to Teacher" dropdown in the bulk-create
        // grid. Kept as plain data (id + name) — no PHP round-trip needed
        // per row.
        const APPROVED_TEACHERS = @json($approvedTeachers->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values());

        // ---------------------------------------------------------------
        // Editable spreadsheet-style grid for bulk student/parent create.
        // Each <table class="grid"> declares its field order via
        // data-columns="a,b,c" on the <table> itself. Inputs are named
        // rows[i][field] so Laravel receives a structured array — no more
        // comma-counting, so a stray comma in a name can't silently drop
        // a whole row.
        //
        // The "teacher_id" column is special-cased to render a <select>
        // of approved teachers instead of a free-text <input> — everything
        // else (reindexing, row add/remove) treats it the same way via the
        // generic [data-col="..."] attribute selector.
        // ---------------------------------------------------------------

        function gridColumns(table) {
            return table.dataset.columns.split(',');
        }

        function buildTeacherSelect(col, value) {
            const select = document.createElement('select');
            select.dataset.col = col;

            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = '— Unassigned —';
            select.appendChild(blank);

            APPROVED_TEACHERS.forEach((t) => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.name;
                select.appendChild(opt);
            });

            if (value) select.value = String(value);
            return select;
        }

        function buildRow(table, values) {
            const columns = gridColumns(table);
            const tr = document.createElement('tr');

            columns.forEach((col) => {
                const td = document.createElement('td');
                let field;

                if (col === 'teacher_id') {
                    field = buildTeacherSelect(col, values && values[col]);
                } else {
                    field = document.createElement('input');
                    field.type = 'text';
                    field.dataset.col = col;
                    field.value = values && values[col] ? values[col] : '';
                    field.addEventListener('paste', (e) => handleGridPaste(e, table, tr, field));
                }

                td.appendChild(field);
                tr.appendChild(td);
            });

            const removeTd = document.createElement('td');
            removeTd.className = 'col-remove';
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'row-remove-btn';
            removeBtn.innerHTML = '&times;';
            removeBtn.title = 'Remove row';
            removeBtn.addEventListener('click', () => {
                const tbody = table.querySelector('tbody');
                if (tbody.rows.length > 1) {
                    tr.remove();
                } else {
                    // keep at least one row, just clear it
                    columns.forEach((col) => {
                        const field = tr.querySelector(`[data-col="${col}"]`);
                        if (field) field.value = '';
                    });
                }
                reindexGrid(table);
            });
            removeTd.appendChild(removeBtn);
            tr.appendChild(removeTd);

            return tr;
        }

        function reindexGrid(table) {
            const columns = gridColumns(table);
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach((tr, i) => {
                columns.forEach((col) => {
                    const field = tr.querySelector(`[data-col="${col}"]`);
                    if (field) field.name = `rows[${i}][${col}]`;
                });
            });
        }

        function addGridRow(tableId, values) {
            const table = document.getElementById(tableId);
            const tbody = table.querySelector('tbody');
            tbody.appendChild(buildRow(table, values));
            reindexGrid(table);
            return table.querySelectorAll('tbody tr').length - 1; // new row index
        }

        function initGrid(tableId, initialRows) {
            const table = document.getElementById(tableId);
            for (let i = 0; i < initialRows; i++) {
                addGridRow(tableId);
            }
        }

        /**
         * Handles pasting a block of cells copied from Excel/Google Sheets
         * (tab-separated columns, newline-separated rows) starting at the
         * cell that was focused when the paste happened. Adds rows to the
         * grid automatically if the pasted block runs past the last row.
         * A normal single-value paste (no tabs/newlines) is left alone so
         * default browser paste behavior still works for one cell.
         */
        function handleGridPaste(e, table, startTr, startInput) {
            const text = (e.clipboardData || window.clipboardData).getData('text');
            if (!text || (!text.includes('\t') && !text.includes('\n'))) {
                return; // single value — let the browser handle it normally
            }
            e.preventDefault();

            const columns = gridColumns(table);
            const startColIndex = columns.indexOf(startInput.dataset.col);
            const tbody = table.querySelector('tbody');
            const startRowIndex = Array.from(tbody.rows).indexOf(startTr);

            const lines = text.replace(/\r/g, '').split('\n').filter((l, idx, arr) =>
                !(idx === arr.length - 1 && l === '') // drop trailing empty line from copy
            );

            lines.forEach((line, li) => {
                const cells = line.split('\t');
                const targetRowIndex = startRowIndex + li;

                while (tbody.rows.length <= targetRowIndex) {
                    tbody.appendChild(buildRow(table));
                }
                reindexGrid(table);

                const targetTr = tbody.rows[targetRowIndex];
                cells.forEach((val, ci) => {
                    const targetColIndex = startColIndex + ci;
                    if (targetColIndex >= columns.length) return; // ignore extra pasted columns
                    const input = targetTr.querySelector(`input[data-col="${columns[targetColIndex]}"]`);
                    if (input) input.value = val.trim();
                });
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('studentGrid')) initGrid('studentGrid', 5);
            if (document.getElementById('parentGrid')) initGrid('parentGrid', 5);
        });
    </script>
</body>
</html>