<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function show(Request $request, User $user)
    {
        $this->validarFirma($request);

        $user->load('especialidad.hospital');

        return Inertia::render('auth/EstablecerPassword', [
            'usuario' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'especialidad' => $user->especialidad->nombre ?? 'Servicio Médico',
                'hospital' => $user->especialidad->hospital->nombre ?? 'Centro Sanitario',
            ],
            'action_url' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, User $user)
    {
        $this->validarFirma($request);

        if ($user->email_verified_at !== null) {
            abort(403, 'Este enlace de activación ya ha sido utilizado.');
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($request->password),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'calendar_token' => $user->calendar_token ?? Str::random(40),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', '¡Cuenta activada con éxito! Bienvenido a CliniCore.'); 
    }

    private function validarFirma(Request $request): void
    {
        // Acepta la firma tanto si se generó con dominio absoluto (true) como relativo (false)
        $firmaValida = $request->hasValidSignature(true) || $request->hasValidSignature(false);

        if (!$firmaValida) {
            abort(403, 'El enlace de invitación ha expirado o no es válido.');
        }
    }
}