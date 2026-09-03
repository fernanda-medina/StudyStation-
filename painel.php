<?php
session_start();

// Protege a página: só acessa quem estiver logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel</title>
</head>
<body>
    <h2>Bem-vindo(a), <?= htmlspecialchars($_SESSION['usuario_nome']) ?>!</h2>
    <p>Você está logado com sucesso.</p>
    <p><a href="logout.php">Sair</a></p>
</body>
</html>
