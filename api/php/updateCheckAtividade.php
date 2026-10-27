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

// ======================================================
// 1. RECEBE OS DADOS
// ======================================================

$dados = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($dados)) {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Dados inválidos.'
    ]);

    exit;
}

$idLider  = $dados['uid'] ?? '';
$idPg     = $dados['idPg'] ?? '';
$idModulo = $dados['idModulo'] ?? '';
$nLicao   = $dados['nLicao'] ?? '';
$tipo     = $dados['tipo'] ?? '';
$concluido = $dados['concluido'] ?? null;

// ======================================================
// 2. VALIDA CAMPOS OBRIGATÓRIOS
// ======================================================

if (
    $idLider === '' ||
    $idPg === '' ||
    $idModulo === '' ||
    $nLicao === '' ||
    $tipo === '' ||
    $concluido === null
) {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Campos obrigatórios não informados.'
    ]);

    exit;
}

// ======================================================
// 3. CHAMA A FUNÇÃO
// ======================================================

$funcao = new Funcao();

$funcao->updateCheckAtividade(
    $idLider,
    (int) $idPg,
    (int) $idModulo,
    (int) $nLicao,
    $tipo,
    (int) $concluido
);
