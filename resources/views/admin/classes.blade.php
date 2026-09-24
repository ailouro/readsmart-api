<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

@extends('admin.layout')

@section('title', 'Classes')

@push('styles')
<style>
    /* ---------- Create class ---------- */
    .create-class { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; }
    .create-class label { display: flex; flex-direction: column; gap: 5px; font-size: 13px; font-weight: 600; color: #475569; min-width: 180px; flex: 1; }
    .create-class select, .list-toolbar select {
        padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; background: #fff; color: #0f172a;
    }
    .create-class .btn { margin: 0; }
    .field-hint { font-size: 12px; color: #b45309; margin-top: 2px; }

    /* ---------- Grade boards (Tab1 / Tab2 in the mockup) ---------- */
    .board-tabs { display: flex; gap: 8px; margin-bottom: 14px; }
    .board-tab {
        border: 2px solid #0f172a; background: #fff; color: #0f172a; padding: 8px 22px;
        border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer;
    }
    .board-tab.active { background: #0f172a; color: #fff; }
    .board { border: 4px solid #0f172a; background: #d8d5cd; }
    .board-title { text-align: center; font-weight: 800; font-size: 20px; padding: 12px; border-bottom: 4px solid #0f172a; }
    .board-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .board-col { padding: 16px 18px 20px; border-right: 1px solid #8f8b82; min-height: 260px; }
    .board-col:last-child { border-right: 0; }
    .col-head { text-align: center; font-weight: 800; font-size: 15px; }
    .col-sub { text-align: center; font-size: 12px; color: #475569; margin: 2px 0 14px; }
    .col-empty { text-align: center; color: #64748b; font-size: 13px; margin-top: 26px; }
    .stu-list { list-style: none; margin: 0; padding: 0; }
    .stu-list li {
        display: flex; align-items: center; justify-content: center; gap: 6px;
        padding: 3px 0; font-size: 14px;
    }
    .stu-list li.muted { color: #64748b; font-size: 13px; }
    .stu-list form { margin: 0; }
    .stu-remove { border: 0; background: transparent; color: #94a3b8; cursor: pointer; font-size: 16px; line-height: 1; padding: 0 4px; }
    .stu-remove:hover { color: #dc2626; }

    /* ---------- Student list ---------- */
    .list-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 14px; }
    .list-toolbar .spacer { flex: 1; }
    .list-toolbar .btn { margin: 0; }
    .sel-count { font-size: 13px; color: #64748b; }
    th.col-check, td.col-check { width: 60px; text-align: center; }
    td.col-check input, th.col-check input { width: 18px; height: 18px; cursor: pointer; }
    tr.dim td { opacity: .45; }
    td.muted { color: #94a3b8; }
</style>
@endpush

@section('content')

    {{-- ================= CREATE CLASS ================= --}}
    <div class="panel">
        <h2>Create class</h2>
        <p class="hint">
            Choose the grade level and section, then the teacher who will handle it.
            Only approved teachers you already assigned to that grade are listed.
        </p>

        <form method="POST" action="{{ route('admin.classes.store') }}" class="create-class">
            @csrf
            <label>
                Grade level
                <select name="grade_level" id="ccGrade" required>
                    <option value="" disabled {{ old('grade_level') ? '' : 'selected' }}>Select grade…</option>
                    @foreach ($grades as $g)
                        <option value="{{ $g }}" {{ old('grade_level') === $g ? 'selected' : '' }}>{{ $g }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Section
                <select name="section" required>
                    <option value="" disabled {{ old('section') ? '' : 'selected' }}>Select section…</option>
                    @foreach ($sectionOptions as $sec)
                        <option value="{{ $sec }}" {{ old('section') === $sec ? 'selected' : '' }}>{{ $sec }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Teacher
                <select name="teacher_id" id="ccTeacher" required></select>
                <span class="field-hint" id="ccTeacherHint" style="display:none;"></span>
            </label>

            <button type="submit" class="btn">Create class</button>
        </form>
    </div>

    {{-- ================= CLASS BOARDS (one tab per grade) ================= --}}
    <div class="panel">
        <div class="board-tabs">
            @foreach ($grades as $grade)
                <button type="button" class="board-tab {{ $loop->first ? 'active' : '' }}" data-board="board-{{ $loop->index }}">{{ $grade }}</button>
            @endforeach
        </div>

        @foreach ($grades as $grade)
            <div class="board" id="board-{{ $loop->index }}" style="{{ $loop->first ? '' : 'display:none;' }}">
                <div class="board-title">{{ $grade }}</div>
                <div class="board-cols">
                    @foreach ($board[$grade] ?? [] as $section => $class)
                        <div class="board-col">
                            <div class="col-head">{{ $section }}</div>

                            @if ($class)
                                <div class="col-sub">
                                    Teacher: {{ $teacherNames[$class->teacher_id] ?? '—' }}
                                    &middot; {{ $class->students->count() }} {{ $class->students->count() === 1 ? 'student' : 'students' }}
                                </div>
                                <ul class="stu-list">
                                    @forelse ($class->students->sortBy('last_name') as $st)
                                        <li>
                                            <span>{{ $st->name ?: trim($st->first_name . ' ' . $st->last_name) }}</span>
                                            <form method="POST" action="{{ route('admin.classes.remove-student', [$class->id, $st->id]) }}"
                                                  onsubmit="return confirm('Remove {{ addslashes($st->name) }} from {{ $grade }} — {{ $section }}?');">
                                                @csrf
                                                <button type="submit" class="stu-remove" title="Remove from this section">&times;</button>
                                            </form>
                                        </li>
                                    @empty
                                        <li class="muted">No students yet</li>
                                    @endforelse
                                </ul>
                            @else
                                <div class="col-empty">No class for this section yet.<br>Create one above.</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- ================= LIST OF STUDENT ACCOUNTS ================= --}}
    <form method="POST" action="{{ route('admin.classes.assign') }}" id="assignForm">
        @csrf
        <div class="panel">
            <h2>List of user accounts (student)</h2>
            <p class="hint">
                Tick the students, choose the section on the right, then press Add.
                A student can only join a section of their own grade level; adding them to another section of the same grade moves them.
            </p>

            <div class="list-toolbar">
                <label style="font-size:13px; font-weight:600; color:#475569;">
                    Show
                    <select id="gradeFilter">
                        <option value="">All grades</option>
                        @foreach ($grades as $grade)
                            <option value="{{ preg_replace('/\D+/', '', $grade) }}">{{ $grade }}</option>
                        @endforeach
                    </select>
                </label>

                <span class="spacer"></span>
                <span class="sel-count" id="selCount">0 selected</span>

                <select name="class_id" id="targetClass" required>
                    @if ($classOptions->isEmpty())
                        <option value="" disabled selected>Create a class first</option>
                    @else
                        <option value="" disabled selected>Add selected students to…</option>
                        @foreach ($classOptions as $opt)
                            <option value="{{ $opt['id'] }}" data-grade="{{ $opt['gnum'] }}">Add in {{ $opt['label'] }}</option>
                        @endforeach
                    @endif
                </select>
                <button type="submit" class="btn btn-sm" {{ $classOptions->isEmpty() ? 'disabled' : '' }}>Add selected</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>LRN</th>
                        <th>Grade level</th>
                        <th>Parent</th>
                        <th>GWA</th>
                        <th>Class</th>
                        <th class="col-check"><input type="checkbox" id="checkAll" title="Select everyone shown"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $s)
                        @php
                            $gnum = preg_match('/\d+/', (string) $s->grade_level, $m) ? (int) $m[0] : '';
                            $gwa  = $s->getAttributes()['gwa'] ?? null;
                        @endphp
                        <tr data-grade="{{ $gnum }}">
                            <td>{{ $s->name ?: trim($s->first_name . ' ' . $s->last_name) }}</td>
                            <td>{{ $s->lrn }}</td>
                            <td>{{ $s->grade_level }}</td>
                            <td class="{{ $s->parent_id ? '' : 'muted' }}">{{ $s->parent_id ? ($parentNames[$s->parent_id] ?? '—') : 'None' }}</td>
                            <td class="{{ $gwa === null || $gwa === '' ? 'muted' : '' }}">{{ $gwa === null || $gwa === '' ? '—' : $gwa }}</td>
                            <td>
                                @if (!empty($classLabels[$s->id]))
                                    <span class="pill pill-ok">{{ $classLabels[$s->id] }}</span>
                                @else
                                    <span class="pill pill-wait">Not in a class</span>
                                @endif
                            </td>
                            <td class="col-check">
                                <input type="checkbox" name="student_ids[]" value="{{ $s->id }}" class="stuCheck" data-grade="{{ $gnum }}">
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">No student accounts yet. Create them from the Students page first.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    // ---------------------------------------------------------------
    // Create class: the Teacher dropdown only lists approved teachers
    // whose assigned grade matches the grade picked above.
    // ---------------------------------------------------------------
    const TEACHERS_BY_GRADE = @json($teachersByGrade);
    const OLD_TEACHER = @json(old('teacher_id'));

    const ccGrade   = document.getElementById('ccGrade');
    const ccTeacher = document.getElementById('ccTeacher');
    const ccHint    = document.getElementById('ccTeacherHint');

    function fillTeachers() {
        const grade = ccGrade.value;
        const list  = (grade && TEACHERS_BY_GRADE[grade]) ? TEACHERS_BY_GRADE[grade] : [];

        ccTeacher.innerHTML = '';
        ccHint.style.display = 'none';

        const first = document.createElement('option');
        first.value = '';
        first.disabled = true;
        first.selected = true;
        first.textContent = !grade ? 'Pick a grade first…' : (list.length ? 'Select teacher…' : 'No teacher for ' + grade);
        ccTeacher.appendChild(first);

        list.forEach((t) => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = t.name;
            if (OLD_TEACHER && String(OLD_TEACHER) === String(t.id)) opt.selected = true;
            ccTeacher.appendChild(opt);
        });

        if (grade && !list.length) {
            ccHint.textContent = 'No approved teacher is assigned to ' + grade + ' yet — set one on the Teachers page.';
            ccHint.style.display = 'block';
        }
    }
    ccGrade.addEventListener('change', fillTeachers);
    fillTeachers();

    // ---------------------------------------------------------------
    // Grade tabs (Grade 5 / Grade 6)
    // ---------------------------------------------------------------
    document.querySelectorAll('.board-tab').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.board-tab').forEach((b) => b.classList.toggle('active', b === btn));
            document.querySelectorAll('.board').forEach((b) => {
                b.style.display = (b.id === btn.dataset.board) ? '' : 'none';
            });
        });
    });

    // ---------------------------------------------------------------
    // Student list: grade filter, "add in ..." target, tick boxes.
    // Once a section is chosen, students of other grades are greyed
    // out and un-tickable (the server double-checks this too).
    // ---------------------------------------------------------------
    const targetClass = document.getElementById('targetClass');
    const gradeFilter = document.getElementById('gradeFilter');
    const checkAll    = document.getElementById('checkAll');
    const selCount    = document.getElementById('selCount');
    const checks      = Array.from(document.querySelectorAll('.stuCheck'));

    function targetGrade() {
        const opt = targetClass.selectedOptions[0];
        return opt && opt.dataset.grade ? opt.dataset.grade : '';
    }

    function rowVisible(cb) {
        const row = cb.closest('tr');
        return row.style.display !== 'none';
    }

    function refreshList() {
        const tGrade = targetGrade();
        const filter = gradeFilter.value;

        checks.forEach((cb) => {
            const row = cb.closest('tr');
            row.style.display = (!filter || row.dataset.grade === filter) ? '' : 'none';

            const eligible = !tGrade || cb.dataset.grade === tGrade;
            cb.disabled = !eligible;
            if (!eligible) cb.checked = false;
            row.classList.toggle('dim', !eligible);
        });

        const usable = checks.filter((cb) => !cb.disabled && rowVisible(cb));
        checkAll.checked = usable.length > 0 && usable.every((cb) => cb.checked);
        selCount.textContent = checks.filter((cb) => cb.checked).length + ' selected';
    }

    checkAll.addEventListener('change', () => {
        checks.forEach((cb) => {
            if (!cb.disabled && rowVisible(cb)) cb.checked = checkAll.checked;
        });
        refreshList();
    });
    checks.forEach((cb) => cb.addEventListener('change', refreshList));
    targetClass.addEventListener('change', refreshList);
    gradeFilter.addEventListener('change', refreshList);
    refreshList();
</script>
@endpush
</html>