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

$consulta = $conexao->prepare("
    SELECT id_funcionario
    FROM funcionarios
    WHERE id_funcionario = :id
      AND ativo = 1
    LIMIT 1
");

$consulta->execute(["id" => $funcionarioId]);

if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
    header("Location: logout_admin.php");
    exit;
}

$clienteId = filter_input(
    INPUT_GET,
    "cliente",
    FILTER_VALIDATE_INT
);

if (!$clienteId) {
    header("Location: gerenciar_clientes.php");
    exit;
}

$consulta = $conexao->prepare("
    SELECT
        id_cliente,
        nome,
        email,
        agendamento_bloqueado
    FROM clientes
    WHERE id_cliente = :cliente
    LIMIT 1
");

$consulta->execute(["cliente" => $clienteId]);

$cliente = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    http_response_code(404);
    exit("Cliente não encontrado.");
}

$consulta = $conexao->prepare("
    SELECT
        id_ocorrencia,
        id_agendamento,
        tipo,
        descricao,
        data_ocorrencia
    FROM ocorrencias
    WHERE id_cliente = :cliente
    ORDER BY data_ocorrencia DESC, id_ocorrencia DESC
");

$consulta->execute(["cliente" => $clienteId]);

$ocorrencias = $consulta->fetchAll(PDO::FETCH_ASSOC);

$totalFaltas = 0;

foreach ($ocorrencias as $ocorrencia) {
    if ($ocorrencia["tipo"] === "falta_injustificada") {
        $totalFaltas++;
    }
}

if (empty($_SESSION["csrf_revisao"])) {
    $_SESSION["csrf_revisao"] = bin2hex(
        random_bytes(32)
    );
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Revisão de Bloqueio - Elas</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header>
    <h2>ELAS</h2>
    <p>Clínica de Cuidados</p>
</header>

<nav>
    <a href="painel_admin.php">Painel</a>
    <a href="gerenciar_clientes.php">Clientes</a>
    <a href="logout_admin.php">Sair</a>
</nav>

<main class="pagina-cadastro">

    <h2>Revisão Administrativa</h2>

    <p>
        <strong>Cliente:</strong>
        <?php echo escapar($cliente["nome"]); ?>
    </p>

    <p>
        <strong>E-mail:</strong>
        <?php echo escapar($cliente["email"]); ?>
    </p>

    <p>
        <strong>Faltas injustificadas:</strong>
        <?php echo $totalFaltas; ?>
    </p>

    <p>
        <strong>Situação:</strong>

        <?php if ((int)$cliente["agendamento_bloqueado"] === 1): ?>
            Bloqueado
        <?php else: ?>
            Liberado
        <?php endif; ?>
    </p>

    <h3>Histórico de Ocorrências</h3>

    <?php if (empty($ocorrencias)): ?>

        <p>Nenhuma ocorrência registrada.</p>

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
                        <th>ID</th>
                        <th>Agendamento</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Data</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($ocorrencias as $ocorrencia): ?>

                        <tr>
                            <td>
                                <?php echo (int)$ocorrencia["id_ocorrencia"]; ?>
                            </td>

                            <td>
                                <?php echo (int)$ocorrencia["id_agendamento"]; ?>
                            </td>

                            <td>
                                <?php echo escapar($ocorrencia["tipo"]); ?>
                            </td>

                            <td>
                                <?php echo escapar($ocorrencia["descricao"]); ?>
                            </td>

                            <td>
                                <?php echo escapar($ocorrencia["data_ocorrencia"]); ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>

        </div>

    <?php endif; ?>

    <?php if ((int)$cliente["agendamento_bloqueado"] === 1): ?>

        <h3>Autorizar Desbloqueio</h3>

        <p>
            Informe o motivo da decisão administrativa.
            A justificativa será registrada no histórico.
        </p>

        <form
            action="processar_revisao_bloqueio.php"
            method="POST"
        >

            <input
                type="hidden"
                name="cliente"
                value="<?php echo (int)$clienteId; ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo escapar($_SESSION["csrf_revisao"]); ?>"
            >

            <label for="justificativa">
                Justificativa do desbloqueio:
            </label>

            <textarea
                name="justificativa"
                id="justificativa"
                rows="5"
                minlength="10"
                maxlength="2000"
                required
                placeholder="Descreva o motivo da liberação..."
            ></textarea>

            <button
                type="submit"
                onclick="return confirm('Confirma a autorização de desbloqueio deste cliente?');"
            >
                Autorizar Desbloqueio
            </button>

        </form>

    <?php else: ?>

        <p>
            Este cliente não possui bloqueio ativo.
        </p>

    <?php endif; ?>

    <p>
        <a href="gerenciar_clientes.php">
            Voltar ao gerenciamento de clientes
        </a>
    </p>

</main>

<footer>
    <p>© 2026 Elas Clínica de Cuidados</p>
</footer>

</body>
</html>
