@extends('admin.layout')

@section('title', 'Admin Dashboard')

@section('content')
        {{-- ================= STUDENTS ================= --}}
        @if ($tab === 'students')
            <div class="panel">
                <h2>Bulk create student accounts</h2>
                <p class="hint">
                    Fill in each row, or copy a block of cells from Excel/Google Sheets and paste directly into the grid —
                    it will fill across columns and add rows automatically.<br>
                    Students log in using their <strong>LRN</strong>. Passwords are generated automatically and shown once on the printout.<br>
                    To put students in a section, use <a href="{{ route('admin.classes') }}">Classes</a> in the sidebar.
                </p>
                <form method="POST" action="{{ route('admin.students.bulk') }}">
                    @csrf
                    <div class="grid-wrap">
                        <table class="grid" id="studentGrid" data-columns="first_name,last_name,lrn,grade_level,section">
                            <thead>
                                <tr>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>LRN</th>
                                    <th>Grade Level</th>
                                    <th>Section</th>
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
                <p class="hint">
                    Need login slips for students who already have an account (e.g. to give to their teacher)?
                    Tick them and press <strong>Reset &amp; print slips</strong>. Each ticked student gets a
                    <strong>new password</strong> and a printable slip — their old password stops working immediately.
                </p>

                {{-- Empty form; the checkboxes and the button below belong to it via form="resetPrintForm"
                     (a real <form> can't wrap the table because each row has its own reset form). --}}
                <form id="resetPrintForm" method="POST" action="{{ route('admin.students.reset-print') }}"
                      onsubmit="return confirm('Give the ticked students a NEW password and print their slips? Their old passwords will stop working immediately.');">@csrf</form>
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                    <button type="submit" form="resetPrintForm" class="btn btn-sm btn-teal">Reset &amp; print slips</button>
                    <span id="reprintCount" style="font-size:13px; color:#64748b;">0 selected</span>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="checkAllStudents" title="Select all"></th>
                            <th>Name</th><th>LRN</th><th>Grade</th><th>Section</th><th>Parent linked</th>
                            <th>Class</th><th>Reset password</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($students as $s)
                            <tr>
                                <td>
                                    <input type="checkbox" class="student-check" name="student_ids[]" value="{{ $s->id }}" form="resetPrintForm">
                                </td>
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
                                    @if (!empty($classLabels[$s->id]))
                                        <span class="pill pill-ok">{{ $classLabels[$s->id] }}</span>
                                    @else
                                        <span class="pill pill-wait">Not in a class</span>
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
                        <tr><th>Name</th><th>Email</th><th>Children</th><th>Reset password</th></tr>
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
                                <td>
                                    <form method="POST" action="{{ route('admin.users.reset-password', $p->id) }}"
                                          onsubmit="return confirm('Reset password for {{ $p->name }}? The old password will stop working immediately.');" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-teal">Reset password</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">No parent accounts yet.</td></tr>
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
                    Teachers register themselves through the app. Choose the grade level a teacher will handle,
                    then approve the account so they can sign in. You can change the grade level of an approved teacher later with "Save grade".
                </p>
                <table>
                    <thead>
                        <tr><th>Name</th><th>Email</th><th>Grade level</th><th>Registered</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($teachers as $t)
                            <tr>
                                <td>{{ $t->name }}</td>
                                <td>{{ $t->email }}</td>
                                <td>
                                    <select name="grade_level" form="approve-teacher-{{ $t->id }}" required
                                            style="padding: 5px 8px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                                        <option value="" disabled {{ $t->grade_level ? '' : 'selected' }}>Select grade…</option>
                                        <option value="Grade 5" {{ $t->grade_level === 'Grade 5' ? 'selected' : '' }}>Grade 5</option>
                                        <option value="Grade 6" {{ $t->grade_level === 'Grade 6' ? 'selected' : '' }}>Grade 6</option>
                                    </select>
                                </td>
                                <td>{{ optional($t->created_at)->format('Y-m-d') }}</td>
                                <td>
                                    @if ($t->email_verified_at)
                                        <span class="pill pill-ok">Approved</span>
                                    @else
                                        <span class="pill pill-wait">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- The grade-level <select> in the previous column belongs to this form (via its form="" attribute). --}}
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <form id="approve-teacher-{{ $t->id }}" method="POST" action="{{ route('admin.teachers.approve', $t->id) }}" style="margin: 0;">
                                            @csrf
                                            @if ($t->email_verified_at)
                                                <button type="submit" class="btn btn-sm btn-teal">Save grade</button>
                                            @else
                                                <button type="submit" class="btn btn-sm">Approve</button>
                                            @endif
                                        </form>
                                        @if ($t->email_verified_at)
                                            <form method="POST" action="{{ route('admin.teachers.revoke', $t->id) }}" style="margin: 0;">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-red">Revoke</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">No teacher accounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
@endsection

@push('scripts')
    <script>
        // ---------------------------------------------------------------
        // Editable spreadsheet-style grid for bulk student/parent create.
        // Each <table class="grid"> declares its field order via
        // data-columns="a,b,c" on the <table> itself. Inputs are named
        // rows[i][field] so Laravel receives a structured array — no more
        // comma-counting, so a stray comma in a name can't silently drop
        // a whole row.
        //
        // Columns listed in GRID_SELECTS below are drawn as dropdowns
        // instead of text boxes. A column with "dependsOn" gets its options
        // from another column in the same row (Section depends on Grade).
        // ---------------------------------------------------------------

        const GRID_SELECTS = {
            studentGrid: {
                grade_level: {
                    placeholder: 'Select grade',
                    options: ['GRADE 5', 'GRADE 6'],
                },
                section: {
                    placeholder: 'Select section',
                    emptyPlaceholder: 'Pick grade first',
                    dependsOn: 'grade_level',
                    // Edit these lists if a grade gets different sections.
                    options: {
                        'GRADE 5': ['MAGSAYSAY', 'AQUINO'],
                        'GRADE 6': ['SATURN', 'MARS'],
                    },
                },
            },
        };

        function gridColumns(table) {
            return table.dataset.columns.split(',');
        }

        function selectConfig(table, col) {
            return (GRID_SELECTS[table.id] || {})[col] || null;
        }

        function cellEl(tr, col) {
            return tr.querySelector(`[data-col="${col}"]`);
        }

        function optionsFor(table, tr, col) {
            const cfg = selectConfig(table, col);
            if (!cfg) return [];
            if (cfg.dependsOn) {
                const parent = cellEl(tr, cfg.dependsOn);
                return (parent && cfg.options[parent.value]) || [];
            }
            return cfg.options;
        }

        function fillSelect(select, list, cfg, current) {
            select.innerHTML = '';
            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = list.length ? cfg.placeholder : (cfg.emptyPlaceholder || cfg.placeholder);
            select.appendChild(blank);
            list.forEach((item) => {
                const opt = document.createElement('option');
                opt.value = item;
                opt.textContent = item;
                select.appendChild(opt);
            });
            select.value = list.includes(current) ? current : '';
        }

        // Rebuild every dropdown in this row that depends on another column.
        function refreshDependents(table, tr) {
            gridColumns(table).forEach((col) => {
                const cfg = selectConfig(table, col);
                if (!cfg || !cfg.dependsOn) return;
                const select = cellEl(tr, col);
                if (select) fillSelect(select, optionsFor(table, tr, col), cfg, select.value);
            });
        }

        // Match typed/pasted text ("grade 6", "Grade6", "6", "saturn") to a dropdown option.
        function matchOption(select, val) {
            const v = String(val || '').trim().toUpperCase();
            if (!v) return '';
            const opts = Array.from(select.options).map((o) => o.value).filter(Boolean);
            const squash = (t) => t.replace(/\s+/g, '');
            return opts.find((o) => o === v)
                || opts.find((o) => squash(o) === squash(v))
                || (/^\d+$/.test(v) ? opts.find((o) => o.endsWith(' ' + v)) : '')
                || '';
        }

        function setCellValue(table, tr, col, val) {
            const el = cellEl(tr, col);
            if (!el) return;
            if (el.tagName === 'SELECT') {
                el.value = matchOption(el, val);
                refreshDependents(table, tr);
            } else {
                el.value = val;
            }
        }

        function buildRow(table, values) {
            const columns = gridColumns(table);
            const tr = document.createElement('tr');

            columns.forEach((col) => {
                const td = document.createElement('td');
                const cfg = selectConfig(table, col);
                let field;

                if (cfg) {
                    field = document.createElement('select');
                    field.dataset.col = col;
                    fillSelect(field, cfg.dependsOn ? [] : cfg.options, cfg, '');
                    if (!cfg.dependsOn) {
                        // changing this dropdown changes what its dependents offer
                        field.addEventListener('change', () => refreshDependents(table, tr));
                    }
                } else {
                    field = document.createElement('input');
                    field.type = 'text';
                    field.dataset.col = col;
                    field.value = values && values[col] ? values[col] : '';
                }
                field.addEventListener('paste', (e) => handleGridPaste(e, table, tr, field));

                td.appendChild(field);
                tr.appendChild(td);
            });

            // Apply starting values to dropdowns (parents first, since columns are in order)
            if (values) {
                columns.forEach((col) => {
                    if (selectConfig(table, col) && values[col]) setCellValue(table, tr, col, values[col]);
                });
            }

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
                    columns.forEach((col) => setCellValue(table, tr, col, ''));
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
                    const field = cellEl(tr, col);
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
            table.addEventListener('keydown', (e) => handleGridKeydown(e, table));
        }

        /**
         * Keyboard navigation, like a spreadsheet.
         *  - Up / Down: move to the cell above / below.
         *  - Left / Right: move to the previous / next cell. In a text box this
         *    only jumps when the cursor is already at the start / end of the
         *    text (or all the text is selected), so you can still edit inside a cell.
         *  - On a dropdown all four arrows move between cells. To change its
         *    value: type the first letter or number (5, 6, S, M), or press
         *    Space / Alt+Down to open the list.
         */
        function handleGridKeydown(e, table) {
            const el = e.target;
            if (!el.matches('input[data-col], select[data-col]')) return;
            if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;

            const isSelect = el.tagName === 'SELECT';

            // Quick pick on dropdowns: 5 / 6 for grade, S / M for section
            if (isSelect && e.key.length === 1 && /[A-Za-z0-9]/.test(e.key)) {
                const k = e.key.toUpperCase();
                const opt = Array.from(el.options).find((o) =>
                    o.value && (o.value.startsWith(k) || (/\d/.test(k) && o.value.endsWith(k))));
                if (opt) {
                    e.preventDefault();
                    el.value = opt.value;
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                }
                return;
            }

            const columns = gridColumns(table);
            const rows = Array.from(table.querySelector('tbody').rows);
            const tr = el.closest('tr');
            let r = rows.indexOf(tr);
            let c = columns.indexOf(el.dataset.col);

            const allSelected = !isSelect && el.selectionStart === 0 && el.selectionEnd === el.value.length;
            const atStart = !isSelect && el.selectionStart === 0 && el.selectionEnd === 0;
            const atEnd = !isSelect && el.selectionStart === el.value.length && el.selectionEnd === el.value.length;

            switch (e.key) {
                case 'ArrowUp':    r--; break;
                case 'ArrowDown':  r++; break;
                case 'ArrowLeft':
                    if (!isSelect && !atStart && !allSelected) return;
                    c--; break;
                case 'ArrowRight':
                    if (!isSelect && !atEnd && !allSelected) return;
                    c++; break;
                default: return;
            }

            e.preventDefault(); // also stops a dropdown from changing value
            if (r < 0 || r >= rows.length || c < 0 || c >= columns.length) return;

            const target = cellEl(rows[r], columns[c]);
            if (!target) return;
            target.focus();
            if (target.tagName === 'INPUT') target.select();
        }

        /**
         * Handles pasting a block of cells copied from Excel/Google Sheets
         * (tab-separated columns, newline-separated rows) starting at the
         * cell that was focused when the paste happened. Adds rows to the
         * grid automatically if the pasted block runs past the last row.
         * A normal single-value paste (no tabs/newlines) is left alone so
         * default browser paste behavior still works for one cell.
         * Pasted text for dropdown columns is matched to an option
         * (e.g. "Grade 6" -> GRADE 6); unrecognised text leaves it blank.
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
                    setCellValue(table, targetTr, columns[targetColIndex], val.trim());
                });
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (document.getElementById('studentGrid')) initGrid('studentGrid', 5);
            if (document.getElementById('parentGrid')) initGrid('parentGrid', 5);

            // "Reset & print slips": select-all + selected counter
            const checkAll = document.getElementById('checkAllStudents');
            if (checkAll) {
                const boxes = () => Array.from(document.querySelectorAll('.student-check'));
                const counter = document.getElementById('reprintCount');
                const refresh = () => {
                    const all = boxes();
                    const n = all.filter((b) => b.checked).length;
                    counter.textContent = n + ' selected';
                    checkAll.checked = all.length > 0 && n === all.length;
                    checkAll.indeterminate = n > 0 && n < all.length;
                };
                checkAll.addEventListener('change', () => {
                    boxes().forEach((b) => { b.checked = checkAll.checked; });
                    refresh();
                });
                boxes().forEach((b) => b.addEventListener('change', refresh));
                refresh();
            }
        });
    </script>
@endpush