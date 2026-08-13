<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; background-color: #09090b; color: #f4f4f5; padding: 20px; text-align: center; }
        .box { background: #18181b; padding: 30px; border-radius: 12px; max-width: 500px; margin: 0 auto; border: 1px solid #27272a; }
        .btn { background: #10b981; color: #000; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="box">
        <h2 style="color: #10b981;">Bienvenido a CliniCore</h2>
        <p>Hola {{ $user->name }}, tu Jefe de Servicio te ha dado de alta en la plataforma de gestión clínica.</p>
        <p>Para activar tu cuenta y acceder a tus turnos y guardias, haz clic en el siguiente botón y configura tu contraseña personal:</p>
        <a href="{{ $urlInvitacion }}" class="btn">Activar Cuenta</a>
        <p style="margin-top: 30px; font-size: 12px; color: #71717a;">Este enlace caducará en 48 horas por motivos de seguridad.</p>
    </div>
</body>
</html>