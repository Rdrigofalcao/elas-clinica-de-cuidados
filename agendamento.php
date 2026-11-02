<?php
session_start();

require_once __DIR__ . "/config/conexao.php";
require_once __DIR__ . "/config/verificar_bloqueio.php";

date_default_timezone_set("America/Bahia");

function escapar($valor) {
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Consultar procedimentos ativos
$sql = "
    SELECT
        id_procedimento,
        nome,
        descricao,
        duracao_minutos,
        preco
    FROM procedimentos
    WHERE ativo = 1
    ORDER BY nome ASC
";

$consulta = $conexao->query($sql);
$procedimentos = $consulta->fetchAll(PDO::FETCH_ASSOC);

// Consultar profissionais ativos
$sql = "
    SELECT
        id_profissional,
        nome,
        especialidade
    FROM profissionais
    WHERE ativo = 1
    ORDER BY nome ASC
";

$consulta = $conexao->query($sql);
$profissionais = $consulta->fetchAll(PDO::FETCH_ASSOC);

$nomeCliente = $_SESSION["cliente_nome"] ?? "Cliente";
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Agendamento - Elas Clínica de Cuidados</title>

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
    <a href="meus_agendamentos.php">Meus Agendamentos</a>
    <a href="logout.php">Sair</a>
</nav>

<main class="pagina-cadastro">

    <h2>Agendar Atendimento</h2>

    <p>
        Olá,
        <strong><?php echo escapar($nomeCliente); ?></strong>!
    </p>

    <p>
        Selecione o procedimento, o profissional
        e a data desejada.
    </p>

    <?php if (empty($procedimentos)): ?>

        <div class="mensagem erro">
            Nenhum procedimento está disponível.
        </div>

    <?php elseif (empty($profissionais)): ?>

        <div class="mensagem erro">
            Nenhum profissional está disponível.
        </div>

    <?php else: ?>

        <form
            action="consultar_horarios.php"
            method="GET"
        >

            <label for="procedimento">
                Procedimento:
            </label>

            <select
                name="procedimento"
                id="procedimento"
                required
            >

                <option value="">
                    Selecione um procedimento
                </option>

                <?php foreach ($procedimentos as $procedimento): ?>

                    <option
                        value="<?php echo (int)$procedimento["id_procedimento"]; ?>"
                    >
                        <?php echo escapar($procedimento["nome"]); ?>

                        -
                        <?php echo (int)$procedimento["duracao_minutos"]; ?>
                        minutos

                        -
                        R$
                        <?php
                        echo number_format(
                            (float)$procedimento["preco"],
                            2,
                            ",",
                            "."
                        );
                        ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <label for="profissional">
                Profissional:
            </label>

            <select
                name="profissional"
                id="profissional"
                required
            >

                <option value="">
                    Selecione um profissional
                </option>

                <?php foreach ($profissionais as $profissional): ?>

                    <option
                        value="<?php echo (int)$profissional["id_profissional"]; ?>"
                    >
                        <?php echo escapar($profissional["nome"]); ?>

                        -
                        <?php echo escapar($profissional["especialidade"]); ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <label for="data">
                Data do atendimento:
            </label>

            <input
                type="date"
                name="data"
                id="data"
                min="<?php echo date("Y-m-d"); ?>"
                required
            >

            <button type="submit">
                Consultar Horários Disponíveis
            </button>

        </form>

    <?php endif; ?>

    <p>
        <a href="area_cliente.php">
            Voltar para minha conta
        </a>
    </p>

</main>

<footer>
    <p>© 2026 Elas Clínica de Cuidados</p>
    <p>Beleza, autoestima e bem-estar.</p>
</footer>

</body>
</html>
