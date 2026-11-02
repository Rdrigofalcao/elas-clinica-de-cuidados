<?php

// Exemplo de configuração do banco de dados.
// Copie este arquivo para conexao.php e ajuste os dados.

$servidor = "127.0.0.1";
$porta = 3306;
$banco = "elas_clinica";
$usuario = "SEU_USUARIO";
$senha = "SUA_SENHA";

try {
    $conexao = new PDO(
        "mysql:host=$servidor;port=$porta;dbname=$banco;charset=utf8mb4",
        $usuario,
        $senha
    );

    $conexao->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $erro) {
    error_log($erro->getMessage());
    die("Não foi possível conectar ao banco de dados.");
}
