<?php

namespace Tests\Unit;

use App\Services\BuscaLivro;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Testes unitários do serviço BuscaLivro.
 * A API externa (Open Library) é substituída por dublês (Http::fake),
 * então nenhum teste depende da internet.
 */
class BuscaLivroStubTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests(); // qualquer chamada real à internet faz o teste falhar
    }

    public function test_retorna_titulo_quando_a_api_responde_200(): void
    {
        // Arrange – STUB: resposta pronta da Open Library
        Http::fake([
            'openlibrary.org/isbn/*' => Http::response(['title' => 'Clean Code'], 200),
        ]);

        // Act
        $titulo = (new BuscaLivro())->porIsbn('9780132350884');

        // Assert – valor retornado + SPY: confere a URL que foi chamada
        $this->assertSame('Clean Code', $titulo);
        Http::assertSent(fn (Request $req) =>
            $req->url() === 'https://openlibrary.org/isbn/9780132350884.json'
            && $req->method() === 'GET'
        );
        Http::assertSentCount(1);
    }

    public function test_retorna_null_quando_isbn_nao_existe_404(): void
    {
        // Arrange – STUB de erro
        Http::fake(['openlibrary.org/*' => Http::response(['error' => 'notfound'], 404)]);

        // Act
        $titulo = (new BuscaLivro())->porIsbn('0000000000');

        // Assert
        $this->assertNull($titulo);
    }

    public function test_retorna_null_quando_a_api_externa_esta_fora_do_ar(): void
    {
        // Arrange – STUB de falha de rede (timeout / DNS / conexão recusada)
        Http::fake(['openlibrary.org/*' => Http::failedConnection()]);

        // Act
        $titulo = (new BuscaLivro())->porIsbn('9780132350884');

        // Assert – o serviço deve degradar com elegância, sem lançar exceção
        $this->assertNull($titulo);
    }
}
