<?php

namespace Tests\Feature;

use Tests\TestCase;

class RotasProtegidasTest extends TestCase
{
    public function test_todas_as_rotas_protegidas_exigem_login(): void
    {
        $rotas = [
            ['GET', '/api/user'],
            ['POST', '/api/logout'],
            ['GET', '/api/metas'],
            ['GET', '/api/eventos'],
            ['GET', '/api/livros'],
            ['GET', '/api/lista-leitura'],
            ['GET', '/api/documentos'],
            ['GET', '/api/arquivos'],
            ['GET', '/api/mapas-mentais'],
            ['GET', '/api/planilhas/1'],
            ['GET', '/api/apresentacoes/1'],
        ];

        foreach ($rotas as [$metodo, $caminho]) {
            $resposta = $this->json($metodo, $caminho);

            $this->assertSame(
                401,
                $resposta->getStatusCode(),
                "$metodo $caminho deveria exigir login (401)"
            );
        }
    }
}