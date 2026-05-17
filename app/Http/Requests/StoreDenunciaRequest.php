<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDenunciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ufs = [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG',
            'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
        ];

        return [
            'titulo' => ['required', 'string', 'min:8', 'max:220'],
            'descricao' => ['required', 'string', 'min:20', 'max:4000'],
            'categoria' => ['required', 'string', 'in:' . implode(',', array_keys(config('denuncias.categories', [])))],
            'estado' => ['required', 'string', 'size:2', 'in:' . implode(',', $ufs)],
            'cidade' => ['required', 'string', 'min:2', 'max:120'],
            'bairro' => ['required', 'string', 'min:2', 'max:160'],
            'endereco_aproximado' => ['nullable', 'string', 'max:255'],
            'imagens'   => ['required', 'array', 'min:1', 'max:5'],
            'imagens.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:6144'],
            'lgpd_aceite' => ['accepted'],
            'lgpd_policy_version' => ['required', 'string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'titulo' => strip_tags((string) $this->input('titulo')),
            'descricao' => strip_tags((string) $this->input('descricao')),
            'cidade' => strip_tags((string) $this->input('cidade')),
            'bairro' => strip_tags((string) $this->input('bairro')),
            'endereco_aproximado' => $this->has('endereco_aproximado') ? strip_tags((string) $this->input('endereco_aproximado')) : null,
            'estado' => strtoupper(trim((string) $this->input('estado'))),
            'lgpd_policy_version' => strip_tags((string) $this->input('lgpd_policy_version')),
        ]);
    }
}