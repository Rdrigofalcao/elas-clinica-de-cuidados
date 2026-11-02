<?php
session_start();

if (!isset($_SESSION["cliente_id"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/config/conexao.php";

$idCliente = (int) $_SESSION["cliente_id"];

try {
    $sql = "SELECT nome, agendamento_bloqueado
            FROM clientes
            WHERE id_cliente = :id
            LIMIT 1";

    $consulta = $conexao->prepare($sql);
    $consulta->execute(["id" => $idCliente]);

    $cliente = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }

    $nomeCliente = htmlspecialchars(
        $cliente["nome"],
        ENT_QUOTES,
        "UTF-8"
    );

    $bloqueado = (int) $cliente["agendamento_bloqueado"] === 1;

} catch (PDOException $erro) {
    http_response_code(500);
    exit("Não foi possível carregar sua conta. Tente novamente.");
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Área do Cliente - Elas Clínica de Cuidados</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .painel-cliente {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 25px;
        }

        .opcao-cliente {
            padding: 20px;
            border: 1px solid #ead5d1;
            border-radius: 10px;
            background-color: #fffaf9;
        }

        .opcao-cliente h3 {
            margin-top: 0;
            color: #795451;
        }

        .botao-cliente {
            display: inline-block;
            margin-top: 12px;
            padding: 12px 20px;
            border-radius: 7px;
            background-color: #956a67;
            color: #ffffff;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.2s;
        }

        .botao-cliente:hover {
            background-color: #754e4b;
        }

        .aviso-bloqueio {
            padding: 18px;
            border: 1px solid #e0b7b0;
            border-radius: 8px;
            background-color: #fff0ed;
            color: #803d37;
            text-align: left;
        }

        .aviso-bloqueio h3 {
            margin-top: 0;
        }

        .pagina-cadastro {
            width: min(92%, 600px);
            max-width: 600px;
            margin: 45px auto;
            box-sizing: border-box;
        }
    </style>
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

    <h2>Bem-vinda à Elas!</h2>

    <p>
        Olá, <strong><?= $nomeCliente ?></strong>!
    </p>

    <p>
        Este é seu espaço exclusivo para acompanhar
        seus cuidados e agendamentos.
    </p>

    <div class="painel-cliente">

        <?php if ($bloqueado): ?>

            <div class="aviso-bloqueio">
                <h3>Agendamentos suspensos</h3>

                <p>
                    Seu cadastro está temporariamente
                    impedido de realizar novos agendamentos
                    devido ao registro de faltas injustificadas.
                </p>

                <p>
                    Entre em contato com a clínica para
                    solicitar uma revisão administrativa.
                </p>

                <p>
                    Você ainda pode consultar seu histórico
                    de atendimentos.
                </p>
            </div>

        <?php endif; ?>

        <div class="opcao-cliente">

            <h3>Meus Agendamentos</h3>

            <p>
                Consulte seus atendimentos,
                horários e histórico de agendamentos.
            </p>

            <a
                href="meus_agendamentos.php"
                class="botao-cliente"
            >
                Ver Meus Agendamentos
            </a>

        </div>

        <div class="opcao-cliente">

            <h3>Agendar Procedimento</h3>

            <p>
                Escolha um procedimento, profissional
                e horário disponível.
            </p>

            <?php if ($bloqueado): ?>

                <p>
                    <strong>
                        Novos agendamentos indisponíveis
                        no momento.
                    </strong>
                </p>

            <?php else: ?>

                <a
                    href="agendamento.php"
                    class="botao-cliente"
                >
                    Agendar Atendimento
                </a>

            <?php endif; ?>

        </div>

    </div>

</main>

<footer>
    <p>© 2026 Elas Clínica de Cuidados</p>
    <p>Beleza, autoestima e bem-estar.</p>
</footer>

</body>
</html>
