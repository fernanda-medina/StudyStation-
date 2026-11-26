<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Testes de integração/contrato da API REST de Metas.
 * Validam status code, estrutura do JSON e tempo de resposta.
 */
class MetaContratoApiTest extends TestCase
{
    use RefreshDatabase;

    private const LIMITE_MS = 500;

    private function medir(callable $requisicao): array
    {
        $inicio = microtime(true);
        $resposta = $requisicao();
        $ms = (microtime(true) - $inicio) * 1000;

        return [$resposta, $ms];
    }

    public function test_post_metas_retorna_201_com_payload_correto(): void
    {
        // Arrange
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $prazo = now()->addDays(7)->toDateString();

        // Act
        [$response, $ms] = $this->medir(fn () => $this->postJson('/api/metas', [
            'nome'       => 'Revisar Pirâmide de Testes',
            'disciplina' => 'Garantia de Qualidade',
            'prazo'      => $prazo,
            'prioridade' => 'urgente',
        ]));

        // Assert – status, contrato JSON e desempenho
        $response->assertStatus(201)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['id', 'user_id', 'nome', 'disciplina', 'prazo', 'prioridade', 'done', 'created_at'])
            ->assertJsonPath('nome', 'Revisar Pirâmide de Testes')
            ->assertJsonPath('prioridade', 'urgente')
            ->assertJsonPath('user_id', $user->id);
        $this->assertLessThan(self::LIMITE_MS, $ms, "POST /api/metas levou {$ms} ms");
    }

    public function test_post_metas_com_prazo_no_passado_retorna_422(): void
    {
        // Arrange
        Sanctum::actingAs(User::factory()->create());

        // Act
        [$response, $ms] = $this->medir(fn () => $this->postJson('/api/metas', [
            'nome'       => 'Meta atrasada',
            'disciplina' => 'QA',
            'prazo'      => '2020-01-01',
        ]));

        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['prazo']])
            ->assertJsonMissingValidationErrors(['nome', 'disciplina']);
        $this->assertDatabaseCount('metas', 0);
        $this->assertLessThan(self::LIMITE_MS, $ms);
    }

    public function test_get_meta_de_outro_usuario_retorna_404(): void
    {
        // Arrange – a meta pertence ao Bruno
        $bruno = User::factory()->create();
        $metaDoBruno = $bruno->metas()->create([
            'nome' => 'Meta privada', 'disciplina' => 'Cálculo', 'prazo' => now()->addDay()->toDateString(),
        ]);
        Sanctum::actingAs(User::factory()->create()); // logada como Ana

        // Act
        [$response, $ms] = $this->medir(fn () => $this->getJson("/api/metas/{$metaDoBruno->id}"));

        // Assert – não pode vazar dado de outro usuário (IDOR)
        $response->assertNotFound()
            ->assertJsonStructure(['message'])
            ->assertJsonMissing(['nome' => 'Meta privada']);
        $this->assertLessThan(self::LIMITE_MS, $ms);
    }
}
