<?php
session_start();

require_once __DIR__ . "/config/conexao.php";

if (isset($_SESSION["funcionario_id"])) {
    header("Location: painel_admin.php");
    exit;
}

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === "") {
        $mensagem = "Informe um e-mail e uma senha válidos.";
    } else {

        try {
            $sql = "
                SELECT
                    id_funcionario,
                    nome,
                    email,
                    senha,
                    cargo
                FROM funcionarios
                WHERE email = :email
                  AND ativo = 1
                LIMIT 1
            ";

            $consulta = $conexao->prepare($sql);
            $consulta->execute(["email" => $email]);

            $funcionario = $consulta->fetch(PDO::FETCH_ASSOC);

            if (
                $funcionario &&
                password_verify($senha, $funcionario["senha"])
            ) {
                session_regenerate_id(true);

                $_SESSION["funcionario_id"] =
                    (int)$funcionario["id_funcionario"];

                $_SESSION["funcionario_nome"] =
                    $funcionario["nome"];

                $_SESSION["funcionario_cargo"] =
                    $funcionario["cargo"];

                header("Location: painel_admin.php");
                exit;

            } else {
                $mensagem = "E-mail ou senha incorretos.";
            }

        } catch (PDOException $erro) {
            error_log($erro->getMessage());
            $mensagem = "Não foi possível realizar o login.";
        }
    }
}

function escapar($valor) {
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Login Administrativo - Elas</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header>
    <h2>ELAS</h2>
    <p>Clínica de Cuidados</p>
</header>

<main class="pagina-cadastro">

    <h2>Acesso Administrativo</h2>

    <p>Área exclusiva para funcionários autorizados.</p>

    <?php if ($mensagem !== ""): ?>

        <div class="mensagem erro">
            <?php echo escapar($mensagem); ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="login_admin.php">

        <label for="email">E-mail:</label>

        <input
            type="email"
            id="email"
            name="email"
            autocomplete="username"
            required
        >

        <label for="senha">Senha:</label>

        <input
            type="password"
            id="senha"
            name="senha"
            autocomplete="current-password"
            required
        >

        <button type="submit">
            Entrar no Painel Administrativo
        </button>

    </form>

    <p>
        <a href="index.php">
            Voltar para o site
        </a>
    </p>

</main>

<footer>
    <p>© 2026 Elas Clínica de Cuidados</p>
</footer>

</body>
</html>
