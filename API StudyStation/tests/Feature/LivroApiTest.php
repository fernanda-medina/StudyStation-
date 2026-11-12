<?php

namespace Tests\Feature;

use App\Models\Livro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LivroApiTest extends TestCase
{
    use RefreshDatabase;

    private function dadosLivro(array $extra = []): array
    {
        return array_merge([
            'titulo' => 'Clean Code',
            'autor'  => 'Robert C. Martin',
            'area'   => 'computacao',
            'icon'   => '📘',
            'tags'   => ['programacao', 'boas praticas'],
        ], $extra);
    }

    private function criarLivro(string $titulo = 'Livro de Teste'): Livro
    {
        return Livro::create([
            'titulo' => $titulo,
            'autor'  => 'Autor Teste',
            'area'   => 'computacao',
            'icon'   => '📘',
        ]);
    }

    public function test_cria_livro_valido_retorna_201(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/livros', $this->dadosLivro())
            ->assertStatus(201)
            ->assertJsonPath('titulo', 'Clean Code')
            ->assertJsonCount(2, 'tags');

        $this->assertDatabaseHas('livros', ['titulo' => 'Clean Code']);
    }

    public function test_livro_sem_titulo_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/livros', $this->dadosLivro(['titulo' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('titulo');
    }

    public function test_area_invalida_retorna_422(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/livros', $this->dadosLivro(['area' => 'culinaria']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('area');
    }

    public function test_lista_todos_os_livros(): void
    {
        $this->criarLivro('Livro A');
        $this->criarLivro('Livro B');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/livros')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_mostra_um_livro(): void
    {
        $livro = $this->criarLivro('Livro A');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/livros/' . $livro->id)
            ->assertOk()
            ->assertJsonPath('titulo', 'Livro A')
            ->assertJsonPath('na_lista', false);
    }

    public function test_atualiza_livro(): void
    {
        $livro = $this->criarLivro('Livro A');

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/livros/' . $livro->id, ['titulo' => 'Título novo'])
            ->assertOk()
            ->assertJsonPath('titulo', 'Título novo');
    }

    public function test_apaga_livro(): void
    {
        $livro = $this->criarLivro();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson('/api/livros/' . $livro->id)->assertNoContent();

        $this->assertDatabaseMissing('livros', ['id' => $livro->id]);
    }

    public function test_adiciona_livro_na_lista_de_leitura(): void
    {
        $user = User::factory()->create();
        $livro = $this->criarLivro();

        Sanctum::actingAs($user);

        $this->postJson('/api/livros/' . $livro->id . '/lista')->assertStatus(201);

        $this->assertDatabaseHas('lista_leitura', [
            'user_id'  => $user->id,
            'livro_id' => $livro->id,
        ]);

        $this->getJson('/api/livros/' . $livro->id)
            ->assertJsonPath('na_lista', true);
    }

    public function test_lista_de_leitura_mostra_so_os_livros_do_usuario(): void
    {
        $user = User::factory()->create();
        $livroDele = $this->criarLivro('Meu livro');
        $livroDeOutro = $this->criarLivro('Livro de outra pessoa');

        $livroDele->leitores()->attach($user->id);
        $livroDeOutro->leitores()->attach(User::factory()->create()->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/lista-leitura')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.titulo', 'Meu livro');
    }

    public function test_remove_livro_da_lista_de_leitura(): void
    {
        $user = User::factory()->create();
        $livro = $this->criarLivro();
        $livro->leitores()->attach($user->id);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/livros/' . $livro->id . '/lista')->assertNoContent();

        $this->assertDatabaseMissing('lista_leitura', [
            'user_id'  => $user->id,
            'livro_id' => $livro->id,
        ]);
    }

    public function test_usuario_comum_nao_cria_livro(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/livros', $this->dadosLivro())->assertForbidden();

        $this->assertDatabaseCount('livros', 0);
    }

    public function test_usuario_comum_nao_atualiza_livro(): void
    {
        $livro = $this->criarLivro('Livro A');

        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/livros/' . $livro->id, ['titulo' => 'Título hackeado'])
            ->assertForbidden();

        $this->assertDatabaseHas('livros', ['id' => $livro->id, 'titulo' => 'Livro A']);
    }

    public function test_usuario_comum_nao_apaga_livro(): void
    {
        $livro = $this->criarLivro();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/livros/' . $livro->id)->assertForbidden();

        $this->assertDatabaseHas('livros', ['id' => $livro->id]);
    }

    public function test_visitante_sem_login_nao_cria_livro(): void
    {
        $this->postJson('/api/livros', $this->dadosLivro())->assertUnauthorized();

        $this->assertDatabaseCount('livros', 0);
    }
}
