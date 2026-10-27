<?php

/** @var mysqli $db */

header('Content-Type: application/json; charset=utf-8');

require_once '../../conexao.php';

$uid = $_POST['uid'] ?? $_GET['uid'] ?? '';

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
     * INICIA TRANSAÇÃO
     * ============================================
     */

    $db->begin_transaction();


    /*
     * ============================================
     * BUSCA O TIMEZONE DO USUÁRIO
     * ============================================
     */

    $sqlUsuario = "
        SELECT timezone
        FROM usuario
        WHERE uid = ?
        LIMIT 1
    ";

    $stmtUsuario = $db->prepare($sqlUsuario);

    $stmtUsuario->bind_param(
        "s",
        $uid
    );

    $stmtUsuario->execute();

    $resultUsuario =
        $stmtUsuario->get_result();


    /*
     * ============================================
     * VERIFICA SE O USUÁRIO EXISTE
     * ============================================
     */

    if ($resultUsuario->num_rows === 0) {

        $db->rollback();

        echo json_encode([
            'status' => 'user_not_found',
            'message' => 'Usuário não encontrado.'
        ]);

        exit;
    }


    $usuario =
        $resultUsuario->fetch_assoc();

    $timezoneUsuario =
        trim($usuario['timezone'] ?? '');


    /*
     * ============================================
     * VALIDA O TIMEZONE
     * ============================================
     *
     * Caso o usuário ainda não possua timezone
     * ou o valor seja inválido, usamos
     * America/Sao_Paulo como fallback.
     */

    if (empty($timezoneUsuario)) {
        $timezoneUsuario =
            'America/Sao_Paulo';
    }

    try {

        $timezone = new DateTimeZone(
            $timezoneUsuario
        );
    } catch (Throwable $e) {

        $timezoneUsuario =
            'America/Sao_Paulo';

        $timezone = new DateTimeZone(
            $timezoneUsuario
        );
    }


    /*
     * ============================================
     * DATA/HORA LOCAL DO USUÁRIO
     * ============================================
     */

    $agora = new DateTime(
        'now',
        $timezone
    );

    /*
     * Exemplo:
     *
     * 2026-09-26
     */
    $dataPacote =
        $agora->format('Y-m-d');

    /*
     * Exemplo:
     *
     * 2026-09-26 16:30:15
     */
    $abertoEm =
        $agora->format(
            'Y-m-d H:i:s'
        );


    /*
     * ============================================
     * DESCOBRE O MÓDULO ATUAL DO USUÁRIO
     * ============================================
     */

    $sqlPg = "
        SELECT id,id_modulo
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


    if ($resultPg->num_rows === 0) {

        $db->rollback();

        echo json_encode([
            'status' => 'pg_not_found',
            'message' => 'PG não encontrado para este usuário.'
        ]);

        exit;
    }


    $pg =
        $resultPg->fetch_assoc();

    $idModulo =
        intval($pg['id_modulo']);


    /*
     * ============================================
     * VALIDA O MÓDULO
     * ============================================
     */

    if ($idModulo <= 0) {

        $db->rollback();

        echo json_encode([
            'status' => 'module_not_found',
            'message' => 'O usuário ainda não possui um módulo ativo.'
        ]);

        exit;
    }

    /*
    * ============================================
    * VERIFICA SE O PG POSSUI CRIANÇA CADASTRADA
    * ============================================
    */

    $idPg = intval($pg['id'] ?? 0);

    $sqlChild = "
        SELECT id
        FROM criancas
        WHERE id_lider = ?
        AND id_pg = ?
        LIMIT 1
    ";

    $stmtChild = $db->prepare($sqlChild);

    $stmtChild->bind_param(
        "si",
        $uid,
        $idPg
    );

    $stmtChild->execute();

    $resultChild = $stmtChild->get_result();

    if ($resultChild->num_rows === 0) {

        $db->rollback();

        echo json_encode([
            'status' => 'album_locked',
            'reason' => 'child_required',
            'message' => 'Cadastre pelo menos uma criança no seu PG para liberar o álbum.'
        ]);

        exit;
    }

    /*
     * ============================================
     * VERIFICA SE JÁ ABRIU O PACOTE HOJE
     * ============================================
     *
     * IMPORTANTE:
     *
     * Não usamos mais CURDATE().
     *
     * $dataPacote representa a data atual
     * no timezone do usuário.
     */

    $sqlCheck = "
        SELECT
            id,
            figurinha_id,
            id_modulo,
            aberto_em

        FROM album_pacotes

        WHERE uid = ?
        AND data_pacote = ?

        LIMIT 1

        FOR UPDATE
    ";

    $stmtCheck =
        $db->prepare($sqlCheck);

    $stmtCheck->bind_param(
        "ss",
        $uid,
        $dataPacote
    );

    $stmtCheck->execute();

    $resultCheck =
        $stmtCheck->get_result();


    if ($resultCheck->num_rows > 0) {

        $pack =
            $resultCheck->fetch_assoc();

        $db->rollback();

        echo json_encode([
            'status' => 'already_opened',
            'message' => 'O pacote de hoje já foi aberto.',
            'openedAt' => $pack['aberto_em']
        ]);

        exit;
    }


    /*
     * ============================================
     * SORTEIA A RARIDADE
     * ============================================
     *
     * 1 - 70   = comum       70%
     * 71 - 95  = rara        25%
     * 96 - 100 = lendária     5%
     */

    $rarityRoll =
        random_int(1, 100);


    if ($rarityRoll <= 70) {

        $raridade = 'comum';
    } elseif ($rarityRoll <= 95) {

        $raridade = 'rara';
    } else {

        $raridade = 'lendaria';
    }


    /*
     * ============================================
     * BUSCA FIGURINHAS
     * ============================================
     */

    $sqlStickers = "
        SELECT
            id,
            id_modulo,
            nome,
            emoji,
            imagem,
            raridade

        FROM album_figurinhas

        WHERE ativa = 1
        AND id_modulo = ?
        AND raridade = ?
    ";

    $stmtStickers =
        $db->prepare($sqlStickers);

    $stmtStickers->bind_param(
        "is",
        $idModulo,
        $raridade
    );

    $stmtStickers->execute();

    $resultStickers =
        $stmtStickers->get_result();


    /*
     * ============================================
     * VERIFICA SE EXISTEM FIGURINHAS
     * ============================================
     */

    if ($resultStickers->num_rows === 0) {

        throw new Exception(
            "Nenhuma figurinha disponível para a raridade {$raridade} no módulo {$idModulo}."
        );
    }


    /*
     * ============================================
     * MONTA LISTA
     * ============================================
     */

    $stickers = [];

    while (
        $row =
        $resultStickers->fetch_assoc()
    ) {

        $stickers[] = $row;
    }


    /*
     * ============================================
     * SORTEIA UMA FIGURINHA
     * ============================================
     */

    $randomIndex =
        random_int(
            0,
            count($stickers) - 1
        );

    $picked =
        $stickers[$randomIndex];


    /*
     * ============================================
     * REGISTRA O PACOTE
     * ============================================
     *
     * Agora enviamos explicitamente:
     *
     * $dataPacote
     * $abertoEm
     *
     * Não dependemos mais do timezone do MySQL.
     */

    $sqlPack = "
        INSERT INTO album_pacotes
        (
            uid,
            id_modulo,
            figurinha_id,
            raridade,
            data_pacote,
            aberto_em
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ";

    $stmtPack =
        $db->prepare($sqlPack);

    $stmtPack->bind_param(
        "sissss",
        $uid,
        $idModulo,
        $picked['id'],
        $picked['raridade'],
        $dataPacote,
        $abertoEm
    );

    $stmtPack->execute();


    /*
     * ============================================
     * ADICIONA À COLEÇÃO
     * ============================================
     *
     * Também usamos a hora local calculada
     * no PHP.
     */

    $sqlCollection = "
        INSERT INTO album_colecao
        (
            uid,
            figurinha_id,
            quantidade,
            primeira_obtencao,
            ultima_obtencao
        )

        VALUES
        (
            ?,
            ?,
            1,
            ?,
            ?
        )

        ON DUPLICATE KEY UPDATE

            quantidade = quantidade + 1,

            ultima_obtencao = ?
    ";

    $stmtCollection =
        $db->prepare($sqlCollection);

    $stmtCollection->bind_param(
        "sssss",
        $uid,
        $picked['id'],
        $abertoEm,
        $abertoEm,
        $abertoEm
    );

    $stmtCollection->execute();


    /*
     * ============================================
     * DESCOBRE A QUANTIDADE ATUAL
     * ============================================
     */

    $sqlQuantity = "
        SELECT quantidade

        FROM album_colecao

        WHERE uid = ?
        AND figurinha_id = ?

        LIMIT 1
    ";

    $stmtQuantity =
        $db->prepare($sqlQuantity);

    $stmtQuantity->bind_param(
        "ss",
        $uid,
        $picked['id']
    );

    $stmtQuantity->execute();

    $resultQuantity =
        $stmtQuantity->get_result();

    $collectionData =
        $resultQuantity->fetch_assoc();

    $quantity =
        intval(
            $collectionData['quantidade']
                ?? 1
        );


    /*
     * ============================================
     * CONFIRMA TUDO
     * ============================================
     */

    $db->commit();


    /*
     * ============================================
     * RETORNA A FIGURINHA
     * ============================================
     */

    echo json_encode(
        [
            'status' => 'success',

            'module' => $idModulo,

            /*
             * Útil para conferirmos
             * se o timezone está funcionando.
             */
            'timezone' =>
            $timezoneUsuario,

            'date' =>
            $dataPacote,

            'openedAt' =>
            $abertoEm,

            'sticker' => [

                'id' =>
                $picked['id'],

                'name' =>
                $picked['nome'],

                'emoji' =>
                $picked['emoji'],

                'image' =>
                $picked['imagem'],

                'rarity' =>
                $picked['raridade'],

                'quantity' =>
                $quantity
            ]
        ],

        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {

    try {

        $db->rollback();
    } catch (Throwable $ignored) {
    }


    http_response_code(500);


    echo json_encode(
        [
            'status' => 'error',

            'message' =>
            'Não foi possível abrir o pacote.',

            /*
             * TEMPORÁRIO PARA DESENVOLVIMENTO.
             *
             * Remover em produção.
             */
            'debug' =>
            $e->getMessage()
        ],

        JSON_UNESCAPED_UNICODE
    );
}
