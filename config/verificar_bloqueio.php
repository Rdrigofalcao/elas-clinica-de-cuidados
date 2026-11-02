<?php

// Este arquivo deve ser incluído após session_start()
// e após carregar config/conexao.php.

if (!isset($_SESSION["cliente_id"])) {
    header("Location: login.php");
    exit;
}

$clienteId = (int)$_SESSION["cliente_id"];

$sql = "
    SELECT
        id_cliente,
        agendamento_bloqueado
    FROM clientes
    WHERE id_cliente = :cliente
    LIMIT 1
";

$consulta = $conexao->prepare($sql);

$consulta->execute([
    "cliente" => $clienteId
]);

$cliente = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    unset($_SESSION["cliente_id"], $_SESSION["cliente_nome"]);

    header("Location: login.php");
    exit;
}

if ((int)$cliente["agendamento_bloqueado"] === 1) {

    http_response_code(403);
    ?>

    <!DOCTYPE html>
    <html lang="pt-BR">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport"
              content="width=device-width, initial-scale=1.0">

        <title>Agendamento Suspenso - Elas</title>

        <link rel="stylesheet" href="/elas_clinica/css/style.css">
    </head>

    <body>

    <header>
        <h2>ELAS</h2>
        <p>Clínica de Cuidados</p>
    </header>

    <main class="pagina-cadastro">

        <h2>Agendamento Online Suspenso</h2>

        <p>
            Sua conta está temporariamente impedida
            de realizar novos agendamentos online.
        </p>

        <p>
            Entre em contato com a administração da
            clínica para solicitar uma revisão.
        </p>

        <p>
            <a href="/elas_clinica/area_cliente.php">
                Voltar para minha conta
            </a>
        </p>

    </main>

    </body>
    </html>

    <?php
    exit;
}
