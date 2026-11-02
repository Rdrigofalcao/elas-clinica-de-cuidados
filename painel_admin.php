<?php
session_start();

require_once __DIR__ . "/config/conexao.php";

date_default_timezone_set("America/Bahia");

// Proteção da área administrativa.
if (!isset($_SESSION["funcionario_id"])) {
    header("Location: login_admin.php");
    exit;
}

$funcionarioId = (int) $_SESSION["funcionario_id"];

// Confirma que o funcionário continua ativo.
$sql = "
    SELECT nome, cargo
    FROM funcionarios
    WHERE id_funcionario = :id
      AND ativo = 1
    LIMIT 1
";

$consulta = $conexao->prepare($sql);
$consulta->execute(["id" => $funcionarioId]);

$funcionario = $consulta->fetch(PDO::FETCH_ASSOC);

if (!$funcionario) {
    unset(
        $_SESSION["funcionario_id"],
        $_SESSION["funcionario_nome"],
        $_SESSION["funcionario_cargo"]
    );

    header("Location: login_admin.php");
    exit;
}

function escapar($valor) {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Indicadores administrativos.
$totalClientes = (int) $conexao
    ->query("SELECT COUNT(*) FROM clientes")
    ->fetchColumn();

$totalProfissionais = (int) $conexao
    ->query("SELECT COUNT(*) FROM profissionais WHERE ativo = 1")
    ->fetchColumn();

$totalAgendamentos = (int) $conexao
    ->query("SELECT COUNT(*) FROM agendamentos WHERE status = 'agendado'")
    ->fetchColumn();

$clientesBloqueados = (int) $conexao
    ->query("
        SELECT COUNT(*)
        FROM clientes
        WHERE agendamento_bloqueado = 1
    ")
    ->fetchColumn();

$sql = "
    SELECT COUNT(*)
    FROM agendamentos
    WHERE data_agendamento = CURDATE()
      AND status = 'agendado'
";

$atendimentosHoje = (int) $conexao
    ->query($sql)
    ->fetchColumn();

// Lista dos próximos atendimentos.
$sql = "
    SELECT
        a.id_agendamento,
        a.data_agendamento,
        a.hora_inicio,
        a.status,
        c.nome AS cliente,
        p.nome AS profissional,
        pr.nome AS procedimento
    FROM agendamentos a
    INNER JOIN clientes c
        ON c.id_cliente = a.id_cliente
    INNER JOIN profissionais p
        ON p.id_profissional = a.id_profissional
    INNER JOIN procedimentos pr
        ON pr.id_procedimento = a.id_procedimento
    WHERE a.status = 'agendado'
      AND (
          a.data_agendamento > CURDATE()
          OR (
              a.data_agendamento = CURDATE()
              AND a.hora_inicio >= CURTIME()
          )
      )
    ORDER BY
        a.data_agendamento ASC,
        a.hora_inicio ASC
    LIMIT 10
";

$consulta = $conexao->query($sql);
$proximosAgendamentos = $consulta->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Painel Administrativo - Elas Clínica de Cuidados</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .painel-admin {
            max-width: 1150px;
            margin: 35px auto;
            padding: 20px;
        }

        .painel-admin h1 {
            margin-bottom: 10px;
        }

        .painel-admin h2 {
            margin-top: 35px;
            margin-bottom: 15px;
        }

        .resumo-admin {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(190px, 1fr));
            gap: 18px;
            margin: 30px 0;
        }

        .indicador-admin {
            background: #FFF8F7;
            border: 1px solid #E8D2D0;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
        }

        .indicador-admin h3 {
            font-size: 16px;
            margin-bottom: 12px;
        }

        .indicador-admin strong {
            display: block;
            font-size: 32px;
            color: #945D60;
        }

        .acoes-admin {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin: 20px 0 35px;
        }

        .acao-admin {
            background: #FFF8F7;
            border: 1px solid #E8D2D0;
            border-radius: 12px;
            padding: 25px;
        }

        .acao-admin h3 {
            margin-top: 0;
            color: #945D60;
        }

        .acao-admin p {
            line-height: 1.6;
        }

        .botao-admin {
            display: inline-block;
            margin-top: 12px;
            padding: 12px 20px;
            background: #945D60;
            color: #FFFFFF;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        .botao-admin:hover {
            background: #75484B;
        }

        .tabela-container {
            width: 100%;
            overflow-x: auto;
        }

        .tabela-admin {
            width: 100%;
            border-collapse: collapse;
            background: #FFFFFF;
        }

        .tabela-admin th,
        .tabela-admin td {
            border-bottom: 1px solid #E8D2D0;
            padding: 14px;
            text-align: left;
        }

        .tabela-admin th {
            background: #F8E8E6;
        }

        .tabela-admin tr:hover {
            background: #FFF8F7;
        }

        .aviso-admin {
            padding: 20px;
            background: #FFF8F7;
            border-radius: 10px;
        }

        @media (max-width: 600px) {
            .painel-admin {
                padding: 15px;
            }

            .tabela-admin {
                min-width: 650px;
            }
        }
    </style>
</head>

<body>

<header>
    <h2>ELAS</h2>
    <p>Clínica de Cuidados</p>
</header>

<nav>
    <a href="painel_admin.php">Painel</a>
    <a href="gerenciar_agendamentos.php">Agendamentos</a>
    <a href="gerenciar_clientes.php">Clientes</a>
    <a href="index.php">Site da Clínica</a>
    <a href="logout_admin.php">Sair</a>
</nav>

<main class="painel-admin">

    <h1>Painel Administrativo</h1>

    <p>
        Bem-vindo(a),
        <strong><?= escapar($funcionario["nome"]) ?></strong>!
    </p>

    <p>
        Cargo: <?= escapar($funcionario["cargo"]) ?>
    </p>

    <section class="resumo-admin">

        <div class="indicador-admin">
            <h3>Clientes Cadastrados</h3>
            <strong><?= $totalClientes ?></strong>
        </div>

        <div class="indicador-admin">
            <h3>Profissionais Ativos</h3>
            <strong><?= $totalProfissionais ?></strong>
        </div>

        <div class="indicador-admin">
            <h3>Agendamentos Ativos</h3>
            <strong><?= $totalAgendamentos ?></strong>
        </div>

        <div class="indicador-admin">
            <h3>Atendimentos Hoje</h3>
            <strong><?= $atendimentosHoje ?></strong>
        </div>

        <div class="indicador-admin">
            <h3>Clientes Bloqueados</h3>
            <strong><?= $clientesBloqueados ?></strong>
        </div>

    </section>

    <section>

        <h2>Gerenciamento da Clínica</h2>

        <div class="acoes-admin">

            <div class="acao-admin">

                <h3>Gerenciar Agendamentos</h3>

                <p>
                    Consulte os atendimentos, registre faltas
                    injustificadas e marque procedimentos
                    como concluídos.
                </p>

                <a
                    href="gerenciar_agendamentos.php"
                    class="botao-admin"
                >
                    Acessar Agendamentos
                </a>

            </div>

            <div class="acao-admin">

                <h3>Gerenciar Clientes</h3>

                <p>
                    Consulte clientes, verifique ocorrências,
                    acompanhe bloqueios e realize
                    revisões administrativas.
                </p>

                <a
                    href="gerenciar_clientes.php"
                    class="botao-admin"
                >
                    Acessar Clientes
                </a>

            </div>

        </div>

    </section>

    <section>

        <h2>Próximos Agendamentos</h2>

        <?php if (empty($proximosAgendamentos)): ?>

            <p class="aviso-admin">
                Nenhum atendimento futuro encontrado.
            </p>

        <?php else: ?>

            <div class="tabela-container">

                <table class="tabela-admin">

                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Cliente</th>
                            <th>Profissional</th>
                            <th>Procedimento</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($proximosAgendamentos as $agendamento): ?>

                            <tr>

                                <td>
                                    <?= date(
                                        "d/m/Y",
                                        strtotime(
                                            $agendamento["data_agendamento"]
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= escapar(
                                        substr(
                                            $agendamento["hora_inicio"],
                                            0,
                                            5
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= escapar($agendamento["cliente"]) ?>
                                </td>

                                <td>
                                    <?= escapar($agendamento["profissional"]) ?>
                                </td>

                                <td>
                                    <?= escapar($agendamento["procedimento"]) ?>
                                </td>

                                <td>
                                    <?= escapar(
                                        ucfirst($agendamento["status"])
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>

<footer>
    <p>© 2026 Elas Clínica de Cuidados</p>
</footer>

</body>
</html>
