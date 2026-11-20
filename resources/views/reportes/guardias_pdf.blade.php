<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuadrante de Guardias - {{ $mes }}/{{ $anio }}</title>
    <style>
        @page {
            margin: 18px 20px 42px 20px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 8px 4px 16px 4px;
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
        .festivo-tag { font-size: 8px; font-weight: bold; color: #b45309; margin-bottom: 3px; }
        .especial-tag { font-size: 8px; font-weight: bold; color: #be123c; background-color: #ffe4e6; padding: 1px 3px; border-radius: 2px; display: inline-block; margin-bottom: 3px; }
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
        .guardia-badge.especial { background-color: #fecdd3; color: #9f1239; border: 1px solid #f43f5e; }

        /* Listado Secuencial */
        tr:nth-child(even) { background-color: #fafafa; }
        .finde { background-color: #fef3c7 !important; }
        .especial-row { background-color: #ffe4e6 !important; }
        .tipo-badge { font-size: 9px; font-family: monospace; color: #52525b; }

        .page-break { page-break-after: always; }
        .watermark-diagonal {
            position: fixed;
            top: 43%;
            left: 0;
            width: 100%;
            text-align: center;
            transform: rotate(-26deg);
            font-size: 54px;
            font-weight: bold;
            letter-spacing: 6px;
            color: #18181b;
            opacity: 0.045;
            z-index: -1;
        }
        .footer {
            position: fixed;
            bottom: -22px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #71717a;
            border-top: 1px solid #e4e4e7;
            padding-top: 6px;
        }
        .footer-brand {
            font-weight: bold;
            color: #52525b;
        }
    </style>
</head>
<body>
    @php
        $showWatermark = $includeWatermark ?? (bool) config('clinicore.exports.watermark_enabled', true);
        $showDiagonal = $includeDiagonalWatermark ?? (bool) config('clinicore.exports.pdf_diagonal_watermark', true);
        $brand = $brandName ?? (string) config('clinicore.brand.name', 'CliniCore');
        $domain = $brandDomain ?? (string) config('clinicore.brand.domain', 'clinicore.es');
    @endphp

    @if($showWatermark && $showDiagonal)
        <div class="watermark-diagonal">{{ strtoupper($brand) }} · {{ $domain }}</div>
    @endif

    <div class="header">
        <h1>Cuadrante Operativo de Guardias</h1>
        <p>{{ $hospital }} — {{ $especialidad }} | Mes {{ $mes }} del Año {{ $anio }}</p>
    </div>

    <!-- SECCIÓN 1: EQUIDAD -->
    <h3>Resumen de Equidad y Tipología de Horas</h3>
    <table class="grid-equidad">
        <thead>
            <tr>
                <th style="text-align: left;">Facultativo</th>
                <th>Laborables (17h)</th>
                <th>Finde No Festivo (24h)</th>
                <th>Festivos (24h)</th>
                <th>★ Especiales (24h)</th>
                <th>Modalidad (P / L)</th>
                <th>Total Guardias</th>
                <th>Total Horas</th>
            </tr>
        </thead>
        <tbody>
            @foreach($equidad as $eq)
            <tr>
                <td class="name">{{ $eq->nombre }}</td>
                <td>{{ $eq->diarias_17h ?? ($eq->totales - $eq->findes) }}</td>
                <td>{{ $eq->findes_24h ?? $eq->findes }}</td>
                <td>{{ $eq->festivos_24h ?? 0 }}</td>
                <td>{{ $eq->especiales_24h ?? 0 }}</td>
                <td>{{ $eq->presenciales ?? $eq->totales }}P / {{ $eq->localizadas ?? 0 }}L</td>
                <td><strong>{{ $eq->totales }}</strong></td>
                <td><strong>{{ $eq->horas_totales ?? (($eq->totales - $eq->findes) * 17 + $eq->findes * 24) }}h</strong></td>
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
                        <td class="{{ !empty($dia['es_especial']) ? 'especial-row' : ($dia['es_finde'] ? 'finde' : '') }}">
                            <div class="day-number">{{ $dia['numero'] }}</div>
                            @if(!empty($dia['es_especial']))
                                <div class="especial-tag">★ [ESPECIAL] {{ $dia['festivo_nombre'] ?? '' }}</div>
                            @elseif(!empty($dia['es_festivo']))
                                <div class="festivo-tag">Festivo: {{ $dia['festivo_nombre'] ?? '' }}</div>
                            @endif
                            @foreach($dia['guardias'] as $g)
                                <div class="guardia-badge {{ !empty($g->es_festivo_especial) ? 'especial' : (($g->es_24h ?? ($g->tipo === 'festivo_24h')) ? 'finde' : '') }}">
                                    {{ str_replace(['Dr. ', 'Dra. '], '', $g->facultativo?->name ?? 'Sin asignar') }}
                                    ({{ ($g->horas_efectivas_computo ?? $g->horas_computadas ?? ($g->tipo === 'festivo_24h' ? 24 : 17)) }}h{{ ($g->modalidad_turno ?? $g->modalidad) === 'localizada' ? ' · Loc.' : '' }})
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
                <th style="width: 32%;">Facultativo Asignado</th>
                <th style="width: 14%;">Modalidad</th>
                <th style="width: 24%;">Tipología / Horas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($guardias as $guardia)
                @php
                    $fecha = \Carbon\Carbon::parse($guardia->fecha);
                    $esEspecial = !empty($guardia->es_festivo_especial);
                    $es24h = (bool) ($guardia->es_24h ?? ($guardia->tipo === 'festivo_24h'));
                    $etiqueta = $guardia->tipo_etiqueta ?? ($es24h ? '24h (Fin de Semana / Festivo)' : '17h (Diaria)');
                    $modalidadEtiqueta = $guardia->modalidad_etiqueta ?? (($guardia->modalidad ?? 'presencial') === 'localizada' ? 'Localizada' : 'Presencial');
                @endphp
                <tr class="{{ $esEspecial ? 'especial-row' : ($es24h ? 'finde' : '') }}">
                    <td><strong>{{ $fecha->format('d/m/Y') }}</strong></td>
                    <td style="text-transform: capitalize;">{{ $fecha->locale('es')->dayName }}</td>
                    <td>
                        {{ $guardia->facultativo ? str_replace(['Dr. ', 'Dra. '], '', $guardia->facultativo->name) : 'Sin asignar' }}
                        @if($guardia->is_manual)
                            <span style="font-size: 10px; color: #d97706;"> (Manual)</span>
                        @endif
                    </td>
                    <td><strong>{{ $modalidadEtiqueta }}</strong></td>
                    <td>
                        <span class="tipo-badge">
                            {{ $etiqueta }}
                            @if(!empty($guardia->festivo_descripcion))
                                — {{ $guardia->festivo_descripcion }}
                            @endif
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px; color: #71717a;">
                        No hay turnos registrados en este periodo.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        @if($showWatermark)
            <span class="footer-brand">Cuadrante generado con {{ $brand }} · {{ $domain }}</span>
            &nbsp;|&nbsp;
        @endif
        Emitido el {{ date('d/m/Y H:i') }} · Válido a efectos de planificación interna de recursos humanos.
    </div>

</body>
</html>
