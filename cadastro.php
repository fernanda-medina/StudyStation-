<?php
session_start();
require 'conexao.php';

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmar_senha) {
        $erro = 'As senhas não coincidem.';
    } else {
        // Verifica se o e-mail já está cadastrado
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $erro = 'Este e-mail já está cadastrado.';
        } else {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)');
            $stmt->execute([$nome, $email, $senha_hash]);

            $sucesso = 'Cadastro realizado com sucesso! Você já pode fazer login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro</title>
</head>
<body>
    <h2>Criar conta</h2>

    <?php if ($erro): ?>
        <p style="color:red;"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <p style="color:green;"><?= htmlspecialchars($sucesso) ?></p>
        <p><a href="login.php">Ir para o login</a></p>
    <?php endif; ?>

    <form method="POST" action="cadastro.php">

        <div>
        <input id="inp_nome" name="Nome" type="text" placeholder="Digite seu Nome" required>
        </div>

        <div>
        <input id="inp_email" name="Email" type="email" placeholder="Digite seu E-mail" required>
        </div>

        <div>
        <input id="inp_senha1" name="Senha1" type="password" placeholder="Digite sua Senha" required>
        </div>

        <div>
        <p id="aviso_senha_igual"></p>
        <input id="inp_senha2" name="Senha2" type="password" placeholder="Confirme sua Senha" required>
        </div>

        <!---->

        <script defer>

        // Verificar se senha é igual

            senha1 = document.getElementById("inp_senha1");
            senha2 = document.getElementById("inp_senha2");
            senha1.addEventListener("input", function() {

            if (senha2.value != senha1.value) {
                document.getElementById("aviso_senha_igual").innerHTML = "*A senha precisa ser a mesma.";

                } else {
                    document.getElementById("aviso_senha_igual").innerHTML = "";
            }
            });
            
            senha2.addEventListener("input", function() {

            if (senha2.value != senha1.value) {
                document.getElementById("aviso_senha_igual").innerHTML = "*A senha precisa ser a mesma.";

                } else {
                    document.getElementById("aviso_senha_igual").innerHTML = "";
            }
            });

        </script>

        <input type="checkbox">
        <p> Li e Aceito os Termos de Serviço </p>

        <button type="submit">Cadastrar</button>
    </form>

    <p>Já tenho um cadastro <a href="login.php">Faça login</a></p>
</body>
</html>
