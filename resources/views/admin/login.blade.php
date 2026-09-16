@php $tab = $tab ?? 'students'; @endphp

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
                    One student per line: <code>first_name, last_name, LRN, grade_level, section</code><br>
                    Example: <code>Maria, Santos, 123456789012, Grade 5, Mabini</code><br>
                    Students log in using their <strong>LRN</strong>. Passwords are generated automatically and shown once on the printout.
                </p>
                <form method="POST" action="{{ route('admin.students.bulk') }}">
                    @csrf
                    <textarea name="roster" placeholder="Maria, Santos, 123456789012, Grade 5, Mabini&#10;Jose, Cruz, 123456789013, Grade 5, Mabini"></textarea>
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
                    One parent per line: <code>first_name, last_name, email, child_LRN</code><br>
                    Example: <code>Ana, Santos, ana.santos@gmail.com, 123456789012</code><br>
                    The child's LRN links this parent to an existing student, so <strong>create the student first</strong>. Parents log in using their <strong>email</strong>.
                </p>
                <form method="POST" action="{{ route('admin.parents.bulk') }}">
                    @csrf
                    <textarea name="roster" placeholder="Ana, Santos, ana.santos@gmail.com, 123456789012"></textarea>
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
</body>
</html>