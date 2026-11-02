<?php
session_start();

require_once __DIR__ . "/config/conexao.php";
require_once __DIR__ . "/config/verificar_bloqueio.php";

if (!isset($_SESSION["cliente_id"])) {
    header("Location: login.php");
    exit;
}

function escapar($valor) {
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

$procedimentoId = filter_input(
    INPUT_GET,
    "procedimento",
    FILTER_VALIDATE_INT
);

$profissionalId = filter_input(
    INPUT_GET,
    "profissional",
    FILTER_VALIDATE_INT
);

$data = $_GET["data"] ?? "";

$erro = "";
$horariosDisponiveis = [];

$fuso = new DateTimeZone("America/Bahia");
$agora = new DateTimeImmutable("now", $fuso);

$dataSelecionada = DateTimeImmutable::createFromFormat(
    "!Y-m-d",
    $data,
    $fuso
);

if (
    !$procedimentoId ||
    !$profissionalId ||
    !$dataSelecionada ||
    $dataSelecionada->format("Y-m-d") !== $data
) {
    $erro = "Selecione um procedimento, profissional e data válidos.";

} elseif ($dataSelecionada < $agora->setTime(0, 0)) {

    $erro = "Não é possível agendar em uma data passada.";

} else {

    // Verifica se o profissional realiza o procedimento.
    $sql = "
        SELECT
            pr.duracao_minutos,
            pr.nome AS procedimento,
            p.nome AS profissional
        FROM profissional_procedimento pp
        INNER JOIN procedimentos pr
            ON pr.id_procedimento = pp.id_procedimento
        INNER JOIN profissionais p
            ON p.id_profissional = pp.id_profissional
        WHERE pp.id_profissional = :profissional
          AND pp.id_procedimento = :procedimento
          AND pr.ativo = 1
          AND p.ativo = 1
    ";

    $consulta = $conexao->prepare($sql);

    $consulta->execute([
        "profissional" => $profissionalId,
        "procedimento" => $procedimentoId
    ]);

    $servico = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$servico) {

        $erro = "Este profissional não realiza o procedimento selecionado.";

    } elseif ((int)$servico["duracao_minutos"] <= 0) {

        $erro = "A duração do procedimento é inválida.";

    } else {

        $diaSemana = (int)$dataSelecionada->format("N");

        // Consulta os horários de trabalho.
        $sql = "
            SELECT hora_inicio, hora_fim
            FROM horarios_trabalho
            WHERE id_profissional = :profissional
              AND dia_semana = :dia
              AND ativo = 1
            ORDER BY hora_inicio
        ";

        $consulta = $conexao->prepare($sql);

        $consulta->execute([
            "profissional" => $profissionalId,
            "dia" => $diaSemana
        ]);

        $turnos = $consulta->fetchAll(PDO::FETCH_ASSOC);

        // Consulta os agendamentos existentes.
        $sql = "
            SELECT hora_inicio, hora_fim
            FROM agendamentos
            WHERE id_profissional = :profissional
              AND data_agendamento = :data
              AND status = 'agendado'
            ORDER BY hora_inicio
        ";

        $consulta = $conexao->prepare($sql);

        $consulta->execute([
            "profissional" => $profissionalId,
            "data" => $data
        ]);

        $ocupados = $consulta->fetchAll(PDO::FETCH_ASSOC);

        $duracao = (int)$servico["duracao_minutos"];
        $intervalo = 15;

        // Gera horários de 15 em 15 minutos.
        foreach ($turnos as $turno) {

            $inicioTurno = new DateTimeImmutable(
                $data . " " . $turno["hora_inicio"],
                $fuso
            );

            $fimTurno = new DateTimeImmutable(
                $data . " " . $turno["hora_fim"],
                $fuso
            );

            for (
                $inicio = $inicioTurno;
                $inicio < $fimTurno;
                $inicio = $inicio->modify("+15 minutes")
            ) {

                $fim = $inicio->modify(
                    "+{$duracao} minutes"
                );

                // Impede horários passados e fora do expediente.
                if ($inicio <= $agora || $fim > $fimTurno) {
                    continue;
                }

                $disponivel = true;

                foreach ($ocupados as $ocupado) {

                    $inicioOcupado = new DateTimeImmutable(
                        $data . " " . $ocupado["hora_inicio"],
                        $fuso
                    );

                    $fimOcupado = new DateTimeImmutable(
                        $data . " " . $ocupado["hora_fim"],
                        $fuso
                    );

                    $limiteAntes = $inicioOcupado->modify(
                        "-{$intervalo} minutes"
                    );

                    $limiteDepois = $fimOcupado->modify(
                        "+{$intervalo} minutes"
                    );

                    // Bloqueia conflitos e exige 15 minutos de intervalo.
                    if (
                        $inicio < $limiteDepois &&
                        $fim > $limiteAntes
                    ) {
                        $disponivel = false;
                        break;
                    }
                }

                if ($disponivel) {
                    $horariosDisponiveis[] = $inicio->format("H:i");
                }
            }
        }

        $horariosDisponiveis = array_values(
            array_unique($horariosDisponiveis)
        );

        sort($horariosDisponiveis);
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

    <title>Horários Disponíveis - Elas</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<header>
    <h2>ELAS</h2>
    <p>Clínica de Cuidados</p>
</header>

<nav>
    <a href="index.php">Início</a>
    <a href="area_cliente.php">Minha Conta</a>
    <a href="logout.php">Sair</a>
</nav>

<main class="pagina-cadastro">

    <h2>Horários Disponíveis</h2>

    <?php if ($erro !== ""): ?>

        <div class="mensagem erro">
            <?php echo escapar($erro); ?>
        </div>

    <?php else: ?>

        <p>
            <strong>Procedimento:</strong>
            <?php echo escapar($servico["procedimento"]); ?>
        </p>

        <p>
            <strong>Profissional:</strong>
            <?php echo escapar($servico["profissional"]); ?>
        </p>

        <p>
            <strong>Data:</strong>
            <?php echo escapar(
                $dataSelecionada->format("d/m/Y")
            ); ?>
        </p>

        <?php if (empty($horariosDisponiveis)): ?>

            <div class="mensagem erro">
                Não há horários disponíveis nesta data.
            </div>

        <?php else: ?>

            <h3>Escolha um horário</h3>

            <form
                action="confirmar_agendamento.php"
                method="POST"
            >

                <input
                    type="hidden"
                    name="procedimento"
                    value="<?php echo (int)$procedimentoId; ?>"
                >

                <input
                    type="hidden"
                    name="profissional"
                    value="<?php echo (int)$profissionalId; ?>"
                >

                <input
                    type="hidden"
                    name="data"
                    value="<?php echo escapar($data); ?>"
                >

                <div class="grade-horarios">

                    <?php foreach ($horariosDisponiveis as $horario): ?>

                        <button
                            type="submit"
                            name="horario"
                            value="<?php echo escapar($horario); ?>"
                            class="horario-disponivel"
                        >
                            <?php echo escapar($horario); ?>
                        </button>

                    <?php endforeach; ?>

                </div>

            </form>

            <p>
                Selecione o horário desejado para
                prosseguir com a confirmação.
            </p>

        <?php endif; ?>

    <?php endif; ?>

    <p>
        <a href="agendamento.php">
            Voltar ao agendamento
        </a>
    </p>

</main>

<footer>
    <p>© 2026 Elas Clínica de Cuidados</p>
    <p>Beleza, autoestima e bem-estar.</p>
</footer>

</body>
</html>
