<?php

/** @var mysqli $db */

header('Content-Type: application/json; charset=utf-8');

require_once '../../conexao.php';

$uid = $_GET['uid'] ?? '';

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
     * DESCOBRE O MÓDULO ATUAL
     * ============================================
     */

    $sqlPg = "
        SELECT id_modulo
        FROM pg
        WHERE id_lider = ?
        LIMIT 1
    ";

    $stmtPg = $db->prepare($sqlPg);
    $stmtPg->bind_param("s", $uid);
    $stmtPg->execute();

    $resultPg = $stmtPg->get_result();

    if ($resultPg->num_rows === 0) {
        echo json_encode([
            'status' => 'pg_not_found',
            'message' => 'PG não encontrado.'
        ]);
        exit;
    }

    $pg = $resultPg->fetch_assoc();

    $idModulo = intval(
        $pg['id_modulo']
    );


    /*
     * ============================================
     * BUSCA A COLEÇÃO DO MÓDULO
     * ============================================
     */

    $sql = "
        SELECT
            f.id,
            f.nome,
            f.emoji,
            f.imagem,
            f.raridade,

            COALESCE(
                c.quantidade,
                0
            ) AS quantidade

        FROM album_figurinhas f

        LEFT JOIN album_colecao c
            ON c.figurinha_id = f.id
            AND c.uid = ?

        WHERE f.id_modulo = ?
        AND f.ativa = 1

        ORDER BY f.id ASC
    ";

    $stmt = $db->prepare($sql);

    $stmt->bind_param(
        "si",
        $uid,
        $idModulo
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $stickers = [];

    while ($row = $result->fetch_assoc()) {

        $stickers[] = [
            'id' => $row['id'],
            'name' => $row['nome'],
            'emoji' => $row['emoji'],
            'image' => $row['imagem'],
            'rarity' => $row['raridade'],
            'quantity' => intval(
                $row['quantidade']
            )
        ];
    }


    /*
     * ============================================
     * ESTATÍSTICAS
     * ============================================
     */

    $total = count($stickers);

    $owned = 0;

    foreach ($stickers as $sticker) {
        if ($sticker['quantity'] > 0) {
            $owned++;
        }
    }


    /*
     * ============================================
     * RETORNO
     * ============================================
     */

    echo json_encode(
        [
            'status' => 'success',

            'module' => $idModulo,

            'total' => $total,

            'owned' => $owned,

            'missing' => $total - $owned,

            'completion' =>
            $total > 0
                ? round(
                    ($owned / $total) * 100,
                    2
                )
                : 0,

            'stickers' => $stickers
        ],
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode(
        [
            'status' => 'error',
            'message' =>
            'Erro ao carregar álbum.',

            // temporário
            'debug' =>
            $e->getMessage()
        ],
        JSON_UNESCAPED_UNICODE
    );
}
