<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class BuscaLivro
{
    public function porIsbn(string $isbn): ?string
    {
        try {
            $resposta = Http::timeout(5)->get("https://openlibrary.org/isbn/{$isbn}.json");
        } catch (ConnectionException $e) {
            // API externa fora do ar: não derruba a requisição do estudante
            return null;
        }

        if ($resposta->failed()) {
            return null;
        }

        return $resposta->json('title');
    }
}
