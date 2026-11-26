<?php

namespace Tests\Feature;

use App\Models\Arquivo;
use App\Models\Planilha;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanilhaApiTest extends TestCase
{
    use RefreshDatabase;

    private function criarPlanilha(User $user, string $nome = 'Minha planilha'): Planilha
    {
        $arquivo = Arquivo::create([
            'user_id'    => $user->id,
            'nome'       => $nome,
            'tipo'       => 'planilha',
            'tamanho_kb' => 1,
        ]);

        return $arquivo->planilha()->create(['linhas' => 3, 'colunas' => 3]);
    }

    private function dadosPlanilha(array $extra = []): array
    {
        return array_merge([
            'nome'    => 'Notas',
            'linhas'  => 5,
            'colunas' => 3,
            'celulas' => [
                ['linha' => 0, 'coluna' => 0, 'valor' => 'Nome'],
                ['linha' => 0, 'coluna' => 1, 'valor' => 'Nota'],
                ['linha' => 1, 'coluna' => 0, 'valor' => 'Ana'],
                ['linha' => 1, 'coluna' => 1, 'valor' => '=A1+B1'],
            ],
        ], $extra);
    }

    public function test_cria_planilha_valida_retorna_201(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $resposta = $this->postJson('/api/planilhas', $this->dadosPlanilha())
            ->assertStatus(201)
            ->assertJsonPath('nome', 'Notas')
            ->assertJsonPath('linhas', 5)
            ->assertJsonCount(4, 'celulas');

        $this->assertDatabaseHas('arquivos', [
            'nome'    => 'Notas',
            'tipo'    => 'planilha',
            'user_id' => $user->id,
        ]);

        // a fórmula é guardada como texto, do jeito que foi digitada
        $formula = collect($resposta->json('celulas'))
            ->first(fn ($c) => $c['linha'] == 1 && $c['coluna'] == 1);
        $this->assertSame('=A1+B1', $formula['valor']);
    }

    public function test_planilha_sem_nome_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/planilhas', $this->dadosPlanilha(['nome' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nome');
    }

    public function test_linhas_alem_do_limite_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/planilhas', $this->dadosPlanilha(['linhas' => 101]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('linhas');
    }

    public function test_colunas_alem_do_limite_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/planilhas', $this->dadosPlanilha(['colunas' => 9]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('colunas');
    }

    public function test_ignora_celula_fora_da_grade(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/planilhas', $this->dadosPlanilha([
            'linhas'  => 2,
            'colunas' => 2,
            'celulas' => [
                ['linha' => 0, 'coluna' => 0, 'valor' => 'dentro'],
                ['linha' => 5, 'coluna' => 0, 'valor' => 'fora'],
            ],
        ]))
            ->assertStatus(201)
            ->assertJsonCount(1, 'celulas');
    }

    public function test_nao_salva_celulas_vazias(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/planilhas', $this->dadosPlanilha([
            'celulas' => [
                ['linha' => 0, 'coluna' => 0, 'valor' => 'A'],
                ['linha' => 0, 'coluna' => 1, 'valor' => ''],
            ],
        ]))
            ->assertStatus(201)
            ->assertJsonCount(1, 'celulas');
    }

    public function test_mostra_planilha_propria(): void
    {
        $user = User::factory()->create();
        $planilha = $this->criarPlanilha($user, 'Minha planilha');

        Sanctum::actingAs($user);

        $this->getJson('/api/planilhas/' . $planilha->id)
            ->assertOk()
            ->assertJsonPath('nome', 'Minha planilha')
            ->assertJsonPath('linhas', 3);
    }

    public function test_nao_mostra_planilha_de_outro_usuario(): void
    {
        $planilha = $this->criarPlanilha(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/planilhas/' . $planilha->id)->assertStatus(404);
    }

    public function test_atualiza_planilha_e_substitui_as_celulas(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/planilhas', $this->dadosPlanilha())->json('id');

        $this->putJson('/api/planilhas/' . $id, [
            'nome'    => 'Nome novo',
            'linhas'  => 4,
            'colunas' => 4,
            'celulas' => [['linha' => 0, 'coluna' => 0, 'valor' => 'X']],
        ])
            ->assertOk()
            ->assertJsonPath('nome', 'Nome novo')
            ->assertJsonPath('linhas', 4)
            ->assertJsonCount(1, 'celulas');
    }

    public function test_nao_atualiza_planilha_de_outro_usuario(): void
    {
        $planilha = $this->criarPlanilha(User::factory()->create(), 'Nome original');

        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/planilhas/' . $planilha->id, [
            'nome'    => 'Invasão',
            'linhas'  => 3,
            'colunas' => 3,
        ])->assertStatus(404);

        $this->assertDatabaseHas('arquivos', [
            'id'   => $planilha->arquivo_id,
            'nome' => 'Nome original',
        ]);
    }

    public function test_apaga_planilha_e_o_arquivo_junto(): void
    {
        $user = User::factory()->create();
        $planilha = $this->criarPlanilha($user);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/planilhas/' . $planilha->id)->assertNoContent();

        $this->assertDatabaseMissing('arquivos', ['id' => $planilha->arquivo_id]);
        $this->assertModelMissing($planilha);
    }

    public function test_nao_apaga_planilha_de_outro_usuario(): void
    {
        $planilha = $this->criarPlanilha(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/planilhas/' . $planilha->id)->assertStatus(404);

        $this->assertModelExists($planilha);
    }
}