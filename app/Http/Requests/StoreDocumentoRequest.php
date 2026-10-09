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
            'arquivo' => ['nullable', 'file', 'mimes:txt,md', 'max:1024'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('conteudo') && ! $this->hasFile('arquivo')) {
                    $validator->errors()->add('conteudo', 'Informe o texto do documento ou envie um arquivo .txt/.md.');
                }
            },
        ];
    }

    /** Texto final: o digitado ou o conteúdo do arquivo enviado. */
    public function texto(): string
    {
        return $this->hasFile('arquivo')
            ? (string) file_get_contents($this->file('arquivo')->getRealPath())
            : (string) $this->input('conteudo');
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'O título é obrigatório.',
            'arquivo.mimes' => 'Envie um arquivo .txt ou .md.',
        ];
    }
}
