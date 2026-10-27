<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../conexao.php';

use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método não permitido.'
    ]);

    exit;
}

// ======================================================
// 1. TOKEN
// ======================================================

$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (!preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
    http_response_code(401);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Token não informado.'
    ]);

    exit;
}

$idToken = trim($matches[1]);

// ======================================================
// 2. NOVA SENHA
// ======================================================

$dados = json_decode(
    file_get_contents('php://input'),
    true
);

$novaSenha = $dados['senha'] ?? '';

if ($novaSenha === '') {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Informe a nova senha.'
    ]);

    exit;
}

if (strlen($novaSenha) < 6) {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'codigo' => 'senha_fraca',
        'mensagem' => 'A senha precisa ter pelo menos 6 caracteres.'
    ]);

    exit;
}

try {

    // ==================================================
    // 3. VALIDA TOKEN
    // ==================================================

    $verifiedIdToken =
        $firebaseAuth->verifyIdToken($idToken);

    $uid = $verifiedIdToken
        ->claims()
        ->get('sub');

    if (!$uid) {
        throw new Exception(
            'UID não encontrado no token.'
        );
    }

    // ==================================================
    // 4. CONFIRMA USUÁRIO NO MYSQL
    // ==================================================

    $stmt = $db->prepare("
        SELECT uid
        FROM usuario
        WHERE uid = ?
        LIMIT 1
    ");

    $stmt->bind_param('s', $uid);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        $stmt->close();

        http_response_code(404);

        echo json_encode([
            'sucesso' => false,
            'codigo' => 'usuario_nao_encontrado',
            'mensagem' => 'Usuário não encontrado.'
        ]);

        exit;
    }

    $stmt->close();

    // ==================================================
    // 5. ALTERA NO FIREBASE
    // ==================================================

    $firebaseAuth->updateUser(
        $uid,
        [
            'password' => $novaSenha,
        ]
    );

    // ==================================================
    // 6. ALTERA NO MYSQL
    // ==================================================

    $stmt = $db->prepare("
        UPDATE usuario
        SET senha = ?
        WHERE uid = ?
    ");

    $stmt->bind_param(
        'ss',
        $novaSenha,
        $uid
    );

    if (!$stmt->execute()) {
        $stmt->close();

        http_response_code(500);

        echo json_encode([
            'sucesso' => false,
            'codigo' => 'erro_banco',
            'mensagem' =>
            'A senha foi alterada no Firebase, mas houve um erro ao atualizar o banco de dados.'
        ]);

        exit;
    }

    $stmt->close();

    // ==================================================
    // 7. SUCESSO
    // ==================================================

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Senha alterada com sucesso.'
    ]);
} catch (FailedToVerifyToken $e) {

    http_response_code(401);

    echo json_encode([
        'sucesso' => false,
        'codigo' => 'token_invalido',
        'mensagem' =>
        'Sua sessão é inválida ou expirou.'
    ]);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'sucesso' => false,
        'codigo' => 'erro',
        'mensagem' =>
        'Não foi possível alterar a senha.'
    ]);
}
