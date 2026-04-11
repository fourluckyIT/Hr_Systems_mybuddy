<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user && $user->hasAnyRole(['employee', 'viewer']) && !$user->hasAnyRole(['admin', 'hr', 'manager'])) {
                return redirect()->route('workspace.my');
            }

            return redirect()->route('employees.index');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            if ($user && $user->hasAnyRole(['employee', 'viewer']) && !$user->hasAnyRole(['admin', 'hr', 'manager'])) {
                return redirect()->intended(route('workspace.my'));
            }

            return redirect()->intended(route('employees.index'));
        }

        return back()->withErrors([
            'email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
