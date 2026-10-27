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

    $usuario = $resultUsuario->fetch_assoc();

    $timezoneUsuario = trim(
        $usuario['timezone'] ?? ''
    );


    /*
     * ============================================
     * VALIDA O TIMEZONE
     * ============================================
     */

    if (empty($timezoneUsuario)) {
        $timezoneUsuario = 'America/Sao_Paulo';
    }

    try {

        $timezone = new DateTimeZone(
            $timezoneUsuario
        );
    } catch (Throwable $e) {

        $timezoneUsuario = 'America/Sao_Paulo';

        $timezone = new DateTimeZone(
            $timezoneUsuario
        );
    }


    /*
     * ============================================
     * DESCOBRE A DATA LOCAL DO USUÁRIO
     * ============================================
     */

    $agora = new DateTime(
        'now',
        $timezone
    );

    $dataPacote = $agora->format('Y-m-d');


    /*
     * ============================================
     * DESCOBRE MÓDULO ATUAL
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
     * VALIDA O MÓDULO
     * ============================================
     */

    if ($idModulo <= 0) {

        echo json_encode([
            'status' => 'module_not_found',
            'message' => 'O usuário ainda não possui um módulo ativo.'
        ]);

        exit;
    }


    /*
     * ============================================
     * VERIFICA PACOTE DO DIA LOCAL DO USUÁRIO
     * ============================================
     *
     * IMPORTANTE:
     *
     * Não usamos CURDATE().
     *
     * A data é calculada de acordo com o timezone
     * salvo para cada usuário.
     *
     */

    $sql = "
        SELECT
            p.id,
            p.id_modulo,
            p.figurinha_id,
            p.raridade,
            p.data_pacote,
            p.aberto_em,

            f.nome,
            f.emoji,
            f.imagem

        FROM album_pacotes p

        LEFT JOIN album_figurinhas f
            ON f.id = p.figurinha_id

        WHERE p.uid = ?
        AND p.data_pacote = ?

        LIMIT 1
    ";

    $stmt = $db->prepare($sql);

    $stmt->bind_param(
        "ss",
        $uid,
        $dataPacote
    );

    $stmt->execute();

    $result = $stmt->get_result();


    /*
     * ============================================
     * AINDA NÃO ABRIU
     * ============================================
     */

    if ($result->num_rows === 0) {

        echo json_encode(
            [
                'status' => 'success',

                'available' => true,

                'module' => $idModulo,

                'timezone' => $timezoneUsuario,

                'date' => $dataPacote
            ],
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }


    /*
     * ============================================
     * JÁ ABRIU
     * ============================================
     */

    $pack = $result->fetch_assoc();

    echo json_encode(
        [
            'status' => 'success',

            'available' => false,

            'module' => $idModulo,

            'timezone' => $timezoneUsuario,

            'date' => $dataPacote,

            'openedAt' => $pack['aberto_em'],

            'sticker' => [

                'id' => $pack['figurinha_id'],

                'name' => $pack['nome'],

                'emoji' => $pack['emoji'],

                'image' => $pack['imagem'],

                'rarity' => $pack['raridade']
            ]
        ],
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode(
        [
            'status' => 'error',

            'message' =>
            'Erro ao verificar pacote diário.',

            // TEMPORÁRIO PARA DESENVOLVIMENTO
            // Remover quando colocar em produção.
            'debug' => $e->getMessage()
        ],
        JSON_UNESCAPED_UNICODE
    );
}
