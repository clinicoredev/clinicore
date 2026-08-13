<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    // 1. Mostrar la vista de Vue para establecer contraseña
    public function show(Request $request, User $user)
    {
        return Inertia::render('auth/EstablecerPassword', [
            'usuario' => $user
        ]);
    }

    // 2. Guardar la nueva contraseña y hacer Login automático
    public function store(Request $request, User $user)
    {
        $request->validate([
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Iniciamos sesión y lo lanzamos al Dashboard
        Auth::login($user);

        return redirect()->route('dashboard'); 
    }
}