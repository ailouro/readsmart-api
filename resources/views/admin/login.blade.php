<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login — ReadSmart</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, sans-serif;
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh; margin: 0; padding: 16px;
            /* Solid color first as a fallback in case the image fails to load. */
            background-color: #f1f5f9;
            background-image: url('{{ asset('public/images/admin-bg.png') }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        @media (max-width: 820px) {
            body { background-attachment: scroll; }
        }
        .login-box {
            /* Slightly see-through so the school seal still shows around the card,
               but opaque enough that the form stays easy to read. */
            background: rgba(255, 255, 255, 0.94);
            padding: 30px; border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            width: 100%; max-width: 400px;
        }
        .login-box h2 { margin-top: 0; }
        .input-group { margin-bottom: 15px; }
        .input-group input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 4px; }
        button { width: 100%; padding: 10px; background: #2563eb; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .error { color: #dc2626; font-size: 14px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>ReadSmart Admin</h2>
        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('admin.login.submit') }}">
            @csrf
            <div class="input-group">
                <input type="email" name="email" placeholder="Admin Email" required value="{{ old('email') }}">
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit">Log In</button>
        </form>
    </div>
</body>
</html>