<?php

header('Content-Type: application/json; charset=utf-8');

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

$codigo = trim(
    (string) ($dados['codigo'] ?? '')
);

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

if (!preg_match('/^\d{6}$/', $codigo)) {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Código inválido.'
    ]);

    exit;
}

// ======================================================
// PROCURA ÚLTIMO CÓDIGO ATIVO
// ======================================================

$stmt = $db->prepare(
    'SELECT
        id,
        uid,
        codigo_hash,
        expira_em,
        tentativas
     FROM recuperacao_senha
     WHERE email = ?
       AND usado = 0
     ORDER BY id DESC
     LIMIT 1'
);

$stmt->bind_param(
    's',
    $email
);

$stmt->execute();
$resultado = $stmt->get_result();
$recuperacao = $resultado->fetch_assoc();
$stmt->close();

// ======================================================
// NÃO ENCONTROU
// ======================================================

if (!$recuperacao) {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'Código inválido ou expirado.'
    ]);

    exit;
}

// ======================================================
// VERIFICA EXPIRAÇÃO
// ======================================================

$agora = new DateTime();

$expiraEm = new DateTime(
    $recuperacao['expira_em']
);

if ($agora > $expiraEm) {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'Este código expirou. Solicite um novo.'
    ]);

    exit;
}

if ((int) ($recuperacao['tentativas'] ?? 0) >= 5) {
    http_response_code(429);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'Muitas tentativas. Solicite um novo código.'
    ]);

    exit;
}

// ======================================================
// VERIFICA CÓDIGO
// ======================================================
if (
    !password_verify(
        $codigo,
        $recuperacao['codigo_hash']
    )
) {
    $idRecuperacao =
        (int) $recuperacao['id'];

    $stmt = $db->prepare(
        'UPDATE recuperacao_senha
         SET tentativas = tentativas + 1
         WHERE id = ?'
    );
    $stmt->bind_param(
        'i',
        $idRecuperacao
    );
    $stmt->execute();
    $stmt->close();

    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Código inválido.'
    ]);
    exit;
}

// ======================================================
// CÓDIGO CORRETO
//
// GERA TOKEN TEMPORÁRIO PARA AUTORIZAR
// A TROCA DA SENHA
// ======================================================

$token = bin2hex(
    random_bytes(32)
);

// Token que será salvo no banco
$tokenHash = hash(
    'sha256',
    $token
);

// ======================================================
// SALVA TOKEN
//
// TOKEN VÁLIDO POR 10 MINUTOS
// ======================================================

$idRecuperacao =
    (int) $recuperacao['id'];

$stmt = $db->prepare(
    'UPDATE recuperacao_senha
     SET
        token_hash = ?,
        token_expira_em =
            DATE_ADD(NOW(), INTERVAL 10 MINUTE)
     WHERE id = ?'
);

$stmt->bind_param(
    'si',
    $tokenHash,
    $idRecuperacao
);

if (!$stmt->execute()) {
    http_response_code(500);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'Não foi possível validar o código.'
    ]);

    exit;
}

$stmt->close();

// ======================================================
// SUCESSO
// ======================================================

echo json_encode([
    'sucesso' => true,

    'mensagem' =>
    'Código validado com sucesso.',

    'token' => $token
]);
