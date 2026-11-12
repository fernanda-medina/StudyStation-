<?php

namespace Tests\Feature;

use App\Models\Arquivo;
use App\Models\MapaMental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MapaMentalApiTest extends TestCase
{
    use RefreshDatabase;

    private function criarMapa(User $user, string $nome = 'Meu mapa'): MapaMental
    {
        $arquivo = Arquivo::create([
            'user_id'    => $user->id,
            'nome'       => $nome,
            'tipo'       => 'mapa',
            'tamanho_kb' => 1,
        ]);

        return $arquivo->mapaMental()->create();
    }

    private function dadosMapa(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Mapa de BD',
            'nos'  => [
                ['ref' => 1, 'parent_ref' => null, 'label' => 'Banco de Dados', 'x' => 100, 'y' => 50, 'cor' => '#ffffff'],
                ['ref' => 2, 'parent_ref' => 1, 'label' => 'Modelagem', 'x' => 200.6, 'y' => 120, 'cor' => null],
            ],
        ], $extra);
    }

    public function test_cria_mapa_valido_retorna_201(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $resposta = $this->postJson('/api/mapas-mentais', $this->dadosMapa())
            ->assertStatus(201)
            ->assertJsonPath('nome', 'Mapa de BD')
            ->assertJsonCount(2, 'nos');

        $this->assertDatabaseHas('arquivos', [
            'nome'    => 'Mapa de BD',
            'tipo'    => 'mapa',
            'user_id' => $user->id,
        ]);

        // o nó filho fica ligado ao nó pai, e as coordenadas são arredondadas
        $nos = collect($resposta->json('nos'));
        $pai = $nos->firstWhere('label', 'Banco de Dados');
        $filho = $nos->firstWhere('label', 'Modelagem');

        $this->assertNull($pai['parent_id']);
        $this->assertEquals($pai['id'], $filho['parent_id']);
        $this->assertEquals(201, $filho['x']);
    }

    public function test_mapa_sem_nome_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/mapas-mentais', $this->dadosMapa(['nome' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nome');
    }

    public function test_no_sem_label_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/mapas-mentais', $this->dadosMapa([
            'nos' => [['ref' => 1, 'label' => '', 'x' => 10, 'y' => 10]],
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nos.0.label');
    }

    public function test_lista_apenas_os_mapas_do_usuario_logado(): void
    {
        $user = User::factory()->create();
        $this->criarMapa($user, 'Meu mapa');
        $this->criarMapa(User::factory()->create(), 'Mapa de outra pessoa');

        Sanctum::actingAs($user);

        $this->getJson('/api/mapas-mentais')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nome', 'Meu mapa');
    }

    public function test_mostra_mapa_proprio(): void
    {
        $user = User::factory()->create();
        $mapa = $this->criarMapa($user, 'Meu mapa');

        Sanctum::actingAs($user);

        $this->getJson('/api/mapas-mentais/' . $mapa->id)
            ->assertOk()
            ->assertJsonPath('nome', 'Meu mapa');
    }

    public function test_nao_mostra_mapa_de_outro_usuario(): void
    {
        $mapa = $this->criarMapa(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/mapas-mentais/' . $mapa->id)->assertStatus(404);
    }

    public function test_atualiza_nome_e_substitui_os_nos(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/mapas-mentais', $this->dadosMapa())->json('id');

        $this->putJson('/api/mapas-mentais/' . $id, [
            'nome' => 'Nome novo',
            'nos'  => [['ref' => 1, 'label' => 'Só um nó', 'x' => 10, 'y' => 10]],
        ])
            ->assertOk()
            ->assertJsonPath('nome', 'Nome novo')
            ->assertJsonCount(1, 'nos')
            ->assertJsonPath('nos.0.label', 'Só um nó');
    }

    public function test_nao_atualiza_mapa_de_outro_usuario(): void
    {
        $mapa = $this->criarMapa(User::factory()->create(), 'Nome original');

        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/mapas-mentais/' . $mapa->id, ['nome' => 'Invasão'])
            ->assertStatus(404);

        $this->assertDatabaseHas('arquivos', [
            'id'   => $mapa->arquivo_id,
            'nome' => 'Nome original',
        ]);
    }

    public function test_apaga_mapa_e_o_arquivo_junto(): void
    {
        $user = User::factory()->create();
        $mapa = $this->criarMapa($user);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/mapas-mentais/' . $mapa->id)->assertNoContent();

        $this->assertDatabaseMissing('arquivos', ['id' => $mapa->arquivo_id]);
        $this->assertModelMissing($mapa);
    }

    public function test_nao_apaga_mapa_de_outro_usuario(): void
    {
        $mapa = $this->criarMapa(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/mapas-mentais/' . $mapa->id)->assertStatus(404);

        $this->assertModelExists($mapa);
    }

    public function test_mapa_com_nos_demais_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $nos = [];
        for ($i = 1; $i <= 201; $i++) { // 1 acima do limite de 200
            $nos[] = ['ref' => $i, 'parent_ref' => null, 'label' => 'Nó ' . $i, 'x' => 10, 'y' => 10];
        }

        $this->postJson('/api/mapas-mentais', ['nome' => 'Mapa enorme', 'nos' => $nos])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nos');

        $this->assertDatabaseCount('mapas_mentais', 0);
    }
}
