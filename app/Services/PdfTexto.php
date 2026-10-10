<?php

namespace App\Services;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

/** Extrai o texto de PDFs que têm camada de texto (sem OCR). */
class PdfTexto
{
    public function extrair(string $caminho): string
    {
        try {
            $texto = (new Parser)->parseFile($caminho)->getText();
        } catch (Throwable $e) {
            throw new RuntimeException('Não foi possível ler este PDF. Ele pode estar protegido ou corrompido.', 0, $e);
        }

        $texto = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $texto);
        $texto = preg_replace('/[ \x{00A0}]+/u', ' ', $texto);
        $texto = preg_replace('/ ?\n ?/', "\n", $texto);
        $texto = trim(preg_replace('/\n{3,}/', "\n\n", $texto));

        if ($texto === '') {
            throw new RuntimeException('Este PDF não tem texto selecionável (parece ser uma imagem). OCR não está incluído.');
        }

        return $texto;
    }
}
