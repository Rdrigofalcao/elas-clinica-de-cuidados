<?php
session_start();

// Remove os dados armazenados na sessão.
$_SESSION = [];

// Remove o cookie de sessão do navegador.
if (ini_get("session.use_cookies")) {
    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $parametros["path"],
        $parametros["domain"],
        $parametros["secure"],
        $parametros["httponly"]
    );
}

// Encerra a sessão.
session_destroy();

// Redireciona para a página de login.
header("Location: login.php");
exit;
?>
