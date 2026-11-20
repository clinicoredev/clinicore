<?php

namespace App\Exports;

use App\Models\Guardia;
use App\Models\User;
use App\Services\GuardiaGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * @implements WithMapping<Guardia>
 */
class GuardiasExport implements FromCollection, WithEvents, WithHeadings, WithMapping, WithMultipleSheets, WithTitle
{
    protected int $especialidad_id;
    protected int $mes;
    protected int $anio;
    /** @var array<int, int> */
    protected array $user_ids;
    /** @var array<string, array{id: int, fecha: string, es_especial: bool, descripcion: string}> */
    protected array $mapaFestivos = [];
    protected GuardiaGeneratorService $generatorService;
    protected bool $includeWatermark;

    /**
     * @param array<int, int> $user_ids
     */
    public function __construct(int $especialidad_id, int $mes, int $anio, array $user_ids = [], ?bool $includeWatermark = null)
    {
        $this->especialidad_id = $especialidad_id;
        $this->mes = $mes;
        $this->anio = $anio;
        $this->user_ids = $user_ids;
        $this->includeWatermark = $includeWatermark ?? (bool) config('clinicore.exports.watermark_enabled', true);
        $this->generatorService = app(GuardiaGeneratorService::class);
        $this->mapaFestivos = $this->generatorService->obtenerMapaFestivos($this->especialidad_id, $this->anio);
    }

    public function shouldIncludeWatermark(): bool
    {
        return $this->includeWatermark;
    }

    public function watermarkText(): string
    {
        $brandName = (string) config('clinicore.brand.name', 'CliniCore');
        $brandDomain = (string) config('clinicore.brand.domain', 'clinicore.es');

        return "Generado con {$brandName} ({$brandDomain})";
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                if (!$this->includeWatermark) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $watermarkRow = $sheet->getHighestRow() + 2;
                $sheet->setCellValue("A{$watermarkRow}", $this->watermarkText());
                $sheet->getStyle("A{$watermarkRow}")->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'size' => 9,
                        'color' => ['rgb' => '71717A'],
                    ],
                ]);
            },
        ];
    }

    public function title(): string
    {
        return 'Detalle de Turnos';
    }

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            $this,
            new GuardiasResumenNominaSheet($this),
        ];
    }

    /**
     * @return Collection<int, Guardia>
     */
    public function collection(): Collection
    {
        return Guardia::where('especialidad_id', $this->especialidad_id)
            ->when(!empty($this->user_ids), fn($q) => $q->whereIn('user_id', $this->user_ids))
            ->whereMonth('fecha', $this->mes)
            ->whereYear('fecha', $this->anio)
            ->with('facultativo:id,name')
            ->orderBy('fecha')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Fecha',
            'Día de la semana',
            'Facultativo Asignado',
            'Tipo de Turno',
            'Horas',
            'Método de Asignación',
            'Modalidad',
            'Horas Efectivas Cómputo',
        ];
    }

    /**
     * @param Guardia $guardia
     * @return array<int, string|int>
     */
    public function map($guardia): array
    {
        $info = $this->generatorService->clasificarTipologiaGuardia($guardia, $this->mapaFestivos);
        $tipoDetalle = $info['etiqueta'];
        if ($info['festivo_descripcion'] !== null) {
            $tipoDetalle .= ' - ' . $info['festivo_descripcion'];
        }

        $fechaCarbon = Carbon::parse($guardia->fecha);
        $fechaCarbon->locale('es');

        return [
            $fechaCarbon->format('d/m/Y'),
            ucfirst($fechaCarbon->dayName),
            $guardia->facultativo ? str_replace(['Dr. ', 'Dra. '], '', $guardia->facultativo->name) : 'Desconocido',
            $tipoDetalle,
            $info['horas'] . 'h',
            $guardia->is_manual ? 'Manual' : 'Automático (IA)',
            $info['etiqueta_modalidad'],
            $info['horas_efectivas_computo'] . 'h',
        ];
    }

    /**
     * F6-06: Construye el resumen mensual de cómputo horario por facultativo para Nómina / RRHH.
     *
     * @return Collection<int, array{
     *   facultativo: string,
     *   porcentaje_jornada: string,
     *   turnos_17h: int,
     *   turnos_finde_24h: int,
     *   turnos_festivo_24h: int,
     *   turnos_especial_24h: int,
     *   turnos_presenciales: int,
     *   turnos_localizados: int,
     *   total_guardias: int,
     *   horas_totales_computo: int
     * }>
     */
    public function resumenNomina(): Collection
    {
        $guardias = $this->collection();
        $usuariosQuery = User::where('especialidad_id', $this->especialidad_id)
            ->when(!empty($this->user_ids), fn($q) => $q->whereIn('id', $this->user_ids));

        $usuarios = $usuariosQuery->orderBy('name')->get();
        $agrupadas = $guardias->groupBy('user_id');

        return $usuarios->map(function (User $u) use ($agrupadas): array {
            $misTurnos = $agrupadas->get($u->id, collect());
            $t17 = 0;
            $tFinde24 = 0;
            $tFest24 = 0;
            $tEsp24 = 0;
            $presenciales = 0;
            $localizadas = 0;
            $horasComputo = 0;

            foreach ($misTurnos as $g) {
                $info = $this->generatorService->clasificarTipologiaGuardia($g, $this->mapaFestivos);
                $horasComputo += $info['horas_efectivas_computo'];

                if ($info['modalidad'] === 'localizada') {
                    $localizadas++;
                } else {
                    $presenciales++;
                }

                if (!$info['es_24h']) {
                    $t17++;
                } elseif ($info['es_festivo_oficial']) {
                    $tFest24++;
                    if ($info['es_festivo_especial']) {
                        $tEsp24++;
                    }
                } else {
                    $tFinde24++;
                }
            }

            return [
                'facultativo' => str_replace(['Dr. ', 'Dra. '], '', (string) $u->name),
                'porcentaje_jornada' => ((int) ($u->porcentaje_jornada ?? 100)) . '%',
                'turnos_17h' => $t17,
                'turnos_finde_24h' => $tFinde24,
                'turnos_festivo_24h' => $tFest24,
                'turnos_especial_24h' => $tEsp24,
                'turnos_presenciales' => $presenciales,
                'turnos_localizados' => $localizadas,
                'total_guardias' => $misTurnos->count(),
                'horas_totales_computo' => $horasComputo,
            ];
        })->values();
    }
}

/**
 * Segunda hoja del Excel con el resumen consolidado de horas para RRHH / Nómina (F6-06).
 */
class GuardiasResumenNominaSheet implements FromCollection, WithEvents, WithHeadings, WithTitle
{
    public function __construct(
        private readonly GuardiasExport $parentExport
    ) {}

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                if (!$this->parentExport->shouldIncludeWatermark()) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $watermarkRow = $sheet->getHighestRow() + 2;
                $sheet->setCellValue("A{$watermarkRow}", $this->parentExport->watermarkText());
                $sheet->getStyle("A{$watermarkRow}")->applyFromArray([
                    'font' => [
                        'italic' => true,
                        'size' => 9,
                        'color' => ['rgb' => '71717A'],
                    ],
                ]);
            },
        ];
    }

    public function title(): string
    {
        return 'Resumen RRHH Nómina';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Facultativo',
            '% Jornada',
            'Laborables (17h)',
            'Fin de Semana (24h)',
            'Festivos (24h)',
            '★ Especiales (24h)',
            'Turnos Presenciales',
            'Turnos Localizados',
            'Total Guardias',
            'Total Horas Cómputo',
        ];
    }

    /**
     * @return Collection<int, array<int, int|string>>
     */
    public function collection(): Collection
    {
        return $this->parentExport->resumenNomina()->map(fn(array $row): array => [
            $row['facultativo'],
            $row['porcentaje_jornada'],
            $row['turnos_17h'],
            $row['turnos_finde_24h'],
            $row['turnos_festivo_24h'],
            $row['turnos_especial_24h'],
            $row['turnos_presenciales'],
            $row['turnos_localizados'],
            $row['total_guardias'],
            $row['horas_totales_computo'] . 'h',
        ]);
    }
}
