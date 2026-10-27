<?php

header('Content-Type: application/json; charset=utf-8');

require_once 'funcao.php';

$funcao = new Funcao();

$uid = $_GET['uid'] ?? '';
$nome = $_GET['nome'] ?? '';
$email = $_GET['email'] ?? '';
$telefone = $_GET['phone'] ?? '';
$pais = $_GET['pais'] ?? '';
$estado = $_GET['estado'] ?? '';
$cidade = $_GET['cidade'] ?? '';
$foto = $_GET['photo'] ?? '';

$funcao->updateUser(
    $uid,
    $nome,
    $email,
    $telefone,
    $pais,
    $estado,
    $cidade,
    $foto
);
