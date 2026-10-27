<?php

/** @var mysqli $db */

header('Content-Type: application/json; charset=utf-8');

require_once '../../conexao.php';

$uid = $_GET['uid'] ?? $_POST['uid'] ?? '';

if (empty($uid)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'UID não informado.'
    ]);
    exit;
}

try {

    /*
     * ============================================
     * VERIFICA O PG
     * ============================================
     */

    $sqlPg = "
        SELECT id, id_modulo
        FROM pg
        WHERE id_lider = ?
        LIMIT 1
    ";

    $stmtPg = $db->prepare($sqlPg);

    $stmtPg->bind_param(
        "s",
        $uid
    );

    $stmtPg->execute();

    $resultPg =
        $stmtPg->get_result();

    /*
     * Não possui PG.
     */

    if ($resultPg->num_rows === 0) {

        echo json_encode([
            'status' => 'success',
            'unlocked' => false,
            'hasPg' => false,
            'hasChild' => false,
            'reason' => 'pg_required'
        ]);

        exit;
    }

    $pg = $resultPg->fetch_assoc();

    $idPg = intval(
        $pg['id']
    );

    $idModulo = intval(
        $pg['id_modulo']
    );


    /*
     * ============================================
     * VERIFICA SE EXISTE CRIANÇA
     * ============================================
     *
     * Além do UID, verificamos também o PG.
     */

    $sqlChild = "
        SELECT id
        FROM criancas
        WHERE id_lider = ?
        AND id_pg = ?
        LIMIT 1
    ";

    $stmtChild =
        $db->prepare($sqlChild);

    $stmtChild->bind_param(
        "si",
        $uid,
        $idPg
    );

    $stmtChild->execute();

    $resultChild =
        $stmtChild->get_result();


    /*
     * Nenhuma criança cadastrada.
     */

    if ($resultChild->num_rows === 0) {

        echo json_encode([
            'status' => 'success',
            'unlocked' => false,
            'hasPg' => true,
            'hasChild' => false,
            'reason' => 'child_required',
            'module' => $idModulo
        ]);

        exit;
    }


    /*
     * ============================================
     * ÁLBUM LIBERADO
     * ============================================
     */

    echo json_encode([
        'status' => 'success',
        'unlocked' => true,
        'hasPg' => true,
        'hasChild' => true,
        'module' => $idModulo
    ]);
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' =>
        'Não foi possível verificar o acesso ao álbum.',

        /*
         * Temporário durante desenvolvimento.
         */
        'debug' => $e->getMessage()
    ]);
}
