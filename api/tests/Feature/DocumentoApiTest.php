<?php

namespace Tests\Feature;

use App\Models\Arquivo;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentoApiTest extends TestCase
{
    use RefreshDatabase;

    private function criarDocumento(User $user, string $nome = 'Resumo', string $corpo = 'Texto do resumo'): Documento
    {
        $arquivo = Arquivo::create([
            'user_id'    => $user->id,
            'nome'       => $nome,
            'tipo'       => 'documento',
            'tamanho_kb' => 1,
        ]);

        return $arquivo->documento()->create(['corpo' => $corpo]);
    }

    public function test_cria_documento_valido_retorna_201(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/documentos', [
            'nome'  => 'Resumo de BD',
            'corpo' => 'Normalização e ER',
        ])
            ->assertStatus(201)
            ->assertJsonPath('nome', 'Resumo de BD')
            ->assertJsonPath('tamanho_kb', 1);

        $this->assertDatabaseHas('arquivos', [
            'nome'    => 'Resumo de BD',
            'tipo'    => 'documento',
            'user_id' => $user->id,
        ]);
    }

    public function test_documento_sem_nome_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/documentos', ['nome' => '', 'corpo' => 'texto'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nome');
    }

    public function test_tamanho_em_kb_e_calculado_pelo_corpo(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/documentos', [
            'nome'  => 'Documento grande',
            'corpo' => str_repeat('a', 2048),
        ])
            ->assertStatus(201)
            ->assertJsonPath('tamanho_kb', 2);
    }

    public function test_lista_apenas_os_documentos_do_usuario_logado(): void
    {
        $user = User::factory()->create();
        $outro = User::factory()->create();

        $this->criarDocumento($user, 'Meu documento');
        $this->criarDocumento($outro, 'Documento de outra pessoa');

        Sanctum::actingAs($user);

        $this->getJson('/api/documentos')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nome', 'Meu documento');
    }

    public function test_mostra_documento_proprio(): void
    {
        $user = User::factory()->create();
        $doc = $this->criarDocumento($user, 'Meu documento', 'Conteúdo');

        Sanctum::actingAs($user);

        $this->getJson('/api/documentos/' . $doc->id)
            ->assertOk()
            ->assertJsonPath('nome', 'Meu documento')
            ->assertJsonPath('corpo', 'Conteúdo');
    }

    public function test_nao_mostra_documento_de_outro_usuario(): void
    {
        $dono = User::factory()->create();
        $doc = $this->criarDocumento($dono);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/documentos/' . $doc->id)->assertStatus(404);
    }

    public function test_atualiza_nome_e_corpo_do_documento(): void
    {
        $user = User::factory()->create();
        $doc = $this->criarDocumento($user);

        Sanctum::actingAs($user);

        $this->putJson('/api/documentos/' . $doc->id, [
            'nome'  => 'Nome novo',
            'corpo' => 'Corpo novo',
        ])
            ->assertOk()
            ->assertJsonPath('nome', 'Nome novo')
            ->assertJsonPath('corpo', 'Corpo novo');

        $this->assertDatabaseHas('arquivos', [
            'id'   => $doc->arquivo_id,
            'nome' => 'Nome novo',
        ]);
    }

    public function test_nao_atualiza_documento_de_outro_usuario(): void
    {
        $dono = User::factory()->create();
        $doc = $this->criarDocumento($dono, 'Nome original');

        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/documentos/' . $doc->id, ['nome' => 'Invasão'])
            ->assertStatus(404);

        $this->assertDatabaseHas('arquivos', [
            'id'   => $doc->arquivo_id,
            'nome' => 'Nome original',
        ]);
    }

    public function test_apaga_documento_e_o_arquivo_junto(): void
    {
        $user = User::factory()->create();
        $doc = $this->criarDocumento($user);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/documentos/' . $doc->id)->assertNoContent();

        $this->assertDatabaseMissing('arquivos', ['id' => $doc->arquivo_id]);
        $this->assertDatabaseMissing('documentos', ['id' => $doc->id]);
    }

    public function test_nao_apaga_documento_de_outro_usuario(): void
    {
        $dono = User::factory()->create();
        $doc = $this->criarDocumento($dono);

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/documentos/' . $doc->id)->assertStatus(404);

        $this->assertDatabaseHas('documentos', ['id' => $doc->id]);
    }

    public function test_documento_com_texto_grande_demais_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/documentos', [
            'nome'  => 'Gigante',
            'corpo' => str_repeat('a', 16001), // 1 acima do limite
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('corpo');

        $this->assertDatabaseCount('documentos', 0);
    }

    public function test_documento_no_limite_de_tamanho_e_aceito(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/documentos', [
            'nome'  => 'No limite',
            'corpo' => str_repeat('a', 16000), // exatamente o máximo
        ])->assertStatus(201);
    }
}
