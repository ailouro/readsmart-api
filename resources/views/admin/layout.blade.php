<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin Dashboard') — ReadSmart</title>
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

        /* Profile at Modal Styles */
    .profile-menu { display: flex; align-items: center; gap: 12px; position: relative; }
    .profile-icon { 
        background: #3b82f6; color: white; width: 36px; height: 36px; 
        border-radius: 50%; display: flex; justify-content: center; align-items: center; 
        font-weight: bold; cursor: pointer; user-select: none;
    }
    .dropdown {
        display: none; position: absolute; top: 100%; right: 0; margin-top: 8px;
        background: white; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        width: 160px; overflow: hidden; z-index: 50;
    }
    .dropdown.active { display: block; }
    .dropdown button {
        display: block; width: 100%; text-align: left; padding: 10px 16px; 
        background: none; border: none; font-size: 14px; cursor: pointer; color: #0f172a;
    }
    .dropdown button:hover { background: #f1f5f9; }
    
    .modal-overlay {
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); z-index: 100; justify-content: center; align-items: center;
    }
    .modal-overlay.active { display: flex; }
    .modal-content {
        background: #fff; padding: 24px; border-radius: 12px; width: 100%; max-width: 400px;
        color: #0f172a;
    }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
    .modal-header h2 { margin: 0; font-size: 18px; }
    .close-btn { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; line-height: 1; }
    .close-btn:hover { color: #0f172a; }

        /* ---------- Sidebar layout ---------- */
        .layout { display: flex; align-items: stretch; }
        .sidebar {
            width: 220px; flex-shrink: 0; background: #fff; border-right: 1px solid #e2e8f0;
            padding: 14px 12px; min-height: calc(100vh - 68px);
        }
        .side-label {
            font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
            color: #94a3b8; margin: 16px 10px 6px;
        }
        .side-label:first-child { margin-top: 4px; }
        .sidebar a {
            display: flex; justify-content: space-between; align-items: center;
            padding: 9px 12px; border-radius: 8px; color: #475569; text-decoration: none;
            font-size: 14px; font-weight: 600; margin-bottom: 2px;
        }
        .sidebar a:hover { background: #f1f5f9; }
        .sidebar a.active { background: #eff6ff; color: #2563eb; }
        .sidebar .count { font-size: 11px; background: #e2e8f0; color: #475569; border-radius: 99px; padding: 1px 8px; }
        .sidebar a.active .count { background: #dbeafe; color: #1d4ed8; }
        .main { flex: 1; min-width: 0; }
        @media (max-width: 820px) {
            .layout { flex-direction: column; }
            .sidebar { width: 100%; min-height: 0; display: flex; gap: 4px; overflow-x: auto; border-right: 0; border-bottom: 1px solid #e2e8f0; padding: 8px; }
            .side-label { display: none; }
            .sidebar a { white-space: nowrap; margin: 0; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header>
        <h1>ReadSmart Admin</h1>
        <div class="profile-menu">
            <div class="profile-icon" onclick="document.getElementById('profileDropdown').classList.toggle('active')">
                {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
            </div>
            <div id="profileDropdown" class="dropdown">
                <button onclick="openProfileModal()">Account Settings</button>
                <form method="POST" action="{{ route('admin.logout') }}" style="margin: 0;">
                    @csrf
                    <button type="submit" style="color: #dc2626;">Sign out</button>
                </form>
            </div>
        </div>
    </header>

    <!-- Ito ang Account Settings Modal -->
    <div id="profileModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Account Settings</h2>
                <button class="close-btn" onclick="closeProfileModal()">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.change-password') }}" style="display: flex; flex-direction: column; gap: 12px;">
                @csrf
                
                <label style="font-size: 14px; font-weight: 600; margin-bottom: -8px;">Admin Name</label>
                <input type="text" name="name" value="{{ Auth::user()->name ?? '' }}" required
                       style="padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                
                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 8px 0; width: 100%;">
                
                <label style="font-size: 14px; font-weight: 600; margin-bottom: -8px;">Change Password <span style="font-weight: 400; color: #64748b;">(Leave blank if no change)</span></label>
                <input type="password" name="current_password" placeholder="Current password"
                       style="padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                <input type="password" name="new_password" placeholder="New password" minlength="8"
                       style="padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                <input type="password" name="new_password_confirmation" placeholder="Confirm new password" minlength="8"
                       style="padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                
                <button type="submit" class="btn" style="margin-top: 8px;">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        function openProfileModal() {
            document.getElementById('profileDropdown').classList.remove('active');
            document.getElementById('profileModal').classList.add('active');
        }
        function closeProfileModal() {
            document.getElementById('profileModal').classList.remove('active');
        }
        
        // Magsasara ang dropdown kapag nag-click ka sa labas
        document.addEventListener('click', function(event) {
            const menu = document.querySelector('.profile-menu');
            if (!menu.contains(event.target)) {
                const dropdown = document.getElementById('profileDropdown');
                if(dropdown) dropdown.classList.remove('active');
            }
        });
    </script>

    @php
        $onClasses  = request()->routeIs('admin.classes*');
        $currentTab = request()->query('tab', 'students');
    @endphp

    <div class="layout">
        <aside class="sidebar">
            <div class="side-label">Accounts</div>
            <a href="{{ route('admin.dashboard', ['tab' => 'students']) }}" class="{{ !$onClasses && $currentTab === 'students' ? 'active' : '' }}">
                Students @isset($navCounts)<span class="count">{{ $navCounts['students'] }}</span>@endisset
            </a>
            <a href="{{ route('admin.dashboard', ['tab' => 'parents']) }}" class="{{ !$onClasses && $currentTab === 'parents' ? 'active' : '' }}">
                Parents @isset($navCounts)<span class="count">{{ $navCounts['parents'] }}</span>@endisset
            </a>
            <a href="{{ route('admin.dashboard', ['tab' => 'teachers']) }}" class="{{ !$onClasses && $currentTab === 'teachers' ? 'active' : '' }}">
                Teachers @isset($navCounts)<span class="count">{{ $navCounts['teachers'] }}</span>@endisset
            </a>

            <div class="side-label">Classes</div>
            <a href="{{ route('admin.classes.index') }}" class="{{ $onClasses ? 'active' : '' }}">Create class</a>
        </aside>

        <main class="main">
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

                @yield('content')
            </div>
        </main>
    </div>

    @stack('scripts')
</body>
</html>