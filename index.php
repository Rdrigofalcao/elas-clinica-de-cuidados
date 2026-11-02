<?php
$nomeClinica = "Elas Clínica de Cuidados";
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title><?php echo $nomeClinica; ?></title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        /* Estilos exclusivos da página inicial */

        body {
            margin: 0;
            background: #f7f3ec;
            color: #493d34;
            font-family: Arial, sans-serif;
        }

        .cabecalho-inicial {
            background: #ffffff;
            padding: 22px 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .marca-inicial h1 {
            margin: 0;
            color: #b28b54;
            font-size: 35px;
            line-height: 1;
        }

        .marca-inicial p {
            margin: 5px 0 0;
            font-size: 11px;
            letter-spacing: 1px;
            color: #796d62;
        }

        .menu-inicial {
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .menu-inicial a {
            text-decoration: none;
            color: #493d34;
            font-size: 15px;
        }

        .menu-inicial .botao-menu {
            background: #b28b54;
            color: white;
            padding: 13px 23px;
            border-radius: 8px;
            font-weight: bold;
        }

        .conteudo-inicial {
            max-width: 1150px;
            margin: auto;
            padding: 35px 25px;
        }

        .destaque-inicial {
            background: #efe4d3;
            border-radius: 16px;
            padding: 40px 45px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 45px;
            align-items: center;
        }

        .etiqueta-inicial {
            color: #b28b54;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .destaque-inicial h2 {
            font-size: 39px;
            line-height: 1.2;
            margin: 24px 0;
            color: #493d34;
        }

        .destaque-inicial p {
            color: #796d62;
            font-size: 16px;
            line-height: 1.7;
        }

        .botao-dourado {
            display: inline-block;
            background: #b28b54;
            color: #ffffff;
            text-decoration: none;
            padding: 15px 26px;
            border-radius: 8px;
            font-weight: bold;
            margin-top: 12px;
        }

        .botao-dourado:hover,
        .menu-inicial .botao-menu:hover {
            background: #94703f;
            color: #ffffff;
        }

        .foto-destaque {
            width: 100%;
            height: 315px;
            object-fit: cover;
            border-radius: 15px;
            display: block;
            background: #e2d2bd;
        }

        .cuidados-inicial {
            text-align: center;
            padding: 50px 0 35px;
        }

        .cuidados-inicial h2 {
            font-size: 32px;
            margin-bottom: 14px;
        }

        .subtitulo-inicial {
            color: #796d62;
            margin-bottom: 30px;
        }

        .cartoes-inicial {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .cartao-inicial {
            background: #ffffff;
            border-radius: 14px;
            overflow: hidden;
            text-align: center;
            padding-bottom: 24px;
        }

        .cartao-inicial img {
            width: 100%;
            height: 170px;
            object-fit: cover;
            display: block;
        }

        .cartao-inicial h3 {
            margin: 22px 15px 12px;
            font-size: 20px;
        }

        .cartao-inicial p {
            color: #796d62;
            padding: 0 15px;
            line-height: 1.5;
        }

        .cartao-inicial a {
            display: inline-block;
            margin-top: 12px;
            color: #b28b54;
            text-decoration: none;
            font-weight: bold;
        }

        .sobre-inicial {
            padding: 25px 10px 40px;
            text-align: center;
        }

        .sobre-inicial p {
            max-width: 750px;
            margin: auto;
            color: #796d62;
            line-height: 1.8;
        }

        .chamada-inicial {
            background: #e8d8c4;
            border-radius: 14px;
            padding: 25px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .chamada-inicial h2 {
            font-size: 23px;
            margin: 0;
        }

        .chamada-inicial .botao-dourado {
            margin: 0;
        }

        .rodape-inicial {
            background: #ffffff;
            text-align: center;
            padding: 25px;
            color: #796d62;
            font-size: 13px;
        }

        @media (max-width: 800px) {
            .cabecalho-inicial {
                justify-content: center;
                text-align: center;
            }

            .menu-inicial {
                justify-content: center;
                gap: 16px;
            }

            .destaque-inicial {
                grid-template-columns: 1fr;
                padding: 30px;
            }

            .destaque-inicial h2 {
                font-size: 31px;
            }

            .cartoes-inicial {
                grid-template-columns: 1fr;
            }

            .chamada-inicial {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<header class="cabecalho-inicial">

    <div class="marca-inicial">
        <h1>ELAS</h1>
        <p>CLÍNICA DE CUIDADOS</p>
    </div>

    <nav class="menu-inicial">
        <a href="index.php">Início</a>
        <a href="#procedimentos">Procedimentos</a>
        <a href="#sobre">Sobre nós</a>
        <a href="login.php">Entrar</a>
        <a href="agendamento.php"
           class="botao-menu">Agendar</a>
    </nav>

</header>

<main class="conteudo-inicial">

    <section class="destaque-inicial">

        <div>
            <span class="etiqueta-inicial">
                ESTÉTICA • CUIDADO • BEM-ESTAR
            </span>

            <h2>
                Sua beleza merece<br>
                um cuidado especial.
            </h2>

            <p>
                Na Elas, cada atendimento é pensado
                para valorizar você. Escolha seu
                procedimento e reserve seu horário online.
            </p>

            <a href="agendamento.php"
               class="botao-dourado">
                AGENDAR HORÁRIO
            </a>
        </div>

        <img
            src="imagens/clinica.jpg"
            alt="Ambiente acolhedor de uma clínica de estética"
            class="foto-destaque"
        >

    </section>

    <section id="procedimentos"
             class="cuidados-inicial">

        <h2>Nossos cuidados</h2>

        <p class="subtitulo-inicial">
            Procedimentos pensados para seu bem-estar
        </p>

        <div class="cartoes-inicial">

            <article class="cartao-inicial">
                <img
                    src="imagens/limpeza-pele.jpg"
                    alt="Tratamento de limpeza de pele"
                >

                <h3>Limpeza de pele</h3>

                <p>
                    Cuidado e renovação para a pele.
                </p>

                <a href="agendamento.php">
                    Agendar procedimento
                </a>
            </article>

            <article class="cartao-inicial">
                <img
                    src="imagens/sobrancelhas.jpg"
                    alt="Procedimento de sobrancelhas"
                >

                <h3>Design de sobrancelhas</h3>

                <p>
                    Realce sua beleza natural.
                </p>

                <a href="agendamento.php">
                    Agendar procedimento
                </a>
            </article>

            <article class="cartao-inicial">
                <img
                    src="imagens/tratamento-facial.jpg"
                    alt="Tratamento facial"
                >

                <h3>Tratamentos faciais</h3>

                <p>
                    Atenção personalizada para você.
                </p>

                <a href="agendamento.php">
                    Agendar procedimento
                </a>
            </article>

        </div>

    </section>

    <section id="sobre" class="sobre-inicial">

        <h2>Sobre a Elas Clínica de Cuidados</h2>

        <p>
            A Elas Clínica de Cuidados é um espaço
            dedicado à beleza, autoestima e bem-estar.
            Nosso objetivo é proporcionar uma experiência
            acolhedora, com atenção e cuidado em cada
            atendimento.
        </p>

    </section>

    <section class="chamada-inicial">

        <h2>
            Pronta para reservar seu momento de cuidado?
        </h2>

        <a href="agendamento.php"
           class="botao-dourado">
            VER HORÁRIOS
        </a>

    </section>

</main>

<footer class="rodape-inicial">
    <p>
        © 2026 Elas Clínica de Cuidados •
        Protótipo acadêmico — sistema de agendamento
    </p>
</footer>

</body>
</html>
