<?php

header('Content-Type: application/json; charset=utf-8');

require_once 'funcao.php';

$idLider = $_POST['uid'] ?? $_GET['uid'] ?? '';
$idPg = $_POST['idPg'] ?? $_GET['idPg'] ?? '';
$idModulo = $_POST['idModulo'] ?? $_GET['idModulo'] ?? '';

$funcao = new Funcao();

$funcao->getProgressoModulo(
    $idLider,
    $idPg,
    $idModulo
);
