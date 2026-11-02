# Elas Clínica de Cuidados

## Sobre o projeto

Sistema web de agendamento e gestão para uma clínica de estética, desenvolvido como Projeto Integrado Individual do curso de Análise e Desenvolvimento de Sistemas.

O sistema permite que clientes realizem agendamentos online e que funcionários gerenciem os atendimentos por meio de uma área administrativa.

## Tecnologias utilizadas

- PHP
- MySQL
- HTML5
- CSS3
- JavaScript
- XAMPP
- Git e GitHub

## Funcionalidades

### Área do cliente
- Cadastro e login.
- Consulta de horários disponíveis.
- Agendamento de procedimentos estéticos.
- Escolha do profissional.
- Visualização dos próprios agendamentos.
- Cancelamento conforme as regras da clínica.

### Área administrativa
- Autenticação de funcionários.
- Gerenciamento de agendamentos.
- Gerenciamento de clientes.
- Acompanhamento de ocorrências.
- Revisão de bloqueios de clientes.

## Regras de negócio

- Os procedimentos possuem durações variáveis.
- O sistema impede conflitos de horários.
- Profissionais diferentes podem realizar atendimentos simultâneos.
- Existe intervalo de 15 minutos entre atendimentos.
- O cancelamento pelo cliente exige antecedência mínima de 24 horas.
- O sistema registra faltas e permite bloqueio após três ausências válidas.
- O desbloqueio pode ser realizado pela administração, com registro da revisão.

## Como executar o projeto

### Requisitos

- XAMPP com Apache e MySQL.
- PHP com extensão PDO MySQL habilitada.
- Navegador web.

### Instalação

1. Baixe ou clone este repositório.
2. Coloque a pasta do projeto dentro de `htdocs` do XAMPP.
3. Inicie Apache e MySQL.
4. Crie um banco MySQL chamado `elas_clinica`.
5. Crie as tabelas necessárias conforme o modelo de dados do projeto.
6. Copie `config/conexao.exemplo.php` para `config/conexao.php`.
7. Ajuste o usuário, a senha e a porta do MySQL conforme seu ambiente.
8. Acesse no navegador:

   http://localhost/elas_clinica/

**Observação:** o repositório não inclui as credenciais locais nem o script de criação do administrador. A estrutura do banco e a configuração inicial de contas administrativas precisam ser preparadas no ambiente de instalação.

## Organização do projeto

- `config/` — configurações e verificações do sistema.
- `css/` — estilos visuais.
- `imagens/` — recursos gráficos.
- `index.php` — página inicial.
- `cadastro.php` e `login.php` — cadastro e autenticação de clientes.
- `agendamento.php` — agendamento de procedimentos.
- `painel_admin.php` — painel administrativo.

## Objetivo acadêmico

Aplicar conhecimentos de análise de requisitos, modelagem UML, banco de dados relacional, desenvolvimento web, testes de software e controle de versões.

## Autor

Rodrigo Falcão

Projeto Integrado Individual — Análise e Desenvolvimento de Sistemas.
