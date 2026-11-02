<?php
session_start();

require_once __DIR__ . "/config/conexao.php";
require_once __DIR__ . "/config/verificar_bloqueio.php";

date_default_timezone_set("America/Bahia");

function escapar($valor) {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

$mensagem = "";
$sucesso = false;
$inicio = null;
$fim = null;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: agendamento.php");
    exit;
}

if (!isset($_SESSION["cliente_id"])) {
    header("Location: login.php");
    exit;
}

$clienteId = (int) $_SESSION["cliente_id"];

$procedimentoId = filter_input(
    INPUT_POST,
    "procedimento",
    FILTER_VALIDATE_INT
);

$profissionalId = filter_input(
    INPUT_POST,
    "profissional",
    FILTER_VALIDATE_INT
);

$data = $_POST["data"] ?? "";
$horario = $_POST["horario"] ?? "";

$fuso = new DateTimeZone("America/Bahia");

$dataValida = is_string($data)
    ? DateTimeImmutable::createFromFormat(
        "!Y-m-d",
        $data,
        $fuso
    )
    : false;

$horaValida = is_string($horario)
    && preg_match(
        "/^([01][0-9]|2[0-3]):[0-5][0-9]$/",
        $horario
    );

if (
    !$procedimentoId ||
    !$profissionalId ||
    !$dataValida ||
    $dataValida->format("Y-m-d") !== $data ||
    !$horaValida
) {
    $mensagem = "Dados do agendamento inválidos.";
} else {

    $inicio = new DateTimeImmutable(
        $data . " " . $horario,
        $fuso
    );

    $agora = new DateTimeImmutable("now", $fuso);

    if ($inicio <= $agora) {
        $mensagem = "Não é possível agendar um horário passado.";

    } elseif ((int) $inicio->format("i") % 15 !== 0) {
        $mensagem = "Selecione um horário válido da agenda.";

    } else {

        try {
            $conexao->beginTransaction();

            // 1. Bloqueia o cliente durante a reserva.
            $sql = "
                SELECT id_cliente, agendamento_bloqueado
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
                throw new Exception("Cliente não encontrado.");
            }

            if ((int) $cliente["agendamento_bloqueado"] === 1) {
                throw new Exception(
                    "Seu agendamento online está suspenso. " .
                    "Entre em contato com a administração."
                );
            }

            // 2. Bloqueia o profissional durante a reserva.
            $sql = "
                SELECT id_profissional
                FROM profissionais
                WHERE id_profissional = :profissional
                  AND ativo = 1
                FOR UPDATE
            ";

            $consulta = $conexao->prepare($sql);
            $consulta->execute([
                "profissional" => $profissionalId
            ]);

            if (!$consulta->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception(
                    "Profissional indisponível."
                );
            }

            // 3. Verifica habilitação e duração.
            $sql = "
                SELECT pr.duracao_minutos
                FROM profissional_procedimento pp
                INNER JOIN procedimentos pr
                    ON pr.id_procedimento = pp.id_procedimento
                WHERE pp.id_profissional = :profissional
                  AND pp.id_procedimento = :procedimento
                  AND pr.ativo = 1
            ";

            $consulta = $conexao->prepare($sql);
            $consulta->execute([
                "profissional" => $profissionalId,
                "procedimento" => $procedimentoId
            ]);

            $procedimento = $consulta->fetch(PDO::FETCH_ASSOC);

            if (
                !$procedimento ||
                (int) $procedimento["duracao_minutos"] <= 0
            ) {
                throw new Exception(
                    "O procedimento não está disponível " .
                    "para este profissional."
                );
            }

            $duracao = (int) $procedimento["duracao_minutos"];

            $fim = $inicio->modify(
                "+{$duracao} minutes"
            );

            // 4. Verifica o expediente do profissional.
            $diaSemana = (int) $inicio->format("N");

            $sql = "
                SELECT hora_inicio, hora_fim
                FROM horarios_trabalho
                WHERE id_profissional = :profissional
                  AND dia_semana = :dia
                  AND ativo = 1
            ";

            $consulta = $conexao->prepare($sql);
            $consulta->execute([
                "profissional" => $profissionalId,
                "dia" => $diaSemana
            ]);

            $turnos = $consulta->fetchAll(PDO::FETCH_ASSOC);
            $dentroExpediente = false;

            foreach ($turnos as $turno) {

                $inicioTurno = new DateTimeImmutable(
                    $data . " " . $turno["hora_inicio"],
                    $fuso
                );

                $fimTurno = new DateTimeImmutable(
                    $data . " " . $turno["hora_fim"],
                    $fuso
                );

                if (
                    $inicio >= $inicioTurno &&
                    $fim <= $fimTurno
                ) {
                    $dentroExpediente = true;
                    break;
                }
            }

            if (!$dentroExpediente) {
                throw new Exception(
                    "O horário está fora do expediente."
                );
            }

            // 5. Verifica conflitos do profissional,
            // respeitando intervalo de 15 minutos.
            $sql = "
                SELECT COUNT(*)
                FROM agendamentos
                WHERE id_profissional = :profissional
                  AND data_agendamento = :data
                  AND status = 'agendado'
                  AND hora_inicio < :limite_fim
                  AND hora_fim > :limite_inicio
            ";

            $consulta = $conexao->prepare($sql);
            $consulta->execute([
                "profissional" => $profissionalId,
                "data" => $data,
                "limite_inicio" => $inicio
                    ->modify("-15 minutes")
                    ->format("H:i:s"),
                "limite_fim" => $fim
                    ->modify("+15 minutes")
                    ->format("H:i:s")
            ]);

            if ((int) $consulta->fetchColumn() > 0) {
                throw new Exception(
                    "Este horário acabou de ser ocupado. " .
                    "Escolha outro."
                );
            }

            // 6. NOVA REGRA:
            // Impede que o mesmo cliente tenha
            // dois atendimentos com horários sobrepostos.
            //
            // Não exige intervalo de 15 minutos
            // entre procedimentos do cliente.
            // Esse intervalo continua sendo
            // obrigatório para os profissionais.

            $sql = "
                SELECT COUNT(*)
                FROM agendamentos
                WHERE id_cliente = :cliente
                  AND data_agendamento = :data
                  AND status = 'agendado'
                  AND hora_inicio < :novo_fim
                  AND hora_fim > :novo_inicio
            ";

            $consulta = $conexao->prepare($sql);
            $consulta->execute([
                "cliente" => $clienteId,
                "data" => $data,
                "novo_inicio" => $inicio->format("H:i:s"),
                "novo_fim" => $fim->format("H:i:s")
            ]);

            if ((int) $consulta->fetchColumn() > 0) {
                throw new Exception(
                    "Você já possui outro atendimento " .
                    "agendado nesse período. " .
                    "Escolha um horário que não coincida " .
                    "com seus agendamentos existentes."
                );
            }

            // 7. Registra o agendamento.
            $sql = "
                INSERT INTO agendamentos (
                    id_cliente,
                    id_profissional,
                    id_procedimento,
                    data_agendamento,
                    hora_inicio,
                    hora_fim,
                    status
                ) VALUES (
                    :cliente,
                    :profissional,
                    :procedimento,
                    :data,
                    :inicio,
                    :fim,
                    'agendado'
                )
            ";

            $cadastro = $conexao->prepare($sql);
            $cadastro->execute([
                "cliente" => $clienteId,
                "profissional" => $profissionalId,
                "procedimento" => $procedimentoId,
                "data" => $data,
                "inicio" => $inicio->format("H:i:s"),
                "fim" => $fim->format("H:i:s")
            ]);

            $agendamentoId = $conexao->lastInsertId();

            // 8. Registra o evento no histórico.
            $sql = "
                INSERT INTO historico_agendamentos (
                    id_agendamento,
                    id_funcionario,
                    acao,
                    descricao
                ) VALUES (
                    :agendamento,
                    NULL,
                    'criacao_cliente',
                    'Agendamento criado pelo cliente.'
                )
            ";

            $historico = $conexao->prepare($sql);
            $historico->execute([
                "agendamento" => $agendamentoId
            ]);

            $conexao->commit();

            $sucesso = true;
            $mensagem = "Agendamento realizado com sucesso!";

        } catch (Exception $erro) {

            if ($conexao->inTransaction()) {
                $conexao->rollBack();
            }

            if ($erro instanceof PDOException) {
                error_log($erro->getMessage());

                $mensagem =
                    "Não foi possível concluir o agendamento.";
            } else {
                $mensagem = $erro->getMessage();
            }
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

    <title>Confirmação - Elas Clínica de Cuidados</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header>
    <h2>ELAS</h2>
    <p>Clínica de Cuidados</p>
</header>

<main class="pagina-cadastro">

    <h2>Confirmação de Agendamento</h2>

    <div class="mensagem <?php
        echo $sucesso ? "sucesso" : "erro";
    ?>">
        <?php echo escapar($mensagem); ?>
    </div>

    <?php if ($sucesso): ?>

        <p>
            <strong>Data:</strong>
            <?php echo escapar(
                $inicio->format("d/m/Y")
            ); ?>
        </p>

        <p>
            <strong>Horário:</strong>
            <?php echo escapar(
                $inicio->format("H:i")
            ); ?>
        </p>

        <p>
            <strong>Término previsto:</strong>
            <?php echo escapar(
                $fim->format("H:i")
            ); ?>
        </p>

    <?php endif; ?>

    <p>
        <a href="area_cliente.php">
            Voltar para minha conta
        </a>
    </p>

    <p>
        <a href="agendamento.php">
            Fazer outro agendamento
        </a>
    </p>

</main>

</body>
</html>
