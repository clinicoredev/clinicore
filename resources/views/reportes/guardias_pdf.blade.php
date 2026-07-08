<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuadrante de Guardias - {{ $mes }}/{{ $anio }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #10b981; /* Verde esmeralda */
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #18181b; /* Zinc 900 */
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 14px;
            color: #52525b; /* Zinc 500 */
        }
        .info-box {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f4f4f5;
            border-radius: 4px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #d4d4d8; /* Zinc 300 */
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #18181b;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
        }
        tr:nth-child(even) {
            background-color: #fafafa;
        }
        .finde {
            background-color: #fef3c7 !important; /* Ámbar muy clarito para findes */
        }
        .tipo-badge {
            font-size: 10px;
            font-family: monospace;
            color: #52525b;
        }
        .footer {
            position: fixed;
            bottom: -10px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #a1a1aa;
            border-top: 1px solid #e4e4e7;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Cuadrante Operativo de Guardias</h1>
        <p>{{ $hospital }} — {{ $especialidad }}</p>
    </div>

    <div class="info-box">
        Periodo de facturación y turnos: Mes {{ $mes }} del Año {{ $anio }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Fecha</th>
                <th style="width: 15%;">Día</th>
                <th style="width: 45%;">Facultativo Asignado</th>
                <th style="width: 25%;">Turno</th>
            </tr>
        </thead>
        <tbody>
            @forelse($guardias as $guardia)
                @php
                    $fecha = \Carbon\Carbon::parse($guardia->fecha);
                    $esFinde = $guardia->tipo === 'festivo_24h';
                @endphp
                <tr class="{{ $esFinde ? 'finde' : '' }}">
                    <td><strong>{{ $fecha->format('d/m/Y') }}</strong></td>
                    <td style="text-transform: capitalize;">{{ $fecha->locale('es')->dayName }}</td>
                    <td>
                        {{ $guardia->facultativo ? str_replace(['Dr. ', 'Dra. '], '', $guardia->facultativo->name) : 'Sin asignar' }}
                        @if($guardia->is_manual)
                            <span style="font-size: 10px; color: #d97706;"> (Manual)</span>
                        @endif
                    </td>
                    <td>
                        <span class="tipo-badge">
                            {{ $esFinde ? 'Atención 24h' : 'Ordinaria 17h' }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px; color: #71717a;">
                        No hay turnos registrados en este periodo.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Documento generado automáticamente por el Sistema Clínico Operativo el {{ date('d/m/Y H:i') }}. 
        Válido a efectos de planificación interna de recursos humanos.
    </div>

</body>
</html>