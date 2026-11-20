<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'es_superadmin' => $request->user()->hasRole('SuperAdmin'),
                    'es_jefe' => $request->user()->hasRole('Jefe de Servicio'),
                    'es_tutor' => $request->user()->hasRole('Tutor de Residentes'),

                    // Nombres dinámicos extraídos de las relaciones
                    'especialidad' => $request->user()->especialidad->nombre ?? 'Administración SaaS',
                    'hospital' => $request->user()->especialidad->hospital->nombre ?? 'Plataforma Central',
                ] : null,

                'notificaciones' => $request->user()
                    ? $request->user()->unreadNotifications()->take(5)->get()
                    : [],
                'notificaciones_count' => $request->user()
                    ? $request->user()->unreadNotifications()->count()
                    : 0,

                'impersonated' => session()->has('impersonated_by'),
            ],
            'flash' => [
                'flash_toast' => fn() => $request->session()->get('flash_toast'),
            ],
            'clinicore' => [
                'name' => config('clinicore.brand.name', 'CliniCore'),
                'domain' => config('clinicore.brand.domain', 'clinicore.es'),
                'email' => config('clinicore.brand.email', 'clinicore.dev@gmail.com'),
                'includeWatermark' => (bool) config('clinicore.exports.watermark_enabled', true),
            ],
        ];
    }
}
