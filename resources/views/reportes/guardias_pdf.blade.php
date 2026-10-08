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
            margin-bottom: 20px;
            border-bottom: 2px solid #10b981;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #18181b;
            text-transform: uppercase;
        }
        .header p { margin: 5px 0 0 0; font-size: 14px; color: #52525b; }
        
        h3 {
            font-size: 14px;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #18181b;
            border-bottom: 1px solid #d4d4d8;
            padding-bottom: 4px;
        }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #d4d4d8; padding: 8px; text-align: left; }
        th { background-color: #18181b; color: #ffffff; font-weight: bold; text-transform: uppercase; font-size: 10px; text-align: center; }
        
        /* Tabla de Equidad */
        .grid-equidad td { text-align: center; font-size: 11px; padding: 6px; }
        .grid-equidad td.name { text-align: left; font-weight: bold; }
        
        /* Cuadrícula del Calendario */
        .calendar-table { table-layout: fixed; }
        .calendar-table td { height: 60px; vertical-align: top; padding: 4px; width: 14.28%; }
        .day-number { font-weight: bold; font-size: 10px; color: #52525b; margin-bottom: 4px; }
        .empty-cell { background-color: #f4f4f5; }
        .guardia-badge {
            font-size: 9px;
            background-color: #e4e4e7;
            border-radius: 3px;
            padding: 3px;
            margin-bottom: 2px;
            text-align: center;
            overflow: hidden;
            white-space: nowrap;
            font-weight: bold;
        }
        .guardia-badge.finde { background-color: #fde68a; color: #92400e; }
        
        /* Listado Secuencial */
        tr:nth-child(even) { background-color: #fafafa; }
        .finde { background-color: #fef3c7 !important; }
        .tipo-badge { font-size: 9px; font-family: monospace; color: #52525b; }
        
        .page-break { page-break-after: always; }
        .footer {
            position: fixed; bottom: -10px; left: 0; right: 0; text-align: center; 
            font-size: 10px; color: #a1a1aa; border-top: 1px solid #e4e4e7; padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Cuadrante Operativo de Guardias</h1>
        <p>{{ $hospital }} — {{ $especialidad }} | Mes {{ $mes }} del Año {{ $anio }}</p>
    </div>

    <!-- SECCIÓN 1: EQUIDAD -->
    <h3>Resumen de Equidad</h3>
    <table class="grid-equidad">
        <thead>
            <tr>
                <th style="text-align: left;">Facultativo</th>
                <th>Total Guardias</th>
                <th>Fines de semana / Festivos (24h)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($equidad as $eq)
            <tr>
                <td class="name">{{ $eq['nombre'] }}</td>
                <td>{{ $eq['totales'] }}</td>
                <td>{{ $eq['findes'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- SECCIÓN 2: CALENDARIO VISUAL -->
    <h3>Vista de Calendario</h3>
    <table class="calendar-table">
        <thead>
            <tr>
                <th>Lun</th>
                <th>Mar</th>
                <th>Mié</th>
                <th>Jue</th>
                <th>Vie</th>
                <th>Sáb</th>
                <th>Dom</th>
            </tr>
        </thead>
        <tbody>
            @foreach($calendario as $semana)
            <tr>
                @foreach($semana as $dia)
                    @if($dia)
                        <td class="{{ $dia['es_finde'] ? 'finde' : '' }}">
                            <div class="day-number">{{ $dia['numero'] }}</div>
                            @foreach($dia['guardias'] as $g)
                                <div class="guardia-badge {{ $g->tipo === 'festivo_24h' ? 'finde' : '' }}">
                                    {{ str_replace(['Dr. ', 'Dra. '], '', $g->facultativo->name) }}
                                </div>
                            @endforeach
                        </td>
                    @else
                        <td class="empty-cell"></td>
                    @endif
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    
    <!-- SALTO DE PÁGINA ANTES DEL LISTADO CLÁSICO -->
    <div class="page-break"></div>

    <!-- SECCIÓN 3: LISTADO SECUENCIAL -->
    <h3>Listado Cronológico</h3>
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