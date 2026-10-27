<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/firebase.php';
require_once __DIR__ . '/../conexao.php';

/** @var mysqli $db */

// ======================================================
// SOMENTE POST
// ======================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método não permitido.'
    ]);

    exit;
}

// ======================================================
// RECEBE JSON
// ======================================================

$dados = json_decode(
    file_get_contents('php://input'),
    true
);

$email = trim(
    strtolower($dados['email'] ?? '')
);

$token = trim(
    $dados['token'] ?? ''
);

$novaSenha =
    $dados['nova_senha'] ?? '';

// ======================================================
// VALIDAÇÕES
// ======================================================

if (
    $email === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'E-mail inválido.'
    ]);

    exit;
}

if ($token === '') {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'Token de recuperação inválido.'
    ]);

    exit;
}

if (strlen($novaSenha) < 6) {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'A senha deve possuir pelo menos 6 caracteres.'
    ]);

    exit;
}

// ======================================================
// TRANSFORMA TOKEN NO MESMO HASH SALVO NO BANCO
// ======================================================

$tokenHash = hash(
    'sha256',
    $token
);

// ======================================================
// PROCURA RECUPERAÇÃO
// ======================================================

$stmt = $db->prepare(
    'SELECT
        id,
        uid,
        email,
        token_hash,
        token_expira_em,
        usado
     FROM recuperacao_senha
     WHERE email = ?
       AND token_hash = ?
       AND usado = 0
     ORDER BY id DESC
     LIMIT 1'
);

$stmt->bind_param(
    'ss',
    $email,
    $tokenHash
);

$stmt->execute();

$resultado = $stmt->get_result();

$recuperacao =
    $resultado->fetch_assoc();

$stmt->close();

// ======================================================
// TOKEN NÃO EXISTE
// ======================================================

if (!$recuperacao) {
    http_response_code(401);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'Recuperação inválida ou já utilizada.'
    ]);

    exit;
}

// ======================================================
// VERIFICA EXPIRAÇÃO
// ======================================================

if (
    empty($recuperacao['token_expira_em'])
) {
    http_response_code(401);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'Recuperação inválida.'
    ]);

    exit;
}

$agora = new DateTime();

$tokenExpiraEm = new DateTime(
    $recuperacao['token_expira_em']
);

if ($agora > $tokenExpiraEm) {
    http_response_code(401);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'Sua recuperação expirou. Solicite um novo código.'
    ]);

    exit;
}

// ======================================================
// DADOS DA CONTA
// ======================================================

$idRecuperacao =
    (int) $recuperacao['id'];

$uid =
    $recuperacao['uid'];

// ======================================================
// ALTERA SENHA NO FIREBASE
// ======================================================

try {
    $firebaseAuth->updateUser(
        $uid,
        [
            'password' => $novaSenha
        ]
    );
} catch (Throwable $e) {

    error_log(
        'Erro Firebase redefinir senha: ' .
            $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'Não foi possível alterar a senha.'
    ]);

    exit;
}

// ======================================================
// ALTERA SENHA NO MYSQL
// ======================================================
//
// Atualmente seu sistema ainda mantém a senha
// diretamente na coluna usuario.senha.
// Estamos mantendo o comportamento atual.
//
// Futuramente vamos migrar isso para password_hash().
// ======================================================

$stmt = $db->prepare(
    'UPDATE usuario
     SET senha = ?
     WHERE uid = ?'
);

$stmt->bind_param(
    'ss',
    $novaSenha,
    $uid
);

if (!$stmt->execute()) {

    $stmt->close();

    // ==================================================
    // ATENÇÃO
    //
    // Nesse ponto o Firebase já foi alterado.
    // Por isso registramos o erro para investigação.
    // ==================================================

    error_log(
        'Senha alterada no Firebase, mas falhou no MySQL. UID: '
            . $uid
    );

    http_response_code(500);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'A senha foi alterada parcialmente. Entre em contato com o suporte.'
    ]);

    exit;
}

$stmt->close();

// ======================================================
// MARCA RECUPERAÇÃO COMO UTILIZADA
// ======================================================

$stmt = $db->prepare(
    'UPDATE recuperacao_senha
     SET
        usado = 1,
        token_hash = NULL,
        token_expira_em = NULL
     WHERE id = ?'
);

$stmt->bind_param(
    'i',
    $idRecuperacao
);

$stmt->execute();
$stmt->close();

// ======================================================
// INVALIDA QUALQUER OUTRA RECUPERAÇÃO
// ======================================================

$stmt = $db->prepare(
    'UPDATE recuperacao_senha
     SET usado = 1
     WHERE uid = ?
       AND usado = 0'
);

$stmt->bind_param(
    's',
    $uid
);

$stmt->execute();
$stmt->close();

// ======================================================
// SUCESSO
// ======================================================

echo json_encode([
    'sucesso' => true,
    'mensagem' =>
    'Senha redefinida com sucesso.'
]);
