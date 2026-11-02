<?php
session_start();

require_once __DIR__ . "/config/conexao.php";

date_default_timezone_set("America/Bahia");

if (!isset($_SESSION["funcionario_id"])) {
    header("Location: login_admin.php");
    exit;
}

$funcionarioId = (int)$_SESSION["funcionario_id"];

// Confirma que o funcionário continua ativo.
$sqlFuncionario = "
    SELECT id_funcionario
    FROM funcionarios
    WHERE id_funcionario = :id
      AND ativo = 1
    LIMIT 1
";

$verificacao = $conexao->prepare($sqlFuncionario);
$verificacao->execute(["id" => $funcionarioId]);

if (!$verificacao->fetch(PDO::FETCH_ASSOC)) {
    unset(
        $_SESSION["funcionario_id"],
        $_SESSION["funcionario_nome"],
        $_SESSION["funcionario_cargo"]
    );

    header("Location: login_admin.php");
    exit;
}

// Token de segurança para as ações administrativas.
if (!isset($_SESSION["csrf_admin"])) {
    $_SESSION["csrf_admin"] = bin2hex(random_bytes(32));
}

function escapar($valor) {
    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

$busca = trim($_GET["busca"] ?? "");
$status = trim($_GET["status"] ?? "");

$statusTexto = [
    "agendado" => "Agendado",
    "concluido" => "Concluído",
    "cancelado" => "Cancelado",
    "falta" => "Falta"
];

if (!array_key_exists($status, $statusTexto)) {
    $status = "";
}

$sql = "
    SELECT
        a.id_agendamento,
        a.data_agendamento,
        a.hora_inicio,
        a.hora_fim,
        a.status,
        c.nome AS cliente,
        c.telefone,
        p.nome AS profissional,
        pr.nome AS procedimento
    FROM agendamentos a
    INNER JOIN clientes c
        ON c.id_cliente = a.id_cliente
    INNER JOIN profissionais p
        ON p.id_profissional = a.id_profissional
    INNER JOIN procedimentos pr
        ON pr.id_procedimento = a.id_procedimento
    WHERE 1 = 1
";

$parametros = [];

if ($busca !== "") {
    $sql .= " AND (
        c.nome LIKE :busca_cliente
        OR p.nome LIKE :busca_profissional
        OR pr.nome LIKE :busca_procedimento
    )";

    $termo = "%" . $busca . "%";

    $parametros["busca_cliente"] = $termo;
    $parametros["busca_profissional"] = $termo;
    $parametros["busca_procedimento"] = $termo;
}

if ($status !== "") {
    $sql .= " AND a.status = :status";
    $parametros["status"] = $status;
}

$sql .= "
    ORDER BY
        a.data_agendamento DESC,
        a.hora_inicio DESC
    LIMIT 100
";

$consulta = $conexao->prepare($sql);
$consulta->execute($parametros);

$agendamentos = $consulta->fetchAll(PDO::FETCH_ASSOC);

$fuso = new DateTimeZone("America/Bahia");
$agora = new DateTimeImmutable("now", $fuso);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Gerenciar Agendamentos - Elas</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .gestao-container {
            max-width: 1250px;
            margin: 35px auto;
            padding: 20px;
        }

        .filtros-gestao {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 25px 0;
            align-items: end;
        }

        .campo-filtro {
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex: 1;
            min-width: 180px;
        }

        .campo-filtro input,
        .campo-filtro select {
            padding: 12px;
            border: 1px solid #D9BFC0;
            border-radius: 8px;
            width: 100%;
        }

        .botao-filtrar {
            padding: 12px 22px;
            background: #945D60;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .tabela-rolagem {
            overflow-x: auto;
        }

        .tabela-gestao {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .tabela-gestao th,
        .tabela-gestao td {
            padding: 13px;
            border-bottom: 1px solid #E8D2D0;
            text-align: left;
            vertical-align: middle;
        }

        .tabela-gestao th {
            background: #F8E8E6;
        }

        .tabela-gestao tr:hover {
            background: #FFF8F7;
        }

        .status-agendado {
            color: #2563EB;
            font-weight: bold;
        }

        .status-concluido {
            color: #15803D;
            font-weight: bold;
        }

        .status-cancelado,
        .status-falta {
            color: #B91C1C;
            font-weight: bold;
        }

        .acoes-admin {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 140px;
        }

        .acoes-admin form {
            margin: 0;
        }

        .botao-concluir,
        .botao-falta {
            width: 100%;
            padding: 9px 12px;
            border: none;
            border-radius: 7px;
            color: white;
            cursor: pointer;
        }

        .botao-concluir {
            background: #15803D;
        }

        .botao-concluir:hover {
            background: #166534;
        }

        .botao-falta {
            background: #B91C1C;
        }

        .botao-falta:hover {
            background: #991B1B;
        }

        .texto-indisponivel {
            font-size: 13px;
            color: #777;
        }

        @media (max-width: 700px) {
            .tabela-gestao {
                min-width: 1000px;
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
    <a href="logout_admin.php">Sair</a>
</nav>

<main class="gestao-container">

    <h1>Gerenciar Agendamentos</h1>

    <p>
        Consulte e atualize os atendimentos da clínica.
    </p>

    <form
        method="GET"
        action="gerenciar_agendamentos.php"
        class="filtros-gestao"
    >

        <div class="campo-filtro">
            <label for="busca">Pesquisar:</label>

            <input
                type="text"
                id="busca"
                name="busca"
                maxlength="100"
                placeholder="Cliente, profissional ou procedimento"
                value="<?php echo escapar($busca); ?>"
            >
        </div>

        <div class="campo-filtro">
            <label for="status">Status:</label>

            <select id="status" name="status">
                <option value="">Todos</option>

                <?php foreach ($statusTexto as $valor => $texto): ?>
                    <option
                        value="<?php echo escapar($valor); ?>"
                        <?php echo $status === $valor ? "selected" : ""; ?>
                    >
                        <?php echo escapar($texto); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="botao-filtrar">
            Filtrar
        </button>

    </form>

    <p>
        <strong>Registros exibidos:</strong>
        <?php echo count($agendamentos); ?>
        <small>(máximo de 100 por consulta)</small>
    </p>

    <?php if (empty($agendamentos)): ?>

        <p>Nenhum agendamento encontrado.</p>

    <?php else: ?>

        <div class="tabela-rolagem">

            <table class="tabela-gestao">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Horário</th>
                        <th>Cliente</th>
                        <th>Telefone</th>
                        <th>Profissional</th>
                        <th>Procedimento</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($agendamentos as $agendamento): ?>

                        <?php
                            $fimAtendimento = new DateTimeImmutable(
                                $agendamento["data_agendamento"] . " " .
                                $agendamento["hora_fim"],
                                $fuso
                            );

                            $podeAtualizar =
                                $agendamento["status"] === "agendado"
                                && $agora >= $fimAtendimento;
                        ?>

                        <tr>

                            <td>
                                <?php echo (int)$agendamento["id_agendamento"]; ?>
                            </td>

                            <td>
                                <?php echo escapar(
                                    $fimAtendimento->format("d/m/Y")
                                ); ?>
                            </td>

                            <td>
                                <?php echo escapar(
                                    substr($agendamento["hora_inicio"], 0, 5)
                                ); ?>
                                às
                                <?php echo escapar(
                                    substr($agendamento["hora_fim"], 0, 5)
                                ); ?>
                            </td>

                            <td>
                                <?php echo escapar($agendamento["cliente"]); ?>
                            </td>

                            <td>
                                <?php echo escapar($agendamento["telefone"]); ?>
                            </td>

                            <td>
                                <?php echo escapar($agendamento["profissional"]); ?>
                            </td>

                            <td>
                                <?php echo escapar($agendamento["procedimento"]); ?>
                            </td>

                            <td class="status-<?php echo escapar(
                                $agendamento["status"]
                            ); ?>">
                                <?php echo escapar(
                                    $statusTexto[$agendamento["status"]]
                                    ?? $agendamento["status"]
                                ); ?>
                            </td>

                            <td>

                                <?php if ($podeAtualizar): ?>

                                    <div class="acoes-admin">

                                        <form
                                            method="POST"
                                            action="atualizar_agendamento_admin.php"
                                            onsubmit="return confirm('Confirmar a conclusão deste atendimento?');"
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
                                                    $_SESSION["csrf_admin"]
                                                ); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="acao"
                                                value="concluir"
                                            >

                                            <button
                                                type="submit"
                                                class="botao-concluir"
                                            >
                                                Concluir
                                            </button>

                                        </form>

                                        <form
                                            method="POST"
                                            action="atualizar_agendamento_admin.php"
                                            onsubmit="return confirm('Confirmar falta injustificada deste cliente?');"
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
                                                    $_SESSION["csrf_admin"]
                                                ); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="acao"
                                                value="registrar_falta"
                                            >

                                            <button
                                                type="submit"
                                                class="botao-falta"
                                            >
                                                Registrar Falta
                                            </button>

                                        </form>

                                    </div>

                                <?php else: ?>

                                    <span class="texto-indisponivel">
                                        Sem ações disponíveis
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</main>

<footer>
    <p>© 2026 Elas Clínica de Cuidados</p>
</footer>

</body>
</html>
