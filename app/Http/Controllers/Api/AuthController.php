<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        if ($request->has('login') && !$request->has('email')) {
            $request->merge(['email' => $request->input('login')]);
        }

        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6'
        ]);

        $role = strtolower($request->input('role', 'student'));

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => $role
        ]);

        // 📧 TRIGGER EMAIL VERIFICATION KUNG TEACHER O PARENT LANG
        if (in_array($role, ['teacher', 'parent'])) {
            event(new Registered($user));
        }

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully!',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->input('login'))
            ->orWhere('lrn', $request->input('login'))
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials or account does not exist.'], 401);
        }

        // 🚫 HARANGIN KUNG HINDI PA VERIFIED ANG TEACHER/PARENT
        if (in_array($user->role, ['teacher', 'parent'])) {
            if (!$user->hasVerifiedEmail()) {
                return response()->json([
                    'message' => 'Please verify your email address first. Check your inbox or spam folder.',
                    'needs_verification' => true,
                    'email' => $user->email
                ], 403);
            }
        }

        $token = Auth::login($user);

        return response()->json([
            'success' => true,
            'message' => 'Login successful!',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'teacher'
            ]
        ], 200);
    }

    // 🔄 API PARA MAG-RESEND NG EMAIL
    public function resendVerification(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email is already verified.'], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link resent! Please check your email.']);
    }

    // ✅ HTML SUCCESS PAGE KAPAG CLINICK ANG LINK SA GMAIL
    public function verifyEmail(Request $request, $id, $hash) {
        $user = User::find($id);
        
        if (!$user) {
            return response('<h1>User not found.</h1>', 404)->header('Content-Type', 'text/html');
        }

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response('<h1>Invalid or expired verification link.</h1>', 400)->header('Content-Type', 'text/html');
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response('
            <div style="text-align:center; padding: 50px; font-family: Arial;">
                <h1 style="color: green;">Email Verified Successfully! ✅</h1>
                <p>You can now close this browser and log in to your ReadSmart app.</p>
            </div>
        ', 200)->header('Content-Type', 'text/html');
    }

    // ============================================================================
    // 📩 STUDENT ACCOUNT REQUEST (VIA APP)
    // ============================================================================
    public function requestStudentAccount(Request $request)
    {
        $request->validate([
            'student_name' => 'required|string',
            'lrn' => 'required|string|unique:users,lrn|unique:student_requests,lrn',
            'grade_level' => 'required|string',
            'section' => 'required|string',
            'parent_email' => 'required|email',
        ], [
            'lrn.unique' => 'This LRN is already registered or currently pending approval.'
        ]);

        // 1. I-check kung nag-register na yung magulang gamit ang email
        $parent = \App\Models\User::where('email', $request->parent_email)
                                  ->where('role', 'parent')
                                  ->first();

        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent email not found! Please ask your parent to register an account first before requesting a student account.'
            ], 404);
        }

        // 2. I-save ang request sa database para makita ng Admin
        $studentReq = \App\Models\StudentRequest::create([
            'student_name' => $request->student_name,
            'lrn' => $request->lrn,
            'grade_level' => $request->grade_level,
            'section' => $request->section,
            'parent_email' => $request->parent_email,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account request submitted! Please wait for your teacher or admin to approve it. An email will be sent to your parent once approved.'
        ], 201);
    }


    // ============================================================================
    // 📸 UPLOAD PROFILE PICTURE
    // ============================================================================
    public function uploadAvatar(Request $request, $id)
    {
        // 1. Siguraduhing may image na ipinasa at tamang format
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120' // Max 5MB
        ]);

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // 2. I-save ang file sa storage/app/public/avatars
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
            
            // I-save sa 'avatars' folder
            $path = $file->storeAs('avatars', $filename, 'public');

            // 3. I-update ang database record
            $user->avatar = '/storage/' . $path;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile picture updated successfully!',
                'avatar_url' => url($user->avatar) // Ibibigay ang buong link (e.g. https://ngrok.../storage/avatars/...)
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Failed to upload image.'], 400);
    }
}