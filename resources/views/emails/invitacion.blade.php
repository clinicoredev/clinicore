<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #09090b; color: #f4f4f5; padding: 24px; text-align: center; margin: 0; }
        .box { background: #18181b; padding: 32px; border-radius: 14px; max-width: 520px; margin: 0 auto; border: 1px solid #27272a; }
        .brand { font-size: 20px; font-weight: 800; color: #ffffff; letter-spacing: -0.02em; margin-bottom: 16px; }
        .brand span { color: #10b981; }
        .btn { background: #10b981; color: #09090b; padding: 12px 26px; text-decoration: none; border-radius: 8px; font-weight: 700; display: inline-block; margin-top: 20px; }
        .footer { margin-top: 28px; padding-top: 16px; border-top: 1px solid #27272a; font-size: 11px; color: #71717a; line-height: 1.6; }
        .footer a { color: #10b981; text-decoration: none; }
    </style>
</head>
<body>
    <div class="box">
        <div class="brand">Clini<span>Core</span></div>
        <h2 style="color: #10b981; margin: 0 0 12px 0; font-size: 18px;">Te han invitado a tu equipo en CliniCore</h2>
        <p style="color: #d4d4d8; font-size: 14px; line-height: 1.6;">Hola {{ $user->name }}, el responsable de tu servicio te ha dado de alta en la plataforma de planificación de guardias.</p>
        <p style="color: #a1a1aa; font-size: 14px; line-height: 1.6;">Para activar tu cuenta y consultar tus turnos, salientes y calendario, haz clic en el siguiente botón y configura tu contraseña personal:</p>
        <a href="{{ $urlInvitacion }}" class="btn">Activar Cuenta</a>
        <p style="margin-top: 24px; font-size: 12px; color: #71717a;">Este enlace seguro caducará en 48 horas por motivos de seguridad.</p>
        <div class="footer">
            {{ config('clinicore.brand.name', 'CliniCore') }} ·
            <a href="{{ config('clinicore.brand.url', 'https://clinicore.es') }}">{{ config('clinicore.brand.domain', 'clinicore.es') }}</a> ·
            <a href="mailto:{{ config('clinicore.brand.email', 'clinicore.dev@gmail.com') }}">{{ config('clinicore.brand.email', 'clinicore.dev@gmail.com') }}</a>
        </div>
    </div>
</body>
</html>
