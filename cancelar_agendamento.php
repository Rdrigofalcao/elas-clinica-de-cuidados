<?php
session_start();

require_once __DIR__ . "/config/conexao.php";

date_default_timezone_set("America/Bahia");

if (!isset($_SESSION["cliente_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: meus_agendamentos.php");
    exit;
}

function escapar($valor) {
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

$clienteId = (int)$_SESSION["cliente_id"];

$agendamentoId = filter_input(
    INPUT_POST,
    "agendamento_id",
    FILTER_VALIDATE_INT
);

$token = $_POST["csrf_token"] ?? "";

$mensagem = "";
$sucesso = false;

if (
    !is_string($token) ||
    !isset($_SESSION["csrf_cancelamento"]) ||
    !hash_equals($_SESSION["csrf_cancelamento"], $token)
) {
    $mensagem = "Solicitação inválida. Atualize a página e tente novamente.";

} elseif (!$agendamentoId) {
    $mensagem = "Agendamento inválido.";

} else {

    try {
        $conexao->beginTransaction();

        // Busca o agendamento do cliente e bloqueia
        // o registro durante a operação.
        $sql = "
            SELECT
                id_agendamento,
                data_agendamento,
                hora_inicio,
                status
            FROM agendamentos
            WHERE id_agendamento = :agendamento
              AND id_cliente = :cliente
            FOR UPDATE
        ";

        $consulta = $conexao->prepare($sql);

        $consulta->execute([
            "agendamento" => $agendamentoId,
            "cliente" => $clienteId
        ]);

        $agendamento = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$agendamento) {
            throw new Exception(
                "Agendamento não encontrado."
            );
        }

        if ($agendamento["status"] !== "agendado") {
            throw new Exception(
                "Este agendamento não pode ser cancelado."
            );
        }

        $fuso = new DateTimeZone("America/Bahia");

        $inicioAtendimento = new DateTimeImmutable(
            $agendamento["data_agendamento"] . " " .
            $agendamento["hora_inicio"],
            $fuso
        );

        $agora = new DateTimeImmutable("now", $fuso);

        $antecedencia = $inicioAtendimento->getTimestamp()
            - $agora->getTimestamp();

        // 24 horas = 86.400 segundos.
        if ($antecedencia < 86400) {
            throw new Exception(
                "O cancelamento exige antecedência mínima de 24 horas."
            );
        }

        // Atualiza o status do agendamento.
        $sql = "
            UPDATE agendamentos
            SET status = 'cancelado'
            WHERE id_agendamento = :agendamento
              AND id_cliente = :cliente
              AND status = 'agendado'
        ";

        $atualizacao = $conexao->prepare($sql);

        $atualizacao->execute([
            "agendamento" => $agendamentoId,
            "cliente" => $clienteId
        ]);

        if ($atualizacao->rowCount() !== 1) {
            throw new Exception(
                "Não foi possível cancelar o agendamento."
            );
        }

        // Registra o cancelamento no histórico.
        $sql = "
            INSERT INTO historico_agendamentos (
                id_agendamento,
                id_funcionario,
                acao,
                descricao
            ) VALUES (
                :agendamento,
                NULL,
                'cancelamento_cliente',
                'Agendamento cancelado pelo cliente.'
            )
        ";

        $historico = $conexao->prepare($sql);

        $historico->execute([
            "agendamento" => $agendamentoId
        ]);

        $conexao->commit();

        // Invalida o token utilizado.
        unset($_SESSION["csrf_cancelamento"]);

        $sucesso = true;
        $mensagem = "Agendamento cancelado com sucesso!";

    } catch (Exception $erro) {

        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }

        if ($erro instanceof PDOException) {
            error_log($erro->getMessage());
            $mensagem = "Não foi possível processar o cancelamento.";
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
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Cancelamento - Elas Clínica de Cuidados</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header>
    <h2>ELAS</h2>
    <p>Clínica de Cuidados</p>
</header>

<main class="pagina-cadastro">

    <h2>Cancelamento de Agendamento</h2>

    <div class="mensagem <?php
        echo $sucesso ? "sucesso" : "erro";
    ?>">
        <?php echo escapar($mensagem); ?>
    </div>

    <p>
        <a href="meus_agendamentos.php">
            Voltar para meus agendamentos
        </a>
    </p>

</main>

</body>
</html>
