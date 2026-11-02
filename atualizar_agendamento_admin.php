<?php
session_start();

require_once __DIR__ . "/config/conexao.php";

date_default_timezone_set("America/Bahia");

if (!isset($_SESSION["funcionario_id"])) {
    header("Location: login_admin.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: gerenciar_agendamentos.php");
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

$agendamentoId = filter_input(
    INPUT_POST,
    "agendamento_id",
    FILTER_VALIDATE_INT
);

$acao = $_POST["acao"] ?? "";
$token = $_POST["csrf_token"] ?? "";

$mensagem = "";
$sucesso = false;
$totalFaltas = 0;
$bloqueioAplicado = false;
$clienteJaBloqueado = false;

if (
    !is_string($token) ||
    !isset($_SESSION["csrf_admin"]) ||
    !hash_equals($_SESSION["csrf_admin"], $token)
) {
    $mensagem = "Solicitação inválida. Atualize a página.";

} elseif (!$agendamentoId) {

    $mensagem = "Agendamento inválido.";

} elseif (!in_array($acao, ["concluir", "registrar_falta"], true)) {

    $mensagem = "Ação administrativa inválida.";

} else {

    try {

        $conexao->beginTransaction();

        // Verifica se o funcionário está ativo.
        $sql = "
            SELECT id_funcionario
            FROM funcionarios
            WHERE id_funcionario = :id
              AND ativo = 1
            LIMIT 1
        ";

        $consulta = $conexao->prepare($sql);

        $consulta->execute([
            "id" => $funcionarioId
        ]);

        if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception(
                "Funcionário sem autorização."
            );
        }

        /*
         * Consulta inicialmente o cliente associado
         * ao agendamento.
         */
        $sql = "
            SELECT id_cliente
            FROM agendamentos
            WHERE id_agendamento = :id
            LIMIT 1
        ";

        $consulta = $conexao->prepare($sql);

        $consulta->execute([
            "id" => $agendamentoId
        ]);

        $clienteId = (int)$consulta->fetchColumn();

        if ($clienteId <= 0) {
            throw new Exception(
                "Agendamento não encontrado."
            );
        }

        /*
         * Bloqueia primeiro o registro do cliente.
         * Isso ajuda a coordenar operações simultâneas
         * de agendamento, revisão e registro de faltas.
         */
        $sql = "
            SELECT
                id_cliente,
                agendamento_bloqueado,
                id_ultima_revisao_bloqueio
            FROM clientes
            WHERE id_cliente = :cliente
            FOR UPDATE
        ";

        $consultaCliente = $conexao->prepare($sql);

        $consultaCliente->execute([
            "cliente" => $clienteId
        ]);

        $cliente = $consultaCliente->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            throw new Exception(
                "Cliente não encontrado."
            );
        }

        /*
         * Bloqueia o agendamento durante a alteração.
         */
        $sql = "
            SELECT
                id_agendamento,
                id_cliente,
                data_agendamento,
                hora_fim,
                status
            FROM agendamentos
            WHERE id_agendamento = :id
            FOR UPDATE
        ";

        $consulta = $conexao->prepare($sql);

        $consulta->execute([
            "id" => $agendamentoId
        ]);

        $agendamento = $consulta->fetch(PDO::FETCH_ASSOC);

        if (!$agendamento) {
            throw new Exception(
                "Agendamento não encontrado."
            );
        }

        if ((int)$agendamento["id_cliente"] !== $clienteId) {
            throw new Exception(
                "Os dados do agendamento foram alterados. Tente novamente."
            );
        }

        if ($agendamento["status"] !== "agendado") {
            throw new Exception(
                "Somente agendamentos ativos podem ser atualizados."
            );
        }

        // Verifica se o atendimento já terminou.
        $fuso = new DateTimeZone("America/Bahia");

        $fimAtendimento = new DateTimeImmutable(
            $agendamento["data_agendamento"] . " " .
            $agendamento["hora_fim"],
            $fuso
        );

        $agora = new DateTimeImmutable("now", $fuso);

        if ($agora < $fimAtendimento) {
            throw new Exception(
                "Aguarde o término previsto do atendimento."
            );
        }

        $novoStatus = $acao === "concluir"
            ? "concluido"
            : "falta";

        // Atualiza o status do atendimento.
        $sql = "
            UPDATE agendamentos
            SET status = :status
            WHERE id_agendamento = :id
              AND status = 'agendado'
        ";

        $atualizacao = $conexao->prepare($sql);

        $atualizacao->execute([
            "status" => $novoStatus,
            "id" => $agendamentoId
        ]);

        if ($atualizacao->rowCount() !== 1) {
            throw new Exception(
                "Não foi possível atualizar o atendimento."
            );
        }

        $descricao = $acao === "concluir"
            ? "Atendimento concluído pelo funcionário."
            : "Falta injustificada registrada pelo funcionário.";

        // Registra o histórico administrativo.
        $sql = "
            INSERT INTO historico_agendamentos (
                id_agendamento,
                id_funcionario,
                acao,
                descricao
            ) VALUES (
                :agendamento,
                :funcionario,
                :acao,
                :descricao
            )
        ";

        $historico = $conexao->prepare($sql);

        $historico->execute([
            "agendamento" => $agendamentoId,
            "funcionario" => $funcionarioId,
            "acao" => $acao,
            "descricao" => $descricao
        ]);

        /*
         * A contagem de faltas só acontece
         * quando a ação é registrar_falta.
         */
        if ($acao === "registrar_falta") {

            // Registra a ocorrência.
            $sql = "
                INSERT INTO ocorrencias (
                    id_cliente,
                    id_agendamento,
                    tipo,
                    descricao
                ) VALUES (
                    :cliente,
                    :agendamento,
                    'falta_injustificada',
                    'Ausência registrada pela administração.'
                )
            ";

            $ocorrencia = $conexao->prepare($sql);

            $ocorrencia->execute([
                "cliente" => $clienteId,
                "agendamento" => $agendamentoId
            ]);

            /*
             * Obtém o limite do ciclo atual.
             *
             * Se não existir revisão, o limite é 0,
             * permitindo contar todas as ocorrências.
             */
            $limiteOcorrencia = 0;

            if (!empty($cliente["id_ultima_revisao_bloqueio"])) {

                $sql = "
                    SELECT id_ultima_ocorrencia
                    FROM revisoes_bloqueio
                    WHERE id_revisao = :revisao
                      AND id_cliente = :cliente
                    LIMIT 1
                ";

                $consultaRevisao = $conexao->prepare($sql);

                $consultaRevisao->execute([
                    "revisao" =>
                        (int)$cliente["id_ultima_revisao_bloqueio"],
                    "cliente" => $clienteId
                ]);

                $resultadoRevisao =
                    $consultaRevisao->fetchColumn();

                if ($resultadoRevisao === false) {
                    throw new Exception(
                        "Não foi possível localizar a última revisão do cliente."
                    );
                }

                $limiteOcorrencia = (int)$resultadoRevisao;
            }

            /*
             * Conta somente as faltas injustificadas
             * posteriores ao limite da última revisão.
             */
            $sql = "
                SELECT COUNT(*)
                FROM ocorrencias
                WHERE id_cliente = :cliente
                  AND tipo = 'falta_injustificada'
                  AND id_ocorrencia > :limite
            ";

            $contagem = $conexao->prepare($sql);

            $contagem->bindValue(
                ":cliente",
                $clienteId,
                PDO::PARAM_INT
            );

            $contagem->bindValue(
                ":limite",
                $limiteOcorrencia,
                PDO::PARAM_INT
            );

            $contagem->execute();

            $totalFaltas = (int)$contagem->fetchColumn();

            $clienteJaBloqueado =
                (int)$cliente["agendamento_bloqueado"] === 1;

            /*
             * Aplica o bloqueio somente quando o cliente
             * atinge três faltas no ciclo atual.
             */
            if (
                $totalFaltas >= 3 &&
                !$clienteJaBloqueado
            ) {

                $sql = "
                    UPDATE clientes
                    SET agendamento_bloqueado = 1
                    WHERE id_cliente = :cliente
                      AND agendamento_bloqueado = 0
                ";

                $bloqueio = $conexao->prepare($sql);

                $bloqueio->execute([
                    "cliente" => $clienteId
                ]);

                if ($bloqueio->rowCount() !== 1) {
                    throw new Exception(
                        "Não foi possível aplicar o bloqueio."
                    );
                }

                $bloqueioAplicado = true;

                // Registra o bloqueio no histórico.
                $sql = "
                    INSERT INTO historico_agendamentos (
                        id_agendamento,
                        id_funcionario,
                        acao,
                        descricao
                    ) VALUES (
                        :agendamento,
                        :funcionario,
                        'bloqueio_cliente',
                        :descricao
                    )
                ";

                $historicoBloqueio =
                    $conexao->prepare($sql);

                $historicoBloqueio->execute([
                    "agendamento" => $agendamentoId,
                    "funcionario" => $funcionarioId,
                    "descricao" =>
                        "Cliente bloqueado após atingir " .
                        "três faltas injustificadas no ciclo atual."
                ]);
            }
        }

        // Confirma as alterações no banco.
        $conexao->commit();

        // Invalida o token utilizado.
        unset($_SESSION["csrf_admin"]);

        $sucesso = true;

        if ($acao === "concluir") {

            $mensagem =
                "Atendimento concluído com sucesso!";

        } elseif ($bloqueioAplicado) {

            $mensagem =
                "Falta registrada. O cliente atingiu " .
                "três faltas injustificadas no ciclo atual " .
                "e teve o agendamento online bloqueado.";

        } elseif ($clienteJaBloqueado) {

            $mensagem =
                "Falta registrada. O cliente já possui " .
                "bloqueio de agendamento online.";

        } else {

            $mensagem =
                "Falta registrada com sucesso! " .
                "Total de faltas injustificadas " .
                "no ciclo atual: " . $totalFaltas . ".";
        }

    } catch (Throwable $erro) {

        if ($conexao->inTransaction()) {
            $conexao->rollBack();
        }

        if ($erro instanceof PDOException) {

            error_log($erro->getMessage());

            $mensagem =
                "Erro ao atualizar o agendamento.";

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

    <title>Atualização de Atendimento - Elas</title>

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

    <h2>Atualização de Atendimento</h2>

    <div class="mensagem <?php
        echo $sucesso ? "sucesso" : "erro";
    ?>">
        <?php echo escapar($mensagem); ?>
    </div>

    <p>
        <a href="gerenciar_agendamentos.php">
            Voltar ao gerenciamento
        </a>
    </p>

</main>

<footer>
    <p>© 2026 Elas Clínica de Cuidados</p>
</footer>

</body>
</html>
