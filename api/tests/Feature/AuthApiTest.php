<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function dadosCadastro(array $extra = []): array
    {
        return array_merge([
            'name' => 'Ana Teste',
            'email' => 'Ana@Teste.com',
            'password' => 'senha12345',
            'password_confirmation' => 'senha12345',
        ], $extra);
    }

    public function test_cadastro_valido_retorna_201_e_token(): void
    {
        $this->postJson('/api/register', $this->dadosCadastro())
            ->assertStatus(201)
            ->assertJsonStructure(['user', 'token']);

        // o e-mail é salvo em minúsculas
        $this->assertDatabaseHas('users', ['email' => 'ana@teste.com']);
    }

    public function test_cadastro_com_senhas_diferentes_retorna_422(): void
    {
        $this->postJson('/api/register', $this->dadosCadastro([
            'password_confirmation' => 'outra-senha',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_cadastro_com_senha_curta_retorna_422(): void
    {
        $this->postJson('/api/register', $this->dadosCadastro([
            'password' => '123',
            'password_confirmation' => '123',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_cadastro_com_email_repetido_retorna_422(): void
    {
        User::factory()->create(['email' => 'ana@teste.com']);

        $this->postJson('/api/register', $this->dadosCadastro())
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_correto_retorna_token(): void
    {
        User::factory()->create([
            'email' => 'ana@teste.com',
            'password' => Hash::make('senha12345'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'ana@teste.com',
            'password' => 'senha12345',
        ])
            ->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_com_senha_errada_retorna_422(): void
    {
        User::factory()->create([
            'email' => 'ana@teste.com',
            'password' => Hash::make('senha12345'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'ana@teste.com',
            'password' => 'senha-errada',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_logout_apaga_o_token(): void
    {
        $token = $this->postJson('/api/register', $this->dadosCadastro())->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_token_do_cadastro_acessa_rota_protegida(): void
    {
        $token = $this->postJson('/api/register', $this->dadosCadastro())->json('token');

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'ana@teste.com');
    }

    public function test_token_dentro_da_validade_funciona(): void
    {
        $token = User::factory()->create()->createToken('estudaflow')->plainTextToken;

        $this->travel(6)->days(); // validade é de 7 dias

        $this->withToken($token)->getJson('/api/user')->assertOk();
    }

    public function test_token_expirado_retorna_401(): void
    {
        $token = User::factory()->create()->createToken('estudaflow')->plainTextToken;

        $this->travel(8)->days(); // passou dos 7 dias

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_cadastro_nao_permite_virar_administrador(): void
    {
        $this->postJson('/api/register', $this->dadosCadastro(['is_admin' => true]))
            ->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email'    => 'ana@teste.com',
            'is_admin' => false,
        ]);
    }
}
