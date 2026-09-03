# Sistema de Cadastro e Login em PHP

## Como usar

1. Crie o banco de dados executando o arquivo `banco.sql` no MySQL (via phpMyAdmin, terminal ou outra ferramenta).
2. Abra `conexao.php` e ajuste `$usuario_db` e `$senha_db` com suas credenciais do MySQL.
3. Coloque todos os arquivos em uma pasta servida pelo Apache/Nginx (ou rode `php -S localhost:8000` dentro da pasta para testar localmente).
4. Acesse `cadastro.php` para criar uma conta e `login.php` para entrar.

## Arquivos

- `banco.sql` — cria o banco e a tabela `usuarios`.
- `conexao.php` — conexão PDO com o banco.
- `cadastro.php` — formulário e lógica de cadastro.
- `login.php` — formulário e lógica de login.
- `painel.php` — página protegida, só acessível após login.
- `logout.php` — encerra a sessão do usuário.

## Segurança já incluída

- Senhas armazenadas com `password_hash()` (nunca em texto puro).
- Consultas com PDO *prepared statements* (protege contra SQL Injection).
- `htmlspecialchars()` ao exibir dados vindos do usuário (protege contra XSS).
- `session_regenerate_id()` no login (protege contra session fixation).
- Validação de e-mail e tamanho mínimo de senha.

## Melhorias que você pode adicionar depois

- Confirmação de e-mail antes de ativar a conta.
- Recuperação de senha ("esqueci minha senha").
- Limite de tentativas de login (contra força bruta).
- CSRF token nos formulários.
- Usar HTTPS em produção e `session.cookie_secure = 1`.
