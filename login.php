<?php
session_start();

require_once __DIR__ . "/config/conexao.php";

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if ($email === "" || $senha === "") {
        $mensagem = "Preencha o e-mail e a senha.";

    } else {

        $sql = "SELECT id_cliente, nome, senha
                FROM clientes
                WHERE email = :email";

        $consulta = $conexao->prepare($sql);
        $consulta->execute([
            "email" => $email
        ]);

        $cliente = $consulta->fetch(PDO::FETCH_ASSOC);

        if ($cliente && password_verify($senha, $cliente["senha"])) {

            session_regenerate_id(true);

            $_SESSION["cliente_id"] = $cliente["id_cliente"];
            $_SESSION["cliente_nome"] = $cliente["nome"];

            header("Location: area_cliente.php");
            exit;

        } else {
            $mensagem = "E-mail ou senha incorretos.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Login - Elas Clínica de Cuidados</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header>
    <h2>ELAS</h2>
    <p>Clínica de Cuidados</p>
</header>

<main class="pagina-cadastro">

    <h2>Entrar na minha conta</h2>

    <p>Acesse sua área de cliente.</p>

    <?php if ($mensagem !== ""): ?>
        <div class="mensagem erro">
            <?php echo htmlspecialchars(
                $mensagem,
                ENT_QUOTES,
                "UTF-8"
            ); ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST">

        <label for="email">E-mail:</label>

        <input
            type="email"
            id="email"
            name="email"
            required
        >

        <label for="senha">Senha:</label>

        <input
            type="password"
            id="senha"
            name="senha"
            required
        >

        <button type="submit">Entrar</button>

    </form>

    <p>
        Ainda não tem uma conta?
        <a href="cadastro.php">Cadastre-se</a>
    </p>

    <p>
        <a href="index.php">Voltar ao início</a>
    </p>

</main>

</body>
</html>
