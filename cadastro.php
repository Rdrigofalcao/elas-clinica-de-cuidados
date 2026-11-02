<?php
require_once __DIR__ . "/config/conexao.php";

$mensagem = "";
$tipoMensagem = "";
$nome = "";
$email = "";
$telefone = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nome = trim($_POST["nome"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $telefone = trim($_POST["telefone"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if ($nome === "" || $email === "" || $senha === "") {
        $mensagem = "Preencha todos os campos obrigatórios.";
        $tipoMensagem = "erro";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Digite um e-mail válido.";
        $tipoMensagem = "erro";

    } elseif (strlen($senha) < 8) {
        $mensagem = "A senha deve ter pelo menos 8 caracteres.";
        $tipoMensagem = "erro";

    } else {

        try {
            $sql = "SELECT id_cliente
                    FROM clientes
                    WHERE email = :email";

            $consulta = $conexao->prepare($sql);
            $consulta->execute([
                "email" => $email
            ]);

            if ($consulta->fetch()) {
                $mensagem = "Este e-mail já está cadastrado.";
                $tipoMensagem = "erro";

            } else {

                $senhaHash = password_hash(
                    $senha,
                    PASSWORD_DEFAULT
                );

                $sql = "INSERT INTO clientes
                        (nome, email, telefone, senha)
                        VALUES
                        (:nome, :email, :telefone, :senha)";

                $cadastro = $conexao->prepare($sql);

                $cadastro->execute([
                    "nome" => $nome,
                    "email" => $email,
                    "telefone" => $telefone,
                    "senha" => $senhaHash
                ]);

                $mensagem = "Cadastro realizado com sucesso!";
                $tipoMensagem = "sucesso";

                $nome = "";
                $email = "";
                $telefone = "";
            }

        } catch (PDOException $erro) {
            error_log($erro->getMessage());

            $mensagem = "Não foi possível concluir o cadastro.";
            $tipoMensagem = "erro";
        }
    }
}

function escapar($valor) {
    return htmlspecialchars(
        $valor,
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

    <title>Cadastro - Elas Clínica de Cuidados</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header>
    <h2>ELAS</h2>
    <p>Clínica de Cuidados</p>
</header>

<main class="pagina-cadastro">

    <h2>Cadastro de Cliente</h2>

    <p>Crie sua conta para agendar seus cuidados.</p>

    <?php if ($mensagem !== ""): ?>

        <div class="mensagem <?php echo escapar($tipoMensagem); ?>">
            <?php echo escapar($mensagem); ?>
        </div>

    <?php endif; ?>

    <form action="cadastro.php" method="POST">

        <label for="nome">Nome completo:</label>

        <input
            type="text"
            id="nome"
            name="nome"
            maxlength="150"
            value="<?php echo escapar($nome); ?>"
            required
        >

        <label for="email">E-mail:</label>

        <input
            type="email"
            id="email"
            name="email"
            maxlength="150"
            value="<?php echo escapar($email); ?>"
            required
        >

        <label for="telefone">Telefone:</label>

        <input
            type="tel"
            id="telefone"
            name="telefone"
            maxlength="20"
            value="<?php echo escapar($telefone); ?>"
        >

        <label for="senha">Senha:</label>

        <input
            type="password"
            id="senha"
            name="senha"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <button type="submit">Cadastrar</button>

    </form>

    <p>
        <a href="index.php">Voltar para o início</a>
    </p>

</main>

</body>
</html>
