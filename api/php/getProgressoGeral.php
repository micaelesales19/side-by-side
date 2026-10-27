<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/funcao.php';

/** @var mysqli $db */

$dados = json_decode(file_get_contents('php://input'), true);

$uid = $dados['uid'] ?? '';
$idPg = (int)($dados['idPg'] ?? 0);

if ($uid === '' || $idPg <= 0) {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'dados_vazios',
    ]);

    exit;
}

try {

    $funcao = new Funcao();

    $resultado = $funcao->getProgressoGeral(
        $db,
        $uid,
        $idPg
    );

    echo json_encode($resultado);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'erro',
        'erro' => $e->getMessage(),
    ]);
}
