<?php

namespace Tests\Feature;

use App\Models\Apresentacao;
use App\Models\Arquivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApresentacaoApiTest extends TestCase
{
    use RefreshDatabase;

    private function criarApresentacao(User $user, string $nome = 'Minha apresentação'): Apresentacao
    {
        $arquivo = Arquivo::create([
            'user_id'    => $user->id,
            'nome'       => $nome,
            'tipo'       => 'apresentacao',
            'tamanho_kb' => 1,
        ]);

        return $arquivo->apresentacao()->create();
    }

    private function dadosApresentacao(array $extra = []): array
    {
        return array_merge([
            'nome'   => 'Banca de QA',
            'slides' => [
                ['titulo' => 'Introdução', 'corpo' => 'Visão de QA'],
                ['titulo' => 'Conclusão'],
            ],
        ], $extra);
    }

    public function test_cria_apresentacao_valida_retorna_201(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/apresentacoes', $this->dadosApresentacao())
            ->assertStatus(201)
            ->assertJsonPath('nome', 'Banca de QA')
            ->assertJsonCount(2, 'slides')
            ->assertJsonPath('slides.0.ordem', 1)
            ->assertJsonPath('slides.0.titulo', 'Introdução')
            ->assertJsonPath('slides.1.ordem', 2);

        $this->assertDatabaseHas('arquivos', [
            'nome'    => 'Banca de QA',
            'tipo'    => 'apresentacao',
            'user_id' => $user->id,
        ]);
    }

    public function test_apresentacao_sem_nome_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/apresentacoes', $this->dadosApresentacao(['nome' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nome');
    }

    public function test_slide_sem_titulo_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/apresentacoes', $this->dadosApresentacao([
            'slides' => [['titulo' => '', 'corpo' => 'texto']],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('slides.0.titulo');
    }

    public function test_mais_de_100_slides_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/apresentacoes', $this->dadosApresentacao([
            'slides' => array_fill(0, 101, ['titulo' => 'x']),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('slides');
    }

    public function test_mostra_apresentacao_propria(): void
    {
        $user = User::factory()->create();
        $apresentacao = $this->criarApresentacao($user, 'Minha apresentação');

        Sanctum::actingAs($user);

        $this->getJson('/api/apresentacoes/' . $apresentacao->id)
            ->assertOk()
            ->assertJsonPath('nome', 'Minha apresentação')
            ->assertJsonCount(0, 'slides');
    }

    public function test_nao_mostra_apresentacao_de_outro_usuario(): void
    {
        $apresentacao = $this->criarApresentacao(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/apresentacoes/' . $apresentacao->id)->assertStatus(404);
    }

    public function test_atualiza_apresentacao_e_substitui_os_slides(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/apresentacoes', $this->dadosApresentacao())->json('id');

        $this->putJson('/api/apresentacoes/' . $id, [
            'nome'   => 'Nome novo',
            'slides' => [['titulo' => 'Único']],
        ])
            ->assertOk()
            ->assertJsonPath('nome', 'Nome novo')
            ->assertJsonCount(1, 'slides')
            ->assertJsonPath('slides.0.titulo', 'Único');
    }

    public function test_nao_atualiza_apresentacao_de_outro_usuario(): void
    {
        $apresentacao = $this->criarApresentacao(User::factory()->create(), 'Nome original');

        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/apresentacoes/' . $apresentacao->id, ['nome' => 'Invasão'])
            ->assertStatus(404);

        $this->assertDatabaseHas('arquivos', [
            'id'   => $apresentacao->arquivo_id,
            'nome' => 'Nome original',
        ]);
    }

    public function test_apaga_apresentacao_e_o_arquivo_junto(): void
    {
        $user = User::factory()->create();
        $apresentacao = $this->criarApresentacao($user);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/apresentacoes/' . $apresentacao->id)->assertNoContent();

        $this->assertDatabaseMissing('arquivos', ['id' => $apresentacao->arquivo_id]);
        $this->assertModelMissing($apresentacao);
    }

    public function test_nao_apaga_apresentacao_de_outro_usuario(): void
    {
        $apresentacao = $this->criarApresentacao(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/apresentacoes/' . $apresentacao->id)->assertStatus(404);

        $this->assertModelExists($apresentacao);
    }
}