<?php

namespace App\Http\Requests\Guardias;

use App\Services\GuardiaGeneratorService;
use Illuminate\Foundation\Http\FormRequest;

class PermutarGuardiaRequest extends FormRequest
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
            'origen_id' => 'required|exists:guardias,id',
            'destino_id' => 'required|exists:guardias,id',
        ];
    }
}
