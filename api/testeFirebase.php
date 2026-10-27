<?php

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/conexao.php';

// ======================================================
// DADOS PARA TESTE
// ======================================================

// Pegue o UID no Firebase Authentication
$uid = 'XRuDbqcyDmfdcupe8MlME18x3Uo1';

// Coloque o novo e-mail que deseja testar
$novoEmail = 'campbrasil@gmail.com';

try {

    // ==================================================
    // 1. BUSCA O USUÁRIO NO FIREBASE
    // ==================================================

    $usuario = $firebaseAuth->getUser($uid);

    $emailAtual = $usuario->email;

    // ==================================================
    // 2. VERIFICA SE O NOVO EMAIL JÁ EXISTE NO MYSQL
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

        $stmt->close();

        echo json_encode([
            'sucesso' => false,
            'codigo' => 'email_em_uso_mysql',
            'mensagem' => 'Este e-mail já pertence a outro usuário no MySQL.'
        ]);

        exit;
    }

    $stmt->close();


    // ==================================================
    // 3. ALTERA NO FIREBASE
    // ==================================================

    $usuarioAtualizado = $firebaseAuth->updateUser($uid, [
        'email' => $novoEmail,
    ]);


    // ==================================================
    // 4. ALTERA NO MYSQL
    // ==================================================

    $stmt = $db->prepare("
        UPDATE usuario
        SET email = ?
        WHERE uid = ?
    ");

    $stmt->bind_param('ss', $novoEmail, $uid);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {

        $stmt->close();

        // Tenta devolver o Firebase ao e-mail anterior
        if ($emailAtual !== null) {
            $firebaseAuth->updateUser($uid, [
                'email' => $emailAtual,
            ]);
        }

        echo json_encode([
            'sucesso' => false,
            'codigo' => 'usuario_mysql_nao_encontrado',
            'mensagem' => 'O usuário não foi encontrado ou não foi alterado no MySQL.'
        ]);

        exit;
    }

    $stmt->close();


    // ==================================================
    // 5. CONFERE O RESULTADO NO MYSQL
    // ==================================================

    $stmt = $db->prepare("
        SELECT uid, nome, email
        FROM usuario
        WHERE uid = ?
        LIMIT 1
    ");

    $stmt->bind_param('s', $uid);
    $stmt->execute();

    $usuarioMysql = $stmt->get_result()->fetch_assoc();

    $stmt->close();


    // ==================================================
    // SUCESSO
    // ==================================================

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Teste realizado com sucesso.',
        'antes' => [
            'firebase' => $emailAtual
        ],
        'depois' => [
            'firebase' => $usuarioAtualizado->email,
            'mysql' => $usuarioMysql['email'] ?? null
        ],
        'usuario' => [
            'uid' => $usuarioMysql['uid'] ?? $uid,
            'nome' => $usuarioMysql['nome'] ?? null
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
