<?php

namespace App\Http\Requests\Guardias;

use App\Services\GuardiaGeneratorService;
use Illuminate\Foundation\Http\FormRequest;

class GuardarGuardiaManualRequest extends FormRequest
{
    public function colectivo(): string
    {
        return $this->is('residentes/*')
            ? GuardiaGeneratorService::COLECTIVO_RESIDENTES
            : GuardiaGeneratorService::COLECTIVO_ADJUNTOS;
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        if ($this->colectivo() === GuardiaGeneratorService::COLECTIVO_RESIDENTES) {
            return $user->hasAnyRole(['Jefe de Servicio', 'Tutor de Residentes', 'Residente Mayor']);
        }

        return $user->hasRole('Jefe de Servicio');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'fecha' => 'required|date',
            'tipo' => 'required|in:diaria_17h,festivo_24h',
            'modalidad' => 'nullable|in:presencial,localizada',
            'forzar' => 'nullable|boolean',
        ];
    }

    protected function passedValidation(): void
    {
        $user = $this->user();
        if ($user) {
            app(GuardiaGeneratorService::class)->validarFacultativoEnColectivo(
                (int) $this->input('user_id'),
                (int) $user->especialidad_id,
                $this->colectivo()
            );
        }
    }
}
