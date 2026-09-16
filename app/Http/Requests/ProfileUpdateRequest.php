<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * O telefone entra com máscara e é guardado só com dígitos — o mesmo
     * tratamento do formulário de inscrição, para os dois combinarem.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => preg_replace('/\D/', '', (string) $this->input('phone')) ?: null,
            ]);
        }
    }

    /**
     * `is_admin` e `is_church_president` não estão aqui, e é o que impede o
     * perfil de virar escada para os painéis: `update()` grava só o validado.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'digits_between:10,11'],
            'church_id' => ['nullable', 'integer', Rule::exists('churches', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.digits_between' => 'Informe o número com DDD (10 ou 11 dígitos).',
            'church_id.exists' => 'Selecione uma igreja da lista.',
        ];
    }
}
