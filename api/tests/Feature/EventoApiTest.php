<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventoApiTest extends TestCase
{
    use RefreshDatabase;

    private function dadosEvento(array $extra = []): array
    {
        return array_merge([
            'titulo' => 'Prova de Banco de Dados',
            'data' => '2026-11-20',
            'hora' => '14:30',
            'tipo' => 'prova',
        ], $extra);
    }

    public function test_cria_evento_valido_retorna_201(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/eventos', $this->dadosEvento())
            ->assertStatus(201)
            ->assertJsonPath('titulo', 'Prova de Banco de Dados');

        $this->assertDatabaseHas('eventos', [
            'titulo' => 'Prova de Banco de Dados',
            'user_id' => $user->id,
        ]);
    }

    public function test_evento_sem_titulo_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/eventos', $this->dadosEvento(['titulo' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('titulo');
    }

    public function test_tipo_invalido_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/eventos', $this->dadosEvento(['tipo' => 'festa']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipo');
    }

    public function test_hora_em_formato_invalido_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/eventos', $this->dadosEvento(['hora' => '25:99']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('hora');
    }

    public function test_lista_apenas_os_eventos_do_usuario_logado(): void
    {
        $user = User::factory()->create();
        $outro = User::factory()->create();

        $user->eventos()->create($this->dadosEvento());
        $outro->eventos()->create($this->dadosEvento(['titulo' => 'Evento de outra pessoa']));

        Sanctum::actingAs($user);

        $this->getJson('/api/eventos')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_atualiza_evento(): void
    {
        $user = User::factory()->create();
        $evento = $user->eventos()->create($this->dadosEvento());

        Sanctum::actingAs($user);

        $this->putJson('/api/eventos/' . $evento->id, ['titulo' => 'Título novo'])
            ->assertOk()
            ->assertJsonPath('titulo', 'Título novo');
    }

    public function test_apaga_evento(): void
    {
        $user = User::factory()->create();
        $evento = $user->eventos()->create($this->dadosEvento());

        Sanctum::actingAs($user);

        $this->deleteJson('/api/eventos/' . $evento->id)->assertOk();

        $this->assertDatabaseMissing('eventos', ['id' => $evento->id]);
    }

    public function test_nao_acessa_evento_de_outro_usuario(): void
    {
        $dono = User::factory()->create();
        $evento = $dono->eventos()->create($this->dadosEvento());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/eventos/' . $evento->id)->assertStatus(404);
    }
}