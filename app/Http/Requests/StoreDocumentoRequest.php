<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:150'],
            'conteudo' => ['nullable', 'string', 'max:200000'],
            'arquivo' => ['nullable', 'file', 'mimes:txt,md,pdf', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('conteudo') && ! $this->hasFile('arquivo')) {
                    $validator->errors()->add('conteudo', 'Cole o texto do documento ou envie um arquivo .txt, .md ou .pdf.');
                }
            },
        ];
    }

    public function enviouPdf(): bool
    {
        return $this->hasFile('arquivo') && strtolower($this->file('arquivo')->getClientOriginalExtension()) === 'pdf';
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'Dê um título ao documento.',
            'titulo.max' => 'O título pode ter até 150 caracteres.',
            'conteudo.max' => 'O texto passa do limite de 200.000 caracteres.',
            'arquivo.mimes' => 'Envie um arquivo .txt, .md ou .pdf.',
            'arquivo.max' => 'O arquivo pode ter até 5 MB.',
        ];
    }
}
