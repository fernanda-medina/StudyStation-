<?php
// conexao.php - Conexão com o banco de dados usando PDO

$host = 'localhost';
$dbname = 'meu_app';
$usuario_db = 'root';       // ajuste para o seu usuário do MySQL
$senha_db = '';             // ajuste para a sua senha do MySQL

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $usuario_db,
        $senha_db,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Erro na conexão com o banco de dados: ' . $e->getMessage());
}
