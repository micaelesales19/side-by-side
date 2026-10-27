<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../../config/email.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);

    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'E-mail inválido.'
    ]);

    exit;
}

// ======================================================
// PROCURA USUÁRIO
// ======================================================

// IMPORTANTE:
// ajuste "email" e "uid" caso os nomes sejam diferentes
// na sua tabela usuario.

$stmt = $db->prepare(
    'SELECT uid, email
     FROM usuario
     WHERE email = ?
     LIMIT 1'
);

$stmt->bind_param('s', $email);
$stmt->execute();

$resultado = $stmt->get_result();

$usuario = $resultado->fetch_assoc();

$stmt->close();

// ======================================================
// RESPOSTA GENÉRICA
// ======================================================

// Não é interessante revelar se determinado e-mail
// possui ou não conta no sistema.

if (!$usuario) {
    echo json_encode([
        'sucesso' => true,
        'mensagem' =>
        'Se este e-mail estiver cadastrado, enviaremos um código de recuperação.'
    ]);

    exit;
}

$uid = $usuario['uid'];

// ======================================================
// EVITA REENVIO EM EXCESSO
// ======================================================

$stmt = $db->prepare(
    'SELECT criado_em
     FROM recuperacao_senha
     WHERE uid = ?
     ORDER BY id DESC
     LIMIT 1'
);

$stmt->bind_param('s', $uid);
$stmt->execute();

$resultado = $stmt->get_result();
$ultimaRecuperacao = $resultado->fetch_assoc();

$stmt->close();

if ($ultimaRecuperacao) {

    $ultimaSolicitacao = new DateTime(
        $ultimaRecuperacao['criado_em']
    );

    $agora = new DateTime();

    $diferenca =
        $agora->getTimestamp()
        - $ultimaSolicitacao->getTimestamp();

    if ($diferenca < 60) {

        http_response_code(429);

        echo json_encode([
            'sucesso' => false,
            'mensagem' =>
            'Aguarde alguns segundos antes de solicitar um novo código.'
        ]);

        exit;
    }
}

// ======================================================
// GERA CÓDIGO
// ======================================================

$codigo = (string) random_int(
    100000,
    999999
);

$codigoHash = password_hash(
    $codigo,
    PASSWORD_DEFAULT
);

// ======================================================
// INVALIDA CÓDIGOS ANTERIORES
// ======================================================

$stmt = $db->prepare(
    'UPDATE recuperacao_senha
     SET usado = 1
     WHERE uid = ?
     AND usado = 0'
);

$stmt->bind_param('s', $uid);
$stmt->execute();
$stmt->close();

// ======================================================
// SALVA NOVO CÓDIGO
// ======================================================

$stmt = $db->prepare(
    'INSERT INTO recuperacao_senha
        (
            uid,
            email,
            codigo_hash,
            expira_em
        )
     VALUES
        (
            ?,
            ?,
            ?,
            DATE_ADD(NOW(), INTERVAL 10 MINUTE)
        )'
);

$stmt->bind_param(
    'sss',
    $uid,
    $email,
    $codigoHash
);

if (!$stmt->execute()) {
    http_response_code(500);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'Não foi possível iniciar a recuperação.'
    ]);

    exit;
}

$stmt->close();

// ======================================================
// ENVIA E-MAIL
// ======================================================

$mail = new PHPMailer(true);

try {

    $mail->isSMTP();

    // ================================================
    // SMTP HOSTINGER
    // ================================================

    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASSWORD;
    $mail->SMTPSecure =
        PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';

    // ================================================
    // REMETENTE
    // ================================================
    $mail->setFrom(
        SMTP_FROM_EMAIL,
        SMTP_FROM_NAME
    );

    $mail->addAddress(
        $email
    );

    // ================================================
    // CONTEÚDO
    // ================================================

    $mail->isHTML(true);

    $mail->Subject =
        'Código para recuperar sua senha';

    $mail->Body = '
        <div style="
            font-family: Arial, sans-serif;
            max-width: 520px;
            margin: auto;
            padding: 30px;
        ">

            <h2 style="
                margin-bottom: 8px;
            ">
                Recuperação de senha
            </h2>

            <p>
                Recebemos uma solicitação para
                redefinir sua senha no Lado a Lado.
            </p>

            <p>
                Seu código de verificação é:
            </p>

            <div style="
                font-size: 32px;
                font-weight: bold;
                letter-spacing: 8px;
                margin: 30px 0;
            ">
                ' . htmlspecialchars($codigo) . '
            </div>

            <p>
                Este código é válido por
                <strong>10 minutos</strong>.
            </p>

            <p style="
                color: #777;
                font-size: 13px;
                margin-top: 30px;
            ">
                Se você não solicitou a recuperação
                da sua senha, ignore esta mensagem.
            </p>

        </div>
    ';

    $mail->AltBody =
        "Seu código de recuperação do Lado a Lado é: "
        . $codigo
        . ". O código expira em 10 minutos.";

    $mail->send();
} catch (Exception $e) {

    // Invalida o código que não conseguiu ser enviado
    $stmt = $db->prepare(
        'UPDATE recuperacao_senha
         SET usado = 1
         WHERE uid = ?
           AND usado = 0'
    );

    $stmt->bind_param('s', $uid);
    $stmt->execute();
    $stmt->close();

    error_log(
        'Erro PHPMailer: ' .
            $mail->ErrorInfo
    );

    http_response_code(500);

    echo json_encode([
        'sucesso' => false,
        'mensagem' =>
        'Não foi possível enviar o código.'
    ]);

    exit;
}

// ======================================================
// SUCESSO
// ======================================================

echo json_encode([
    'sucesso' => true,
    'mensagem' =>
    'Se este e-mail estiver cadastrado, enviaremos um código de recuperação.'
]);
