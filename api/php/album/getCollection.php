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
     * VERIFICA USUÁRIO
     * ============================================
     */

    $sqlUsuario = "
        SELECT uid
        FROM usuario
        WHERE uid = ?
        LIMIT 1
    ";

    $stmtUsuario = $db->prepare($sqlUsuario);
    $stmtUsuario->bind_param("s", $uid);
    $stmtUsuario->execute();

    $resultUsuario = $stmtUsuario->get_result();

    if ($resultUsuario->num_rows === 0) {

        echo json_encode([
            'status' => 'user_not_found',
            'message' => 'Usuário não encontrado.'
        ]);

        exit;
    }


    /*
     * ============================================
     * MÓDULO ATUAL
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

    $currentModule = 0;

    if ($resultPg->num_rows > 0) {
        $pg = $resultPg->fetch_assoc();

        $currentModule = intval(
            $pg['id_modulo'] ?? 0
        );
    }


    /*
     * ============================================
     * CONFIGURAÇÃO DOS MÓDULOS
     * ============================================
     */

    $moduleNames = [
        1 => 'A Trilha',
        2 => 'O Mergulho',
        3 => 'O Santuário',
        4 => 'O Arco',
        5 => 'A Estação Final'
    ];

    $modules = [];


    /*
     * ============================================
     * PROGRESSO DE CADA MÓDULO
     * ============================================
     */

    for ($module = 1; $module <= 5; $module++) {

        /*
         * Total de figurinhas existentes
         * naquele módulo.
         */

        $sqlTotal = "
            SELECT COUNT(*) AS total
            FROM album_figurinhas
            WHERE id_modulo = ?
            AND ativa = 1
        ";

        $stmtTotal = $db->prepare($sqlTotal);
        $stmtTotal->bind_param("i", $module);
        $stmtTotal->execute();

        $resultTotal = $stmtTotal->get_result();
        $rowTotal = $resultTotal->fetch_assoc();

        $total = intval(
            $rowTotal['total'] ?? 0
        );


        /*
         * Quantas figurinhas DIFERENTES
         * o usuário possui neste módulo.
         */

        $sqlOwned = "
            SELECT COUNT(*) AS owned
            FROM album_colecao c

            INNER JOIN album_figurinhas f
                ON f.id = c.figurinha_id

            WHERE c.uid = ?
            AND f.id_modulo = ?
            AND f.ativa = 1
            AND c.quantidade > 0
        ";

        $stmtOwned = $db->prepare($sqlOwned);

        $stmtOwned->bind_param(
            "si",
            $uid,
            $module
        );

        $stmtOwned->execute();

        $resultOwned = $stmtOwned->get_result();
        $rowOwned = $resultOwned->fetch_assoc();

        $owned = intval(
            $rowOwned['owned'] ?? 0
        );


        /*
         * Porcentagem do módulo.
         */

        $percentage = 0;

        if ($total > 0) {
            $percentage = round(
                ($owned / $total) * 100
            );
        }


        $modules[] = [
            'id' => $module,
            'name' => $moduleNames[$module],
            'total' => $total,
            'owned' => $owned,
            'percentage' => $percentage,
            'current' => $module === $currentModule
        ];
    }


    /*
     * ============================================
     * TOTAL DE FIGURINHAS DIFERENTES
     * ============================================
     */

    $sqlUnique = "
        SELECT COUNT(*) AS total
        FROM album_colecao
        WHERE uid = ?
        AND quantidade > 0
    ";

    $stmtUnique = $db->prepare($sqlUnique);
    $stmtUnique->bind_param("s", $uid);
    $stmtUnique->execute();

    $resultUnique = $stmtUnique->get_result();
    $rowUnique = $resultUnique->fetch_assoc();

    $totalUnique = intval(
        $rowUnique['total'] ?? 0
    );


    /*
     * ============================================
     * TOTAL DE CARTAS OBTIDAS
     * ============================================
     *
     * Aqui contamos também as repetidas.
     *
     * Exemplo:
     *
     * Carta A = 3
     * Carta B = 1
     *
     * totalCards = 4
     */

    $sqlCards = "
        SELECT COALESCE(SUM(quantidade), 0) AS total
        FROM album_colecao
        WHERE uid = ?
    ";

    $stmtCards = $db->prepare($sqlCards);
    $stmtCards->bind_param("s", $uid);
    $stmtCards->execute();

    $resultCards = $stmtCards->get_result();
    $rowCards = $resultCards->fetch_assoc();

    $totalCards = intval(
        $rowCards['total'] ?? 0
    );


    /*
     * ============================================
     * QUANTIDADE TOTAL DE REPETIDAS
     * ============================================
     *
     * IMPORTANTE:
     *
     * Se possui uma carta 3 vezes:
     *
     * 1 é da coleção
     * 2 são repetidas
     *
     * Portanto:
     *
     * totalDuplicates =
     * totalCards - totalUnique
     */

    $totalDuplicates =
        max(0, $totalCards - $totalUnique);


    /*
     * ============================================
     * TIPOS DE FIGURINHAS QUE POSSUEM REPETIDAS
     * ============================================
     */

    $sqlDuplicateTypes = "
        SELECT COUNT(*) AS total
        FROM album_colecao
        WHERE uid = ?
        AND quantidade > 1
    ";

    $stmtDuplicateTypes =
        $db->prepare($sqlDuplicateTypes);

    $stmtDuplicateTypes->bind_param(
        "s",
        $uid
    );

    $stmtDuplicateTypes->execute();

    $resultDuplicateTypes =
        $stmtDuplicateTypes->get_result();

    $rowDuplicateTypes =
        $resultDuplicateTypes->fetch_assoc();

    $duplicateTypes = intval(
        $rowDuplicateTypes['total'] ?? 0
    );


    /*
     * ============================================
     * LENDÁRIAS
     * ============================================
     */

    $sqlLegendaryTotal = "
        SELECT COUNT(*) AS total
        FROM album_figurinhas
        WHERE ativa = 1
        AND raridade = 'lendaria'
    ";

    $resultLegendaryTotal =
        $db->query($sqlLegendaryTotal);

    $rowLegendaryTotal =
        $resultLegendaryTotal->fetch_assoc();

    $legendaryTotal = intval(
        $rowLegendaryTotal['total'] ?? 0
    );


    $sqlLegendaryOwned = "
        SELECT COUNT(*) AS total
        FROM album_colecao c

        INNER JOIN album_figurinhas f
            ON f.id = c.figurinha_id

        WHERE c.uid = ?
        AND c.quantidade > 0
        AND f.ativa = 1
        AND f.raridade = 'lendaria'
    ";

    $stmtLegendaryOwned =
        $db->prepare($sqlLegendaryOwned);

    $stmtLegendaryOwned->bind_param(
        "s",
        $uid
    );

    $stmtLegendaryOwned->execute();

    $resultLegendaryOwned =
        $stmtLegendaryOwned->get_result();

    $rowLegendaryOwned =
        $resultLegendaryOwned->fetch_assoc();

    $legendaryOwned = intval(
        $rowLegendaryOwned['total'] ?? 0
    );


    /*
     * ============================================
     * LISTA DE FIGURINHAS REPETIDAS
     * ============================================
     */

    $sqlDuplicates = "
        SELECT
            f.id,
            f.id_modulo,
            f.nome,
            f.emoji,
            f.imagem,
            f.raridade,
            c.quantidade

        FROM album_colecao c

        INNER JOIN album_figurinhas f
            ON f.id = c.figurinha_id

        WHERE c.uid = ?
        AND c.quantidade > 1
        AND f.ativa = 1

        ORDER BY
            f.id_modulo ASC,
            f.id ASC
    ";

    $stmtDuplicates =
        $db->prepare($sqlDuplicates);

    $stmtDuplicates->bind_param(
        "s",
        $uid
    );

    $stmtDuplicates->execute();

    $resultDuplicates =
        $stmtDuplicates->get_result();

    $duplicates = [];

    while (
        $row = $resultDuplicates->fetch_assoc()
    ) {

        $duplicates[] = [
            'id' => $row['id'],
            'module' => intval($row['id_modulo']),
            'name' => $row['nome'],
            'emoji' => $row['emoji'],
            'image' => $row['imagem'],
            'rarity' => $row['raridade'],
            'quantity' => intval($row['quantidade'])
        ];
    }


    /*
     * ============================================
     * RESPOSTA
     * ============================================
     */

    echo json_encode(
        [
            'status' => 'success',

            'currentModule' => $currentModule,

            'summary' => [
                'unique' => $totalUnique,
                'cards' => $totalCards,
                'duplicates' => $totalDuplicates,
                'duplicateTypes' => $duplicateTypes,

                'legendaryOwned' =>
                    $legendaryOwned,

                'legendaryTotal' =>
                    $legendaryTotal
            ],

            'modules' => $modules,

            'duplicateStickers' => $duplicates
        ],
        JSON_UNESCAPED_UNICODE
    );

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode(
        [
            'status' => 'error',

            'message' =>
                'Não foi possível carregar a coleção.',

            // TEMPORÁRIO PARA DESENVOLVIMENTO.
            // Remover depois.
            'debug' => $e->getMessage()
        ],
        JSON_UNESCAPED_UNICODE
    );
}
