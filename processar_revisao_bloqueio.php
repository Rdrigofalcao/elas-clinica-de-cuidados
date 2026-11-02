<?php
session_start();

require_once __DIR__ . "/config/conexao.php";

date_default_timezone_set("America/Bahia");

// Verifica se o funcionário está autenticado.
if (!isset($_SESSION["funcionario_id"])) {
    header("Location: login_admin.php");
    exit;
}

// Permite apenas requisições POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: gerenciar_clientes.php");
    exit;
}

// Protege a exibição de textos no HTML.
function escapar($valor) {
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Dados enviados pelo formulário.
$funcionarioId = (int)$_SESSION["funcionario_id"];

$clienteId = filter_input(
    INPUT_POST,
    "cliente",
    FILTER_VALIDATE_INT
);

$justificativa = trim(
    (string)($_POST["justificativa"] ?? "")
);

$token = $_POST["csrf_token"] ?? "";

$mensagem = "";
$sucesso = false;

// Validação do token de segurança.
if (
    !is_string($token) ||
    !isset($_SESSION["csrf_revisao"]) ||
    !hash_equals($_SESSION["csrf_revisao"], $token)
) {

    $mensagem = "Solicitação inválida. Atualize a página.";

} elseif (!$clienteId) {

    $mensagem = "Cliente inválido.";

} elseif (
    mb_strlen($justificativa, "UTF-8") < 10 ||
    mb_strlen($justificativa, "UTF-8") > 2000
) {

    $mensagem =
        "A justificativa deve ter entre 10 e 2000 caracteres.";

} else {

    try {

        // Inicia uma transação no banco de dados.
        $conexao->beginTransaction();

        // Verifica se o funcionário existe e está ativo.
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
            throw new Exception(
                "Funcionário sem autorização."
            );
        }

        // Bloqueia temporariamente o registro do cliente
        // para evitar alterações simultâneas.
        $sql = "
            SELECT
                id_cliente,
                nome,
                agendamento_bloqueado
            FROM clientes
            WHERE id_cliente = :cliente
            FOR UPDATE
        ";

        $consulta = $conexao->prepare($sql);

        $consulta->execute([
            "cliente" => $clienteId
        ]);

        $cliente = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            throw new Exception(
                "Cliente não encontrado."
            );
        }

        // O desbloqueio só pode acontecer
        // se o cliente estiver bloqueado.
        if ((int)$cliente["agendamento_bloqueado"] !== 1) {
            throw new Exception(
                "Este cliente não possui bloqueio ativo."
            );
        }

        // Identifica a última ocorrência registrada
        // para o cliente antes da revisão.
        $sql = "
            SELECT COALESCE(MAX(id_ocorrencia), 0)
            FROM ocorrencias
            WHERE id_cliente = :cliente
        ";

        $consulta = $conexao->prepare($sql);

        $consulta->execute([
            "cliente" => $clienteId
        ]);

        $ultimaOcorrencia = (int)$consulta->fetchColumn();

        // Registra a revisão administrativa
        // e o limite do ciclo anterior.
        $sql = "
            INSERT INTO revisoes_bloqueio (
                id_cliente,
                id_funcionario,
                justificativa,
                faltas_justificadas,
                id_ultima_ocorrencia
            ) VALUES (
                :cliente,
                :funcionario,
                :justificativa,
                0,
                :ultima_ocorrencia
            )
        ";

        $revisao = $conexao->prepare($sql);

        $revisao->execute([
            "cliente" => $clienteId,
            "funcionario" => $funcionarioId,
            "justificativa" => $justificativa,
            "ultima_ocorrencia" => $ultimaOcorrencia
        ]);

        // Recupera o ID da revisão criada.
        $idRevisao = (int)$conexao->lastInsertId();

        if ($idRevisao <= 0) {
            throw new Exception(
                "Não foi possível identificar a revisão."
            );
        }

        // Desbloqueia o cliente e vincula
        // a revisão ao cadastro dele.
        $sql = "
            UPDATE clientes
            SET
                agendamento_bloqueado = 0,
                id_ultima_revisao_bloqueio = :revisao
            WHERE id_cliente = :cliente
              AND agendamento_bloqueado = 1
        ";

        $atualizacao = $conexao->prepare($sql);

        $atualizacao->execute([
            "revisao" => $idRevisao,
            "cliente" => $clienteId
        ]);

        if ($atualizacao->rowCount() !== 1) {
            throw new Exception(
                "Não foi possível desbloquear o cliente."
            );
        }

        // Confirma todas as alterações.
        $conexao->commit();

        // Invalida o token usado no formulário.
        unset($_SESSION["csrf_revisao"]);

        $sucesso = true;

        $mensagem =
            "Cliente desbloqueado com sucesso! " .
            "A revisão administrativa foi registrada " .
            "e um novo ciclo de faltas foi iniciado.";

    } catch (Throwable $erro) {

        // Desfaz as alterações caso ocorra um erro.
        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }

        if ($erro instanceof PDOException) {

            error_log($erro->getMessage());

            $mensagem =
                "Não foi possível concluir a revisão.";

        } else {

            $mensagem = $erro->getMessage();

        }
    }
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

    <title>Resultado da Revisão - Elas</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >
</head>

<body>

<header>
    <h2>ELAS</h2>
    <p>Clínica de Cuidados</p>
</header>

<main class="pagina-cadastro">

    <h2>Resultado da Revisão Administrativa</h2>

    <div class="mensagem <?php
        echo $sucesso ? "sucesso" : "erro";
    ?>">
        <?php echo escapar($mensagem); ?>
    </div>

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
