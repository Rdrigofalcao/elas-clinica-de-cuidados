<?php
session_start();

require_once __DIR__ . "/config/conexao.php";

date_default_timezone_set("America/Bahia");

if (!isset($_SESSION["cliente_id"])) {
    header("Location: login.php");
    exit;
}

// Token para proteger o formulário de cancelamento.
if (!isset($_SESSION["csrf_cancelamento"])) {
    $_SESSION["csrf_cancelamento"] = bin2hex(
        random_bytes(32)
    );
}

$clienteId = (int)$_SESSION["cliente_id"];

$sql = "
    SELECT
        a.id_agendamento,
        a.data_agendamento,
        a.hora_inicio,
        a.hora_fim,
        a.status,
        p.nome AS profissional,
        pr.nome AS procedimento,
        pr.preco
    FROM agendamentos a
    INNER JOIN profissionais p
        ON p.id_profissional = a.id_profissional
    INNER JOIN procedimentos pr
        ON pr.id_procedimento = a.id_procedimento
    WHERE a.id_cliente = :cliente
    ORDER BY a.data_agendamento DESC, a.hora_inicio DESC
";

$consulta = $conexao->prepare($sql);

$consulta->execute([
    "cliente" => $clienteId
]);

$agendamentos = $consulta->fetchAll(PDO::FETCH_ASSOC);

function escapar($valor) {
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

$statusTexto = [
    "agendado" => "Agendado",
    "concluido" => "Concluído",
    "cancelado" => "Cancelado",
    "falta" => "Falta"
];

$fuso = new DateTimeZone("America/Bahia");
$agora = new DateTimeImmutable("now", $fuso);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Meus Agendamentos - Elas</title>

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
    <a href="agendamento.php">Agendar</a>
    <a href="logout.php">Sair</a>
</nav>

<main class="pagina-cadastro">

    <h2>Meus Agendamentos</h2>

    <p>Acompanhe seus atendimentos na clínica.</p>

    <?php if (empty($agendamentos)): ?>

        <p>Você ainda não possui agendamentos.</p>

        <a href="agendamento.php">
            Agendar meu primeiro atendimento
        </a>

    <?php else: ?>

        <?php foreach ($agendamentos as $agendamento): ?>

            <?php
                $inicioAtendimento = new DateTimeImmutable(
                    $agendamento["data_agendamento"] . " " .
                    $agendamento["hora_inicio"],
                    $fuso
                );

                $antecedencia = $inicioAtendimento->getTimestamp()
                    - $agora->getTimestamp();

                $podeCancelar =
                    $agendamento["status"] === "agendado"
                    && $antecedencia >= 86400;
            ?>

            <div class="cartao-agendamento">

                <h3>
                    <?php echo escapar(
                        $agendamento["procedimento"]
                    ); ?>
                </h3>

                <p>
                    <strong>Profissional:</strong>
                    <?php echo escapar(
                        $agendamento["profissional"]
                    ); ?>
                </p>

                <p>
                    <strong>Data:</strong>
                    <?php echo escapar(
                        $inicioAtendimento->format("d/m/Y")
                    ); ?>
                </p>

                <p>
                    <strong>Horário:</strong>
                    <?php echo escapar(
                        substr($agendamento["hora_inicio"], 0, 5)
                    ); ?>
                    às
                    <?php echo escapar(
                        substr($agendamento["hora_fim"], 0, 5)
                    ); ?>
                </p>

                <p>
                    <strong>Valor:</strong>
                    R$ <?php echo number_format(
                        (float)$agendamento["preco"],
                        2,
                        ",",
                        "."
                    ); ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <?php echo escapar(
                        $statusTexto[$agendamento["status"]]
                        ?? $agendamento["status"]
                    ); ?>
                </p>

                <?php if ($podeCancelar): ?>

                    <form
                        action="cancelar_agendamento.php"
                        method="POST"
                        onsubmit="return confirm('Deseja realmente cancelar este agendamento?');"
                    >

                        <input
                            type="hidden"
                            name="agendamento_id"
                            value="<?php echo (int)$agendamento["id_agendamento"]; ?>"
                        >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo escapar(
                                $_SESSION["csrf_cancelamento"]
                            ); ?>"
                        >

                        <button
                            type="submit"
                            class="botao-cancelar"
                        >
                            Cancelar Agendamento
                        </button>

                    </form>

                <?php elseif ($agendamento["status"] === "agendado"): ?>

                    <p class="aviso-cancelamento">
                        Cancelamento online indisponível:
                        é necessário solicitar com pelo menos
                        24 horas de antecedência.
                    </p>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

    <p>
        <a href="area_cliente.php">
            Voltar para minha conta
        </a>
    </p>

</main>

<footer>
    <p>© 2026 Elas Clínica de Cuidados</p>
</footer>

</body>
</html>
