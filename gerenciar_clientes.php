<?php
session_start();

require_once __DIR__ . "/config/conexao.php";

if (!isset($_SESSION["funcionario_id"])) {
    header("Location: login_admin.php");
    exit;
}

function escapar($valor) {
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

$funcionarioId = (int)$_SESSION["funcionario_id"];

// Verifica se o funcionário está ativo.
$sql = "
    SELECT id_funcionario
    FROM funcionarios
    WHERE id_funcionario = :funcionario
      AND ativo = 1
    LIMIT 1
";

$consulta = $conexao->prepare($sql);

$consulta->execute([
    "funcionario" => $funcionarioId
]);

if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
    header("Location: logout_admin.php");
    exit;
}

// Consulta os clientes e suas faltas injustificadas.
$sql = "
    SELECT
        c.id_cliente,
        c.nome,
        c.email,
        c.telefone,
        c.agendamento_bloqueado,
        COUNT(o.id_ocorrencia) AS total_faltas
    FROM clientes c
    LEFT JOIN ocorrencias o
        ON o.id_cliente = c.id_cliente
       AND o.tipo = 'falta_injustificada'
    GROUP BY
        c.id_cliente,
        c.nome,
        c.email,
        c.telefone,
        c.agendamento_bloqueado
    ORDER BY
        c.agendamento_bloqueado DESC,
        c.nome ASC
";

$consulta = $conexao->query($sql);

$clientes = $consulta->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Gerenciar Clientes - Elas</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header>
    <h2>ELAS</h2>
    <p>Clínica de Cuidados</p>
</header>

<nav>
    <a href="painel_admin.php">Painel Administrativo</a>
    <a href="gerenciar_agendamentos.php">Agendamentos</a>
    <a href="logout_admin.php">Sair</a>
</nav>

<main class="pagina-cadastro">

    <h2>Gerenciamento de Clientes</h2>

    <p>
        Consulte as faltas injustificadas,
        a situação dos clientes e as revisões
        administrativas.
    </p>

    <?php if (empty($clientes)): ?>

        <p>Nenhum cliente cadastrado.</p>

    <?php else: ?>

        <div style="overflow-x: auto;">

            <table
                border="1"
                cellpadding="10"
                cellspacing="0"
                width="100%"
            >

                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Faltas</th>
                        <th>Situação</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($clientes as $cliente): ?>

                    <tr>

                        <td>
                            <?php echo escapar($cliente["nome"]); ?>
                        </td>

                        <td>
                            <?php echo escapar($cliente["email"]); ?>
                        </td>

                        <td>
                            <?php echo escapar($cliente["telefone"]); ?>
                        </td>

                        <td>
                            <?php echo (int)$cliente["total_faltas"]; ?>
                        </td>

                        <td>

                            <?php if (
                                (int)$cliente["agendamento_bloqueado"] === 1
                            ): ?>

                                <strong style="color: #b42318;">
                                    Bloqueado
                                </strong>

                            <?php else: ?>

                                <strong style="color: #16703c;">
                                    Liberado
                                </strong>

                            <?php endif; ?>

                        </td>

                        <td>
                            <a href="revisar_bloqueio.php?cliente=<?php
                                echo (int)$cliente["id_cliente"];
                            ?>">
                                Ver Ocorrências / Revisar
                            </a>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

    <p>
        <a href="painel_admin.php">
            Voltar ao painel administrativo
        </a>
    </p>

</main>

<footer>
    <p>© 2026 Elas Clínica de Cuidados</p>
</footer>

</body>
</html>
