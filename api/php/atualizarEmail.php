<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../conexao.php';

use Kreait\Firebase\Exception\Auth\EmailExists;
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
// 1. PEGA O TOKEN DO FIREBASE
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
// 2. PEGA OS DADOS ENVIADOS
// ======================================================

$dados = json_decode(file_get_contents('php://input'), true);

$novoEmail = trim($dados['email'] ?? '');

if ($novoEmail === '') {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Informe o novo e-mail.'
    ]);

    exit;
}

if (!filter_var($novoEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'E-mail inválido.'
    ]);

    exit;
}

try {

    // ==================================================
    // 3. VALIDA O TOKEN
    // ==================================================

    $verifiedIdToken = $firebaseAuth->verifyIdToken($idToken);

    $uid = $verifiedIdToken->claims()->get('sub');

    if (!$uid) {
        throw new Exception('UID não encontrado no token.');
    }

    // ==================================================
    // 4. BUSCA O USUÁRIO
    // ==================================================

    $usuario = $firebaseAuth->getUser($uid);

    $emailAtual = $usuario->email;

    // Não precisa alterar se for o mesmo e-mail
    if (
        $emailAtual !== null &&
        strtolower($emailAtual) === strtolower($novoEmail)
    ) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'O e-mail informado já é o e-mail atual.',
            'email' => $emailAtual
        ]);

        exit;
    }

    // ==================================================
    // 5. ALTERA O E-MAIL
    // ==================================================

    // ==================================================
    // 5. VERIFICA SE O E-MAIL JÁ EXISTE NO MYSQL
    // ==================================================

    $stmt = $db->prepare("
        SELECT uid
        FROM usuario
        WHERE email = ?
        AND uid <> ?
        LIMIT 1
    ");

    $stmt->bind_param('ss', $novoEmail, $uid);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        http_response_code(409);

        echo json_encode([
            'sucesso' => false,
            'codigo' => 'email_em_uso',
            'mensagem' => 'Este e-mail já está sendo utilizado por outra conta.'
        ]);

        exit;
    }

    $stmt->close();


    // ==================================================
    // 6. ALTERA NO FIREBASE AUTH
    // ==================================================

    $usuarioAtualizado = $firebaseAuth->updateUser($uid, [
        'email' => $novoEmail,
    ]);


    // ==================================================
    // 7. ALTERA NO MYSQL
    // ==================================================

    $stmt = $db->prepare("
        UPDATE usuario
        SET email = ?
        WHERE uid = ?
    ");

    $stmt->bind_param('ss', $novoEmail, $uid);

    if (!$stmt->execute()) {

        // Se o MySQL falhar, tenta restaurar o e-mail no Firebase
        if ($emailAtual !== null) {
            try {
                $firebaseAuth->updateUser($uid, [
                    'email' => $emailAtual,
                ]);
            } catch (Throwable $rollbackError) {
                // Não expõe detalhes internos
            }
        }

        $stmt->close();

        http_response_code(500);

        echo json_encode([
            'sucesso' => false,
            'codigo' => 'erro_banco',
            'mensagem' => 'Não foi possível atualizar o e-mail.'
        ]);

        exit;
    }

    $stmt->close();


    // ==================================================
    // 8. SUCESSO
    // ==================================================

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'E-mail alterado com sucesso.',
        'email' => $usuarioAtualizado->email
    ]);
} catch (EmailExists $e) {

    http_response_code(409);

    echo json_encode([
        'sucesso' => false,
        'codigo' => 'email_em_uso',
        'mensagem' => 'Este e-mail já está sendo utilizado por outra conta.'
    ]);
} catch (FailedToVerifyToken $e) {

    http_response_code(401);

    echo json_encode([
        'sucesso' => false,
        'codigo' => 'token_invalido',
        'mensagem' => 'Sua sessão é inválida ou expirou.'
    ]);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'sucesso' => false,
        'codigo' => 'erro',
        'mensagem' => 'Não foi possível alterar o e-mail.'
    ]);
}
