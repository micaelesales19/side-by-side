<?php

header('Content-Type: application/json; charset=utf-8');

require_once 'funcao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método não permitido.'
    ]);

    exit;
}

$dados = json_decode(
    file_get_contents('php://input'),
    true
);

$uid = $dados['uid'] ?? '';
$idPg = $dados['idPg'] ?? 0;
$idModulo = $dados['idModulo'] ?? 0;
$nLicao = $dados['nLicao'] ?? 0;

if (
    empty($uid) ||
    empty($idPg) ||
    empty($idModulo) ||
    empty($nLicao)
) {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'dados_vazios'
    ]);

    exit;
}

$funcoes = new Funcao();

$funcoes->getSemanariosLiberados(
    $uid,
    $idPg,
    $idModulo,
    $nLicao
);
