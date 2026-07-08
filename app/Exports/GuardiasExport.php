<?php

namespace App\Exports;

use App\Models\Guardia;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class GuardiasExport implements FromCollection, WithHeadings, WithMapping
{
    protected $especialidad_id;
    protected $mes;
    protected $anio;

    public function __construct($especialidad_id, $mes, $anio)
    {
        $this->especialidad_id = $especialidad_id;
        $this->mes = $mes;
        $this->anio = $anio;
    }

    public function collection()
    {
        return Guardia::where('especialidad_id', $this->especialidad_id)
            ->whereMonth('fecha', $this->mes)
            ->whereYear('fecha', $this->anio)
            ->with('facultativo:id,name')
            ->orderBy('fecha')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Día de la semana',
            'Facultativo Asignado',
            'Tipo de Turno',
            'Método de Asignación'
        ];
    }

    public function map($guardia): array
    {
        return [
            Carbon::parse($guardia->fecha)->format('d/m/Y'),
            ucfirst(Carbon::parse($guardia->fecha)->locale('es')->dayName),
            $guardia->facultativo ? str_replace(['Dr. ', 'Dra. '], '', $guardia->facultativo->name) : 'Desconocido',
            $guardia->tipo === 'festivo_24h' ? '24h (Fin de semana)' : '17h (Diaria)',
            $guardia->is_manual ? 'Manual' : 'Automático (IA)'
        ];
    }
}