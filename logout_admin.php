<?php
session_start();

unset(
    $_SESSION["funcionario_id"],
    $_SESSION["funcionario_nome"],
    $_SESSION["funcionario_cargo"]
);

session_regenerate_id(true);

header("Location: login_admin.php");
exit;
