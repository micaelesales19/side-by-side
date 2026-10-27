<?php

date_default_timezone_set('America/Sao_Paulo');

class Funcao
{

    function totalLicoesModulo(int $idModulo): int
    {
        return match ($idModulo) {
            1 => 8,
            2 => 8,
            3 => 10,
            4 => 10,
            5 => 8,
            default => 0,
        };
    }

    private function calcularSemanariosLiberados(
        mysqli $db,
        string $idLider,
        int $idPg,
        int $idModulo,
        int $idLicao
    ): int {

        $stmt = $db->prepare("
        SELECT checks, data_conclusao
        FROM check_licao
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND n_licao = ?
        LIMIT 1
    ");

        $stmt->bind_param(
            "siii",
            $idLider,
            $idPg,
            $idModulo,
            $idLicao
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 0) {
            return 0;
        }

        $licao = $resultado->fetch_assoc();

        // A lição principal ainda não foi concluída
        if ((int) $licao['checks'] !== 1) {
            return 0;
        }

        // Sem data de conclusão não temos como calcular
        if (empty($licao['data_conclusao'])) {
            return 0;
        }

        $dataConclusao = new DateTime($licao['data_conclusao']);
        $hoje = new DateTime();

        // A liberação é por DIA DO CALENDÁRIO
        $dataConclusao->setTime(0, 0, 0);
        $hoje->setTime(0, 0, 0);

        // Proteção contra data futura
        if ($dataConclusao > $hoje) {
            return 0;
        }

        $diasPassados = (int) $dataConclusao
            ->diff($hoje)
            ->format('%a');

        // Dia da conclusão = Dia 1
        $liberados = $diasPassados + 1;

        // Nunca menos que 1 nem mais que 6
        return max(1, min(6, $liberados));
    }

    public function nome_e_sobrenome($nome)
    {
        $partes = explode(' ', $nome);
        if (count($partes) >= 2) {
            $nomeESobrenome = $partes[0] . ' ' . $partes[count($partes) - 1];
        } else {
            $nomeESobrenome = $nome;
        }

        return $nomeESobrenome;
    }
    public function cadastro($uid, $nome, $pais, $estado, $cidade, $telefone, $email, $senha, $token)
    {
        require_once '../conexao.php';

        $data = date('d/m/Y');

        // CONSULTAR NA TABELA
        $consulta = "SELECT * FROM usuario WHERE nome = '$nome' OR email = '$email' OR telefone = '$telefone'";
        $resultado = mysqli_query($db, $consulta);
        $cont = mysqli_num_rows($resultado);

        if ($cont == 0) {
            $inserir_usuario_sql = "INSERT INTO usuario VALUES('$uid', '$token' , '$nome', '$email', '$senha', '$telefone', '$pais', '$estado', '$cidade', 1, '$data', '')";
            $inserir_usuario = mysqli_query($db, $inserir_usuario_sql);

            if ($inserir_usuario) {
                $consulta2 = "SELECT * FROM usuario WHERE nome = '$nome' AND email = '$email' AND senha = '$senha'";
                $resultado2 = mysqli_query($db, $consulta2);

                $dados = array();

                while ($fetchData = $resultado2->fetch_assoc()) {
                    $dados[] = $fetchData;
                }
                echo json_encode($dados);
            } else {
                echo json_encode("erro_no_cadastro");
            }
        } else {
            echo json_encode("ja_cadastrado");
        }
    }

    public function login($uid, $nome, $email, $senha, $telefone, $pais, $estado, $cidade, $token, $timezone)
    {
        require_once '../conexao.php';

        $sql = "SELECT * FROM usuario WHERE uid = '$uid'";
        $query = mysqli_query($db, $sql);
        $cont = mysqli_num_rows($query);

        $data = date('d/m/Y');

        $dados_array = array();

        if ($cont == 0) {
            if ($nome == '' && $telefone == '') {
                echo json_encode('vc_precisa_se_cadastrar');
            } else {
                $cadastro_usuario_sql = "
                    INSERT INTO usuario (
                        uid,
                        token_alert,
                        nome,
                        email,
                        senha,
                        telefone,
                        pais,
                        estado,
                        cidade,
                        timezone,
                        ativo,
                        data,
                        foto
                    ) VALUES (
                        '$uid',
                        '$token',
                        '$nome',
                        '$email',
                        '$senha',
                        '$telefone',
                        '$pais',
                        '$estado',
                        '$cidade',
                        '$timezone',
                        1,
                        '$data',
                        ''
                    )
                ";
                $cadastro_usuario = mysqli_query($db, $cadastro_usuario_sql);

                if ($cadastro_usuario) {
                    $sql_2 = "SELECT * FROM usuario WHERE uid = '$uid'";
                    $query_2 = mysqli_query($db, $sql_2);
                    /*while ($fetchData = $query_2->fetch_assoc()) {
                        $dados_array[] = $fetchData;
                    }*/

                    $fetchData = $query_2->fetch_assoc();
                    $dados_array[] = $fetchData;
                    $id_lider = $fetchData['uid'];

                    $consulta_pg = "SELECT * FROM pg WHERE id_lider = '$id_lider'";
                    $resultado_pg = mysqli_query($db, $consulta_pg);
                    $cont_pg = mysqli_num_rows($resultado_pg);

                    if ($cont_pg == 0) {

                        $inserir_pg_sql = "INSERT INTO pg VALUES (null,'$id_lider',1,1,1,'$data')";
                        $inserir_pg = mysqli_query($db, $inserir_pg_sql);

                        if ($inserir_pg) {
                            $consulta_pg_2 = "SELECT * FROM pg WHERE id_lider = '$id_lider'";
                            $resultado_pg_2 = mysqli_query($db, $consulta_pg_2);
                            $info_pg = $resultado_pg_2->fetch_assoc();
                            # code...
                            $id_pg = $info_pg['id'];

                            $consulta_progresso_sql = "SELECT * FROM check_licao WHERE id_lider = '$id_lider' AND id_pg = '$id_pg'";
                            $resultado_progresso = mysqli_query($db, $consulta_progresso_sql);
                            $cont_progresso = mysqli_num_rows($resultado_progresso);

                            if ($cont_progresso == 0) {
                                $inserir_progresso_sql = "INSERT INTO check_licao VALUES (null,'$id_lider',$id_pg,1,1,0,0,0,'$data')";
                                $inserir_progresso = mysqli_query($db, $inserir_progresso_sql);

                                if ($inserir_progresso) {
                                    /*$consulta_devocional_sql = "SELECT * FROM check_devocional WHERE id_lider = '$id_lider' AND id_pg = '$id_pg'";
                                    $resultado_devocional = mysqli_query($db, $consulta_devocional_sql);
                                    $cont_devocional = mysqli_num_rows($resultado_devocional);

                                    if ($cont_devocional == 0) {
                                        $inserir_devocional_sql = "INSERT INTO check_devocional VALUES (null,'$id_lider','$id_pg',1,1,1,0,'$data')";
                                        $inserir_devocional = mysqli_query($db, $inserir_devocional_sql);
                                        if ($inserir_devocional) {
                                        }
                                    }*/
                                } else {
                                }
                            }
                        }
                    } else {
                        $info_pg = $resultado_pg->fetch_assoc();
                        # code...
                        $id_pg = $info_pg['id'];

                        $consulta_progresso_sql = "SELECT * FROM check_licao WHERE id_lider = '$id_lider' AND id_pg = '$id_pg'";
                        $resultado_progresso = mysqli_query($db, $consulta_progresso_sql);
                        $cont_progresso = mysqli_num_rows($resultado_progresso);

                        if ($cont_progresso == 0) {
                            $inserir_progresso_sql = "INSERT INTO check_licao VALUES (null,'$id_lider',$id_pg,1,1,0,0,0,'$data')";
                            $inserir_progresso = mysqli_query($db, $inserir_progresso_sql);

                            if ($inserir_progresso) {
                                /*$consulta_devocional_sql = "SELECT * FROM check_devocional WHERE id_lider = '$id_lider' AND id_pg = '$id_pg'";
                                $resultado_devocional = mysqli_query($db, $consulta_devocional_sql);
                                $cont_devocional = mysqli_num_rows($resultado_devocional);

                                if ($cont_devocional == 0) {
                                    $inserir_devocional_sql = "INSERT INTO check_devocional VALUES (null,'$id_lider','$id_pg',1,1,1,0,'$data')";
                                    $inserir_devocional = mysqli_query($db, $inserir_devocional_sql);
                                    if ($inserir_devocional) {
                                    }
                                }*/
                            } else {
                            }
                        }
                    }
                } else {
                }
            }
        } else {
            $fetchData = $query->fetch_assoc();
            $dados_array[] = $fetchData;
            $id_lider = $fetchData['uid'];

            $consulta_pg = "SELECT * FROM pg WHERE id_lider = '$id_lider'";
            $resultado_pg = mysqli_query($db, $consulta_pg);
            $cont_pg = mysqli_num_rows($resultado_pg);

            if (!empty($timezone)) {
                $timezoneSeguro = mysqli_real_escape_string(
                    $db,
                    $timezone
                );

                $uidSeguro = mysqli_real_escape_string(
                    $db,
                    $uid
                );

                mysqli_query(
                    $db,
                    "UPDATE usuario
                    SET timezone = '$timezoneSeguro'
                    WHERE uid = '$uidSeguro'"
                );
            }

            if ($cont_pg == 0) {
                $inserir_pg_sql = "INSERT INTO pg VALUES (null,'$id_lider',1,1,1,'$data')";
                $inserir_pg = mysqli_query($db, $inserir_pg_sql);

                if ($inserir_pg) {
                    $consulta_pg_2 = "SELECT * FROM pg WHERE id_lider = '$id_lider'";
                    $resultado_pg_2 = mysqli_query($db, $consulta_pg_2);
                    $info_pg = $resultado_pg_2->fetch_assoc();
                    # code...
                    $id_pg = $info_pg['id'];

                    $consulta_progresso_sql = "SELECT * FROM check_licao WHERE id_lider = '$id_lider' AND id_pg = '$id_pg'";
                    $resultado_progresso = mysqli_query($db, $consulta_progresso_sql);
                    $cont_progresso = mysqli_num_rows($resultado_progresso);

                    if ($cont_progresso == 0) {
                        $inserir_progresso_sql = "INSERT INTO check_licao VALUES (null,'$id_lider',$id_pg,1,1,0,0,0,'$data')";
                        $inserir_progresso = mysqli_query($db, $inserir_progresso_sql);

                        if ($inserir_progresso) {
                            /*$consulta_devocional_sql = "SELECT * FROM check_devocional WHERE id_lider = '$id_lider' AND id_pg = '$id_pg'";
                            $resultado_devocional = mysqli_query($db, $consulta_devocional_sql);
                            $cont_devocional = mysqli_num_rows($resultado_devocional);

                            if ($cont_devocional == 0) {
                                $inserir_devocional_sql = "INSERT INTO check_devocional VALUES (null,'$id_lider','$id_pg',1,1,1,0,'$data')";
                                $inserir_devocional = mysqli_query($db, $inserir_devocional_sql);
                                if ($inserir_devocional) {
                                }
                            }*/
                        } else {
                        }
                    }
                }
            } else {
                $info_pg = $resultado_pg->fetch_assoc();
                # code...
                $id_pg = $info_pg['id'];

                $consulta_progresso_sql = "SELECT * FROM check_licao WHERE id_lider = '$id_lider' AND id_pg = '$id_pg'";
                $resultado_progresso = mysqli_query($db, $consulta_progresso_sql);
                $cont_progresso = mysqli_num_rows($resultado_progresso);

                if ($cont_progresso == 0) {
                    $inserir_progresso_sql = "INSERT INTO check_licao VALUES (null,'$id_lider',$id_pg,1,1,0,0,0,'$data')";
                    $inserir_progresso = mysqli_query($db, $inserir_progresso_sql);

                    if ($inserir_progresso) {
                        /*$consulta_devocional_sql = "SELECT * FROM check_devocional WHERE id_lider = '$id_lider' AND id_pg = '$id_pg'";
                        $resultado_devocional = mysqli_query($db, $consulta_devocional_sql);
                        $cont_devocional = mysqli_num_rows($resultado_devocional);

                        if ($cont_devocional == 0) {
                            $inserir_devocional_sql = "INSERT INTO check_devocional VALUES (null,'$id_lider','$id_pg',1,1,1,0,'$data')";
                            $inserir_devocional = mysqli_query($db, $inserir_devocional_sql);
                            if ($inserir_devocional) {
                            }
                        }*/
                    } else {
                    }
                }
            }
        }

        echo json_encode($dados_array);
    }

    public function update_token($id, $token)
    {
        require_once '../conexao.php';

        $data_hj = date('d/m/Y');

        if ($token == '') {
            echo json_encode("dados_vazios");
        } else {
            $update_usuario_sql = "UPDATE usuario SET token_alert = '$token' WHERE uid = '$id'";
            $update_usuario = mysqli_query($db, $update_usuario_sql);

            if ($update_usuario) {
                echo json_encode("sucesso");
            } else {
                echo json_encode("erro_na_edicao");
            }
        }
    }

    public function updateUser(
        $uid,
        $nome,
        $email,
        $telefone,
        $pais,
        $estado,
        $cidade,
        $foto
    ) {
        require_once '../conexao.php';

        if (
            empty($uid) ||
            empty($nome)
        ) {
            echo json_encode("dados_vazios");
            return;
        }

        // Segurança
        $uid = mysqli_real_escape_string($db, $uid);
        $nome = mysqli_real_escape_string($db, $nome);
        $email = mysqli_real_escape_string($db, $email);
        $telefone = mysqli_real_escape_string($db, $telefone);
        $pais = mysqli_real_escape_string($db, $pais);
        $estado = mysqli_real_escape_string($db, $estado);
        $cidade = mysqli_real_escape_string($db, $cidade);
        $foto = mysqli_real_escape_string($db, $foto);

        // Verifica se usuário existe
        $consulta = "
        SELECT uid
        FROM usuario
        WHERE uid = '$uid'
        LIMIT 1
    ";

        $resultado = mysqli_query(
            $db,
            $consulta
        );

        if (!$resultado) {
            echo json_encode("Database erro");
            return;
        }

        if (mysqli_num_rows($resultado) == 0) {
            echo json_encode("usuario_nao_encontrado");
            return;
        }

        // Atualiza usuário
        $sql = "
        UPDATE usuario
        SET
            nome = '$nome',
            email = '$email',
            telefone = '$telefone',
            pais = '$pais',
            estado = '$estado',
            cidade = '$cidade',
            foto = '$foto'
        WHERE uid = '$uid'
    ";

        $update = mysqli_query(
            $db,
            $sql
        );

        if ($update) {
            echo json_encode("sucesso");
        } else {
            echo json_encode("erro_na_edicao");
        }
    }

    public function getUIDUser($uid)
    {
        require_once '../conexao.php';

        $sql = "SELECT * FROM usuario WHERE uid = '$uid'";
        $query = mysqli_query($db, $sql);
        $cont = mysqli_num_rows($query);

        $dados_array = array();

        if ($cont == 0) {
            echo json_encode('erro');
        } else {
            while ($fetchData = $query->fetch_assoc()) {
                $dados_array[] = $fetchData;
            }
        }
        echo json_encode($dados_array);
    }

    public function getPG($idLider)
    {
        require_once '../conexao.php';

        if ($idLider == '') {
            echo json_encode("dados_vazios");
        } else {
            // CONSULTAR NA TABELA
            $consulta = "SELECT * FROM pg WHERE id_lider = '$idLider'";
            $resultado = mysqli_query($db, $consulta);
            $cont = mysqli_num_rows($resultado);

            if ($cont == 0) {
                echo json_encode("erro");
            } else {

                $dados = array();

                while ($fetchData = $resultado->fetch_assoc()) {
                    $dados[] = $fetchData;
                }
                echo json_encode($dados);
            }
        }
    }
    public function addPG($idLider)
    {
        require_once '../conexao.php';

        $data = date('d/m/Y');
        if ($idLider == '') {
            echo json_encode("dados_vazios");
        } else {

            // CONSULTAR NA TABELA
            $consulta = "SELECT * FROM pg WHERE id_lider = '$idLider'";
            $resultado = mysqli_query($db, $consulta);
            $cont = mysqli_num_rows($resultado);

            if ($cont == 0) {

                $inserir_pg_sql = "INSERT INTO pg VALUES (null,'$idLider',1,1,1,'','$data')";
                $inserir_pg = mysqli_query($db, $inserir_pg_sql);

                if ($inserir_pg) {

                    /*$consulta2 = "SELECT * FROM pg WHERE id_lider = '$idLider'";
                    $resultado2 = mysqli_query($db, $consulta2);

                    $dados = array();

                    while ($fetchData = $resultado2->fetch_assoc()) {
                        $dados[] = $fetchData;
                    }
                    echo json_encode($dados);*/
                    echo json_encode("sucesso");
                } else {
                    echo json_encode("erro_no_cadastro");
                }
            } else {
                echo json_encode("ja_cadastrado");
            }
        }
    }
    public function getLicaoAll()
    {
        require_once '../conexao.php';

        // CONSULTAR NA TABELA
        $consulta_1 = "SELECT * FROM licao";
        $resultado_1 = mysqli_query($db, $consulta_1);
        $cont_1 = mysqli_num_rows($resultado_1);

        if ($cont_1 == 0) {
            echo json_encode("erro");
        } else {

            $dados_1 = array();

            while ($fetchData_1 = $resultado_1->fetch_assoc()) {
                $dados_1[] = $fetchData_1;
            }
            echo json_encode($dados_1);
        }
    }

    public function addProgresso($idLider, $idPg)
    {
        require_once '../conexao.php';

        $data = date('d/m/Y');
        if ($idLider == '' || $idPg == '') {
            echo json_encode("dados_vazios");
        } else {
            // CONSULTAR NA TABELA
            $consulta_lider_sql = "SELECT * FROM lider WHERE id_lider = '$idLider'";
            $resultado_lider = mysqli_query($db, $consulta_lider_sql);
            $cont_lider = mysqli_num_rows($resultado_lider);

            $consulta_pg_sql = "SELECT * FROM pg WHERE id_lider = '$idLider'";
            $resultado_pg = mysqli_query($db, $consulta_pg_sql);
            $cont_pg = mysqli_num_rows($resultado_pg);

            if ($cont_lider == 0 || $cont_pg == 0) {

                $consulta_progresso_sql = "SELECT * FROM check_licao WHERE id_lider = '$idLider' AND id_pg = '$idPg'";
                $resultado_progresso = mysqli_query($db, $consulta_progresso_sql);
                $cont_progresso = mysqli_num_rows($resultado_progresso);

                if ($cont_progresso == 0) {
                    $inserir_progresso_sql = "INSERT INTO check_licao VALUES (null,'$idLider',$idPg,1,1,0,0,0,'$data')";
                    $inserir_progresso = mysqli_query($db, $inserir_progresso_sql);

                    if ($inserir_progresso) {

                        echo json_encode("sucesso");
                        /*$consulta_devocional_sql = "SELECT * FROM check_devocional WHERE id_lider = '$idLider' AND id_pg = '$idPg'";
                        $resultado_devocional = mysqli_query($db, $consulta_devocional_sql);
                        $cont_devocional = mysqli_num_rows($resultado_devocional);

                        if ($cont_devocional == 0) {
                            $inserir_devocional_sql = "INSERT INTO check_devocional VALUES (null,'$idLider','$idPg',1,1,1,0,'$data')";
                            $inserir_devocional = mysqli_query($db, $inserir_devocional_sql);
                            if ($inserir_devocional) {
                            }
                        }*/
                    } else {
                        echo json_encode("erro_no_cadastro_do_progresso");
                    }
                }
            } else {
                echo json_encode("dados_n_encontrado");
            }
        }
    }
    public function getProgressoLicaoAll($idLider, $idPG)
    {
        require_once '../conexao.php';

        if ($idLider == '' || $idPG == 0 || $idPG == '') {
            echo json_encode("dados_vazios");
        } else {
            // CONSULTAR NA TABELA
            $consulta_1 = "SELECT * FROM check_licao WHERE id_lider = '$idLider' AND id_pg = '$idPG'";
            $resultado_1 = mysqli_query($db, $consulta_1);
            $cont_1 = mysqli_num_rows($resultado_1);

            if ($cont_1 == 0) {
                echo json_encode("erro");
            } else {

                $dados_1 = array();

                while ($fetchData_1 = $resultado_1->fetch_assoc()) {
                    $dados_1[] = $fetchData_1;
                }
                echo json_encode($dados_1);
            }
        }
    }

    public function addcheckdevocional(
        $idLider,
        $idPg,
        $idModulo,
        $idLicao,
        $dia,
        $checkLicao
    ) {
        require_once '../conexao.php';

        $data = date('d/m/Y');

        // =====================================================
        // 1. VALIDAÇÃO
        // =====================================================

        if (
            empty($idLider) ||
            empty($idPg) ||
            empty($idModulo) ||
            empty($idLicao) ||
            empty($dia)
        ) {
            echo json_encode("dados_vazios");
            return;
        }

        $idPg = (int) $idPg;
        $idModulo = (int) $idModulo;
        $idLicao = (int) $idLicao;
        $dia = (int) $dia;
        $checkLicao = (int) $checkLicao;

        if ($dia < 1 || $dia > 6) {
            echo json_encode("dia_invalido");
            return;
        }

        if ($checkLicao !== 0 && $checkLicao !== 1) {
            echo json_encode("check_invalido");
            return;
        }

        // =====================================================
        // 2. VERIFICA SE O SEMANÁRIO JÁ ESTÁ LIBERADO
        // =====================================================

        $semanariosLiberados = $this->calcularSemanariosLiberados(
            $db,
            $idLider,
            $idPg,
            $idModulo,
            $idLicao
        );

        if ($dia > $semanariosLiberados) {
            echo json_encode("semanario_bloqueado");
            return;
        }

        // =====================================================
        // 2. VERIFICA SE O SEMANÁRIO JÁ EXISTE
        // =====================================================

        $stmt = $db->prepare("
        SELECT id
        FROM check_devocional
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND n_licao = ?
          AND dia = ?
        LIMIT 1
    ");

        $stmt->bind_param(
            "siiii",
            $idLider,
            $idPg,
            $idModulo,
            $idLicao,
            $dia
        );

        $stmt->execute();

        $resultado = $stmt->get_result();
        $existe = $resultado->num_rows > 0;


        // =====================================================
        // 3. ATUALIZA OU INSERE
        // =====================================================

        if ($existe) {

            $stmt = $db->prepare("
            UPDATE check_devocional
            SET check_devocional = ?,
                data = ?
            WHERE id_lider = ?
              AND id_pg = ?
              AND id_modulo = ?
              AND n_licao = ?
              AND dia = ?
        ");

            $stmt->bind_param(
                "issiiii",
                $checkLicao,
                $data,
                $idLider,
                $idPg,
                $idModulo,
                $idLicao,
                $dia
            );
        } else {

            $stmt = $db->prepare("
            INSERT INTO check_devocional
            (
                id_lider,
                id_pg,
                id_modulo,
                n_licao,
                dia,
                check_devocional,
                data
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

            $stmt->bind_param(
                "siiiiis",
                $idLider,
                $idPg,
                $idModulo,
                $idLicao,
                $dia,
                $checkLicao,
                $data
            );
        }


        // =====================================================
        // 4. SALVA
        // =====================================================

        if (!$stmt->execute()) {
            echo json_encode("erro");
            return;
        }


        // =====================================================
        // 5. VERIFICA SE A LIÇÃO CHEGOU EM 10/10
        // =====================================================

        if ($checkLicao === 1) {

            $this->atualizarPosicaoSeConcluida(
                $db,
                $idLider,
                $idPg,
                $idModulo,
                $idLicao
            );
        }


        // =====================================================
        // 6. RESPOSTA ÚNICA
        // =====================================================

        echo json_encode("sucesso");
    }

    function atualizarPosicaoSeConcluida(
        mysqli $db,
        string $uid,
        int $idPg,
        int $idModulo,
        int $nLicao
    ): bool {

        error_log(
            "AVANCO 1 => uid=$uid | pg=$idPg | modulo=$idModulo | licao=$nLicao"
        );

        // ==========================================
        // 1. VERIFICA A POSIÇÃO ATUAL DO PG
        // ==========================================

        $stmt = $db->prepare("
        SELECT id_modulo, n_licao
        FROM pg
        WHERE id = ?
          AND id_lider = ?
        LIMIT 1
    ");

        $stmt->bind_param(
            "is",
            $idPg,
            $uid
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        error_log(
            "AVANCO 2 => PG encontrados=" . $resultado->num_rows
        );

        if ($resultado->num_rows === 0) {
            return false;
        }

        $pg = $resultado->fetch_assoc();

        $moduloAtual = (int) $pg['id_modulo'];
        $licaoAtual = (int) $pg['n_licao'];

        error_log(
            "AVANCO 3 => atual=$moduloAtual/$licaoAtual | concluida=$idModulo/$nLicao"
        );

        if (
            $moduloAtual !== $idModulo ||
            $licaoAtual !== $nLicao
        ) {
            return false;
        }

        // ==========================================
        // 2. CALCULA O PROGRESSO
        // ==========================================

        $concluidos = 0;

        // LIÇÃO PRINCIPAL
        $stmt = $db->prepare("
        SELECT checks
        FROM check_licao
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND n_licao = ?
        LIMIT 1
    ");

        $stmt->bind_param(
            "siii",
            $uid,
            $idPg,
            $idModulo,
            $nLicao
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($row = $resultado->fetch_assoc()) {
            if ((int) $row['checks'] === 1) {
                $concluidos++;
            }
        }

        // 6 SEMANÁRIOS
        $stmt = $db->prepare("
        SELECT COUNT(DISTINCT dia) AS total
        FROM check_devocional
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND n_licao = ?
          AND check_devocional = 1
          AND dia BETWEEN 1 AND 6
    ");

        $stmt->bind_param(
            "siii",
            $uid,
            $idPg,
            $idModulo,
            $nLicao
        );

        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        $concluidos += min(
            (int) ($row['total'] ?? 0),
            6
        );

        // DESAFIO / VERSÍCULO / MISTÉRIO
        $stmt = $db->prepare("
        SELECT COUNT(DISTINCT tipo) AS total
        FROM check_atividade
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND n_licao = ?
          AND concluido = 1
          AND tipo IN ('desafio', 'versiculo', 'misterio')
    ");

        $stmt->bind_param(
            "siii",
            $uid,
            $idPg,
            $idModulo,
            $nLicao
        );

        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        $concluidos += min(
            (int) ($row['total'] ?? 0),
            3
        );

        error_log(
            "AVANCO 4 => concluidos=$concluidos"
        );

        if ($concluidos < 10) {
            return false;
        }

        // ==========================================
        // 3. PRÓXIMA POSIÇÃO
        // ==========================================

        $totalLicoes = $this->totalLicoesModulo($idModulo);

        error_log(
            "AVANCO TOTAL LICOES => modulo=$idModulo | total=$totalLicoes"
        );

        if ($totalLicoes === 0) {
            return false;
        }

        if ($nLicao < $totalLicoes) {

            $novoModulo = $idModulo;
            $novaLicao = $nLicao + 1;
        } else {

            if ($idModulo >= 5) {
                return false;
            }

            $novoModulo = $idModulo + 1;
            $novaLicao = 1;
        }

        // ==========================================
        // 4. UPDATE PG
        // ==========================================

        error_log(
            "AVANCO UPDATE => $idModulo/$nLicao -> $novoModulo/$novaLicao"
        );

        $stmt = $db->prepare("
        UPDATE pg
        SET id_modulo = ?,
            n_licao = ?
        WHERE id = ?
          AND id_lider = ?
          AND id_modulo = ?
          AND n_licao = ?
    ");

        $stmt->bind_param(
            "iiisii",
            $novoModulo,
            $novaLicao,
            $idPg,
            $uid,
            $idModulo,
            $nLicao
        );

        if (!$stmt->execute()) {
            error_log(
                "AVANCO ERRO UPDATE => " . $stmt->error
            );

            return false;
        }

        error_log(
            "AVANCO 5 => novo=$novoModulo/$novaLicao | affected=" . $stmt->affected_rows
        );

        return $stmt->affected_rows > 0;
    }

    public function getProgressoModulo(
        $idLider,
        $idPg,
        $idModulo
    ) {
        require_once '../conexao.php';

        if (
            empty($idLider) ||
            empty($idPg) ||
            empty($idModulo)
        ) {
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Dados incompletos.'
            ]);
            return;
        }

        $progressoLicoes = [];

        // =====================================================
        // 1. LIÇÃO PRINCIPAL
        // =====================================================

        $sql = "
        SELECT n_licao, checks
        FROM check_licao
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
    ";

        $stmt = $db->prepare($sql);

        $stmt->bind_param(
            "sii",
            $idLider,
            $idPg,
            $idModulo
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        while ($row = $resultado->fetch_assoc()) {
            $nLicao = (int) $row['n_licao'];

            if (!isset($progressoLicoes[$nLicao])) {
                $progressoLicoes[$nLicao] = [
                    'nLicao' => $nLicao,
                    'licao' => false,
                    'semanarios' => 0,
                    'desafio' => false,
                    'versiculo' => false,
                    'misterio' => false
                ];
            }

            $progressoLicoes[$nLicao]['licao'] =
                ((int) $row['checks'] === 1);
        }

        // =====================================================
        // 2. SEMANÁRIOS
        // =====================================================

        $sql = "
        SELECT
            n_licao,
            COUNT(DISTINCT dia) AS total
        FROM check_devocional
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND dia BETWEEN 1 AND 6
          AND check_devocional = 1
        GROUP BY n_licao
    ";

        $stmt = $db->prepare($sql);

        $stmt->bind_param(
            "sii",
            $idLider,
            $idPg,
            $idModulo
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        while ($row = $resultado->fetch_assoc()) {
            $nLicao = (int) $row['n_licao'];

            if (!isset($progressoLicoes[$nLicao])) {
                $progressoLicoes[$nLicao] = [
                    'nLicao' => $nLicao,
                    'licao' => false,
                    'semanarios' => 0,
                    'desafio' => false,
                    'versiculo' => false,
                    'misterio' => false
                ];
            }

            $progressoLicoes[$nLicao]['semanarios'] =
                (int) $row['total'];
        }

        // =====================================================
        // 3. DESAFIO / VERSÍCULO / MISTÉRIO
        // =====================================================

        $sql = "
        SELECT n_licao, tipo, concluido
        FROM check_atividade
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
    ";

        $stmt = $db->prepare($sql);

        $stmt->bind_param(
            "sii",
            $idLider,
            $idPg,
            $idModulo
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        while ($row = $resultado->fetch_assoc()) {
            $nLicao = (int) $row['n_licao'];

            if (!isset($progressoLicoes[$nLicao])) {
                $progressoLicoes[$nLicao] = [
                    'nLicao' => $nLicao,
                    'licao' => false,
                    'semanarios' => 0,
                    'desafio' => false,
                    'versiculo' => false,
                    'misterio' => false
                ];
            }

            $concluido = ((int) $row['concluido'] === 1);

            switch ($row['tipo']) {
                case 'desafio':
                    $progressoLicoes[$nLicao]['desafio'] = $concluido;
                    break;

                case 'versiculo':
                    $progressoLicoes[$nLicao]['versiculo'] = $concluido;
                    break;

                case 'misterio':
                    $progressoLicoes[$nLicao]['misterio'] = $concluido;
                    break;
            }
        }

        // =====================================================
        // 4. CALCULA TOTAL DE CADA LIÇÃO
        // =====================================================

        $retorno = [];

        foreach ($progressoLicoes as $nLicao => $dados) {

            $concluidos = 0;

            if ($dados['licao']) {
                $concluidos++;
            }

            $concluidos += $dados['semanarios'];

            if ($dados['desafio']) {
                $concluidos++;
            }

            if ($dados['versiculo']) {
                $concluidos++;
            }

            if ($dados['misterio']) {
                $concluidos++;
            }

            $total = 10;

            $porcentagem = (int) round(
                ($concluidos / $total) * 100
            );

            $retorno[] = [
                'nLicao' => (int) $nLicao,
                'concluidos' => $concluidos,
                'total' => $total,
                'porcentagem' => $porcentagem,
                'completo' => $concluidos >= $total
            ];
        }

        usort($retorno, function ($a, $b) {
            return $a['nLicao'] <=> $b['nLicao'];
        });

        echo json_encode([
            'sucesso' => true,
            'progresso' => $retorno
        ]);
    }

    public function getProgressoLicao($idLider, $idPG, $idModulo, $nLicao)
    {
        require_once '../conexao.php';

        $data_hj = date('d/m/Y');

        if ($idLider == '' || $idPG == '' || $idModulo == '' || $nLicao == '') {
            echo json_encode("dados_vazios");
        } else {
            // CONSULTAR NA TABELA
            $consulta_1 = "SELECT * FROM check_licao WHERE id_lider = '$idLider' AND id_pg = '$idPG' AND id_modulo = '$idModulo' AND n_licao = '$nLicao'";
            $resultado_1 = mysqli_query($db, $consulta_1);
            $cont_1 = mysqli_num_rows($resultado_1);

            if ($cont_1 == 0) {
                $inserir_progresso_novo_sql = "INSERT INTO check_licao VALUES (null,'$idLider','$idPG','$idModulo','$nLicao',0,0,0,'$data_hj')";
                mysqli_query($db, $inserir_progresso_novo_sql);
            } else {

                $dados_1 = array();

                while ($fetchData_1 = $resultado_1->fetch_assoc()) {
                    $dados_1[] = $fetchData_1;
                }
                echo json_encode($dados_1);
            }
        }
    }

    public function getProgressoDevocional($idLider, $idPG, $idModulo, $nLicao)
    {
        require_once '../conexao.php';

        if ($idLider == '' || $idPG == '' || $idModulo == '' || $nLicao == '') {
            echo json_encode("dados_vazios");
        } else {
            // CONSULTAR NA TABELA
            $consulta_1 = "SELECT * FROM check_devocional WHERE id_lider = '$idLider' AND id_pg = '$idPG' AND id_modulo = '$idModulo' AND n_licao = '$nLicao'";
            $resultado_1 = mysqli_query($db, $consulta_1);
            $cont_1 = mysqli_num_rows($resultado_1);

            if ($cont_1 == 0) {
                echo json_encode("erro");
            } else {

                $dados_1 = array();

                while ($fetchData_1 = $resultado_1->fetch_assoc()) {
                    $dados_1[] = $fetchData_1;
                }
                echo json_encode($dados_1);
            }
        }
    }

    public function addProgressoLicao(
        $idLider,
        $idPg,
        $idModulo,
        $idLicao,
        $checkLicao
    ) {
        require_once '../conexao.php';

        $data = date('d/m/Y');

        if (
            empty($idLider) ||
            empty($idPg) ||
            empty($idModulo) ||
            empty($idLicao)
        ) {
            echo json_encode("dados_vazios");
            return;
        }

        $idPg = (int) $idPg;
        $idModulo = (int) $idModulo;
        $idLicao = (int) $idLicao;
        $checkLicao = (int) $checkLicao;

        if ($checkLicao !== 0 && $checkLicao !== 1) {
            echo json_encode("check_invalido");
            return;
        }

        // Verifica se a lição já possui progresso
        $stmt = $db->prepare("
        SELECT id
        FROM check_licao
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND n_licao = ?
        LIMIT 1
    ");

        $stmt->bind_param(
            "siii",
            $idLider,
            $idPg,
            $idModulo,
            $idLicao
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        // Se ainda não existe, cria
        if ($resultado->num_rows === 0) {

            $stmt = $db->prepare("
            INSERT INTO check_licao
            (
                id_lider,
                id_pg,
                id_modulo,
                n_licao,
                checks,
                likes,
                saves,
                data,
                data_conclusao
            )
            VALUES (?, ?, ?, ?, ?, 0, 0, ?, NULL)
        ");

            $stmt->bind_param(
                "siiiis",
                $idLider,
                $idPg,
                $idModulo,
                $idLicao,
                $checkLicao,
                $data
            );

            if (!$stmt->execute()) {
                echo json_encode("erro_no_cadastro");
                return;
            }

            $idProgresso = $db->insert_id;
        } else {

            $registro = $resultado->fetch_assoc();
            $idProgresso = (int) $registro['id'];
        }

        echo json_encode([
            "sucesso" => true,
            "id" => $idProgresso
        ]);
    }

    public function getProgressoGeral(
        mysqli $db,
        string $idLider,
        int $idPg
    ): array {

        $modulos = [
            1 => 8,
            2 => 8,
            3 => 10,
            4 => 10,
            5 => 8,
        ];

        // --------------------------------------------------
        // LIÇÕES
        // --------------------------------------------------

        $licoes = [];

        $stmt = $db->prepare("
        SELECT
            id_modulo,
            n_licao,
            MAX(checks) AS concluido
        FROM check_licao
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo BETWEEN 1 AND 5
        GROUP BY id_modulo, n_licao
    ");

        $stmt->bind_param("si", $idLider, $idPg);
        $stmt->execute();

        $resultado = $stmt->get_result();

        while ($row = $resultado->fetch_assoc()) {
            $modulo = (int)$row['id_modulo'];
            $licao = (int)$row['n_licao'];

            $licoes[$modulo][$licao] =
                (int)$row['concluido'] === 1 ? 1 : 0;
        }

        $stmt->close();

        // --------------------------------------------------
        // DEVOCIONAIS / SEMANÁRIOS
        // --------------------------------------------------

        $devocionais = [];

        $stmt = $db->prepare("
        SELECT
            id_modulo,
            n_licao,
            COUNT(DISTINCT dia) AS total
        FROM check_devocional
        WHERE id_lider = ?
          AND id_pg = ?
          AND check_devocional = 1
          AND dia BETWEEN 1 AND 6
          AND id_modulo BETWEEN 1 AND 5
        GROUP BY id_modulo, n_licao
    ");

        $stmt->bind_param("si", $idLider, $idPg);
        $stmt->execute();

        $resultado = $stmt->get_result();

        while ($row = $resultado->fetch_assoc()) {
            $modulo = (int)$row['id_modulo'];
            $licao = (int)$row['n_licao'];

            $devocionais[$modulo][$licao] =
                min(6, (int)$row['total']);
        }

        $stmt->close();

        // --------------------------------------------------
        // DESAFIO / VERSÍCULO / MISTÉRIO
        // --------------------------------------------------

        $atividades = [];

        $stmt = $db->prepare("
        SELECT
            id_modulo,
            n_licao,
            COUNT(DISTINCT tipo) AS total
        FROM check_atividade
        WHERE id_lider = ?
          AND id_pg = ?
          AND concluido = 1
          AND tipo IN ('desafio', 'versiculo', 'misterio')
          AND id_modulo BETWEEN 1 AND 5
        GROUP BY id_modulo, n_licao
    ");

        $stmt->bind_param("si", $idLider, $idPg);
        $stmt->execute();

        $resultado = $stmt->get_result();

        while ($row = $resultado->fetch_assoc()) {
            $modulo = (int)$row['id_modulo'];
            $licao = (int)$row['n_licao'];

            $atividades[$modulo][$licao] =
                min(3, (int)$row['total']);
        }

        $stmt->close();

        // --------------------------------------------------
        // MONTA RESULTADO DOS 5 MÓDULOS
        // --------------------------------------------------

        $resultadoModulos = [];

        $pontosGerais = 0;
        $totalGeral = 0;
        $licoesConcluidasGeral = 0;
        $totalLicoesGeral = 0;

        foreach ($modulos as $idModulo => $totalLicoes) {

            $pontosModulo = 0;
            $licoesConcluidas = 0;

            for ($nLicao = 1; $nLicao <= $totalLicoes; $nLicao++) {

                $pontosLicao = 0;

                // 1 ponto - lição principal
                $pontosLicao +=
                    $licoes[$idModulo][$nLicao] ?? 0;

                // até 6 pontos - semanários
                $pontosLicao +=
                    $devocionais[$idModulo][$nLicao] ?? 0;

                // até 3 pontos - desafio, versículo e mistério
                $pontosLicao +=
                    $atividades[$idModulo][$nLicao] ?? 0;

                // Segurança
                $pontosLicao = min(10, $pontosLicao);

                $pontosModulo += $pontosLicao;

                if ($pontosLicao === 10) {
                    $licoesConcluidas++;
                }
            }

            $totalPontosModulo = $totalLicoes * 10;

            $porcentagemModulo = $totalPontosModulo > 0
                ? (int)round(($pontosModulo / $totalPontosModulo) * 100)
                : 0;

            $resultadoModulos[] = [
                'idModulo' => $idModulo,
                'pontos' => $pontosModulo,
                'total' => $totalPontosModulo,
                'porcentagem' => $porcentagemModulo,
                'licoesConcluidas' => $licoesConcluidas,
                'totalLicoes' => $totalLicoes,
                'completo' => $pontosModulo >= $totalPontosModulo,
            ];

            $pontosGerais += $pontosModulo;
            $totalGeral += $totalPontosModulo;

            $licoesConcluidasGeral += $licoesConcluidas;
            $totalLicoesGeral += $totalLicoes;
        }

        // --------------------------------------------------
        // RESULTADO GERAL
        // --------------------------------------------------

        $porcentagemGeral = $totalGeral > 0
            ? (int)round(($pontosGerais / $totalGeral) * 100)
            : 0;

        return [
            'sucesso' => true,
            'pontos' => $pontosGerais,
            'total' => $totalGeral,
            'porcentagem' => $porcentagemGeral,
            'licoesConcluidas' => $licoesConcluidasGeral,
            'totalLicoes' => $totalLicoesGeral,
            'modulos' => $resultadoModulos,
        ];
    }
    public function uploadProgresso($id, $idLider, $idPg, $idModulo, $idLicao)
    {
        require_once '../conexao.php';

        $nova_licao = $idLicao + 1;

        $id_novo_modulo = '';
        $id_nova_licao = '';

        $consulta_pg = "SELECT * FROM pg WHERE id = '$idPg'";
        $resultado_pg = mysqli_query($db, $consulta_pg);
        $cont_pg = mysqli_num_rows($resultado_pg);
        $info_pg = $resultado_pg->fetch_assoc();

        // limites para mudar o módulo
        if ($idModulo == 1 || $idModulo == 2) {
            $limite = 8;
        } else if ($idModulo == 3 || $idModulo == 4) {
            $limite = 10;
        } else {
        }

        if ($nova_licao > $limite) {
            $id_novo_modulo = $idModulo + 1;
            $id_nova_licao = '1';
        } else {
            $id_novo_modulo = $idModulo;
            $id_nova_licao = $nova_licao;
        }

        $data_hj = date('d/m/Y');

        if ($id == '') {
            echo json_encode("dados_vazios");
        } else {

            $update_progresso_sql = "UPDATE check_licao SET checks = 1, data = '$data_hj' WHERE id = '$id'";
            $update_progresso = mysqli_query($db, $update_progresso_sql);

            if ($update_progresso) {


                $consulta_progresso_novo = "SELECT * FROM check_licao WHERE id_pg = '$idPg' AND id_lider = '$idLider' AND id_modulo = $id_novo_modulo AND n_licao = '$id_nova_licao'";
                $resultado_progresso = mysqli_query($db, $consulta_progresso_novo);
                $cont_progresso = mysqli_num_rows($resultado_progresso);

                if ($cont_progresso == 0) {
                    $inserir_progresso_novo_sql = "INSERT INTO check_licao VALUES (null,'$idLider','$idPg','$id_novo_modulo','$id_nova_licao',0,0,0,'$data')";
                    mysqli_query($db, $inserir_progresso_novo_sql);
                }

                if ($info_pg['n_licao'] == $idLicao) {
                    $atualizar_pg_sql = "UPDATE pg SET id_modulo = $id_novo_modulo, n_licao = '$id_nova_licao' WHERE id = '$idPg'";
                    mysqli_query($db, $atualizar_pg_sql);
                }
                echo json_encode("sucesso");
            } else {
                echo json_encode("erro_na_edicao");
            }
        }
    }
    public function finalizouLicao(
        $id,
        $idLider,
        $idPg,
        $idModulo,
        $idLicao
    ) {
        require_once '../conexao.php';

        $data_hj = date('d/m/Y');

        // =====================================================
        // 1. VALIDAÇÃO
        // =====================================================

        if (
            empty($id) ||
            empty($idLider) ||
            empty($idPg) ||
            empty($idModulo) ||
            empty($idLicao)
        ) {
            echo json_encode("dados_vazios");
            return;
        }

        $id = (int) $id;
        $idPg = (int) $idPg;
        $idModulo = (int) $idModulo;
        $idLicao = (int) $idLicao;


        // =====================================================
        // 2. BUSCA O PROGRESSO DA LIÇÃO
        // =====================================================

        $stmt = $db->prepare("
        SELECT checks
        FROM check_licao
        WHERE id = ?
          AND id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND n_licao = ?
        LIMIT 1
    ");

        $stmt->bind_param(
            "isiii",
            $id,
            $idLider,
            $idPg,
            $idModulo,
            $idLicao
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 0) {
            echo json_encode("erro_progresso_nao_encontrado");
            return;
        }

        $progresso = $resultado->fetch_assoc();


        // =====================================================
        // 3. MARCA A LIÇÃO PRINCIPAL COMO CONCLUÍDA
        // =====================================================

        if ((int) $progresso['checks'] !== 1) {

            $stmt = $db->prepare("
            UPDATE check_licao
            SET checks = 1,
                data = ?,
                data_conclusao = NOW()
            WHERE id = ?
              AND id_lider = ?
              AND id_pg = ?
              AND id_modulo = ?
              AND n_licao = ?
        ");

            $stmt->bind_param(
                "sisiii",
                $data_hj,
                $id,
                $idLider,
                $idPg,
                $idModulo,
                $idLicao
            );

            if (!$stmt->execute()) {
                echo json_encode("erro_na_edicao");
                return;
            }
        }

        // =====================================================
        // 4. GARANTE QUE O SEMANÁRIO DIA 1 EXISTA
        // =====================================================

        $stmt = $db->prepare("
        SELECT id
        FROM check_devocional
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND n_licao = ?
          AND dia = 1
        LIMIT 1
    ");

        $stmt->bind_param(
            "siii",
            $idLider,
            $idPg,
            $idModulo,
            $idLicao
        );

        $stmt->execute();

        $resultadoDevocional = $stmt->get_result();

        if ($resultadoDevocional->num_rows === 0) {

            $stmt = $db->prepare("
            INSERT INTO check_devocional
            (
                id_lider,
                id_pg,
                id_modulo,
                n_licao,
                dia,
                check_devocional,
                data
            )
            VALUES (?, ?, ?, ?, 1, 0, ?)
        ");

            $stmt->bind_param(
                "siiis",
                $idLider,
                $idPg,
                $idModulo,
                $idLicao,
                $data_hj
            );

            if (!$stmt->execute()) {
                echo json_encode("erro_ao_criar_semanario");
                return;
            }
        }


        // =====================================================
        // 5. VERIFICA SE AGORA A LIÇÃO CHEGOU EM 10/10
        // =====================================================

        $this->atualizarPosicaoSeConcluida(
            $db,
            $idLider,
            $idPg,
            $idModulo,
            $idLicao
        );


        // =====================================================
        // 6. RESPOSTA ÚNICA
        // =====================================================

        echo json_encode("sucesso");
    }

    public function uploadProgressoLike($id, $like)
    {
        require_once '../conexao.php';

        if ($id == '') {
            echo json_encode("dados_vazios");
        } else {

            $update_progresso_sql = "UPDATE check_licao SET likes = $like WHERE id = '$id'";
            $update_progresso = mysqli_query($db, $update_progresso_sql);

            if ($update_progresso) {
                echo json_encode("sucesso");
            } else {
                echo json_encode("erro_na_edicao");
            }
        }
    }
    public function uploadProgressoSaves($id, $save)
    {
        require_once '../conexao.php';

        if ($id == '') {
            echo json_encode("dados_vazios");
        } else {

            $update_progresso_sql = "UPDATE check_licao SET saves = $save WHERE id = '$id'";
            $update_progresso = mysqli_query($db, $update_progresso_sql);

            if ($update_progresso) {
                echo json_encode("sucesso");
            } else {
                echo json_encode("erro_na_edicao");
            }
        }
    }

    public function uploadLikeModulo($uid, $idModulo, $like)
    {
        require_once '../conexao.php';

        if ($uid == '' || $idModulo == '') {
            echo json_encode("dados_vazios");
        } else {

            $consulta = "SELECT * FROM fav_saves_modulos WHERE uid = '$uid' AND idModulo = '$idModulo'";
            $resultado = mysqli_query($db, $consulta);
            $cont = mysqli_num_rows($resultado);

            if ($cont == 0) {
                $inserir_like_modulo_sql = "INSERT INTO fav_saves_modulos VALUES (null,'$uid','$idModulo','$like',0)";
                $inserir_like_modulo = mysqli_query($db, $inserir_like_modulo_sql);
                if ($inserir_like_modulo) {
                    echo json_encode("sucesso");
                } else {
                    echo json_encode("erro");
                }
            } else {
                $update_like_modulo_sql = "UPDATE fav_saves_modulos SET is_fav = $like WHERE uid = '$uid' AND idModulo = '$idModulo'";
                $update_like_modulo = mysqli_query($db, $update_like_modulo_sql);

                if ($update_like_modulo) {
                    echo json_encode("sucesso");
                } else {
                    echo json_encode("erro_na_edicao");
                }
            }
        }
    }

    public function uploadSavedModulo($uid, $idModulo, $saves)
    {
        require_once '../conexao.php';

        if ($uid == '' || $idModulo == '') {
            echo json_encode("dados_vazios");
        } else {

            $consulta = "SELECT * FROM fav_saves_modulos WHERE uid = '$uid' AND idModulo = '$idModulo'";
            $resultado = mysqli_query($db, $consulta);
            $cont = mysqli_num_rows($resultado);

            if ($cont == 0) {
                $inserir_saves_modulo_sql = "INSERT INTO fav_saves_modulos VALUES (null,'$uid','$idModulo',0,'$saves')";
                $inserir_saves_modulo = mysqli_query($db, $inserir_saves_modulo_sql);
                if ($inserir_saves_modulo) {
                    echo json_encode("sucesso");
                } else {
                    echo json_encode("erro");
                }
            } else {
                $update_saves_modulo_sql = "UPDATE fav_saves_modulos SET is_save = $saves WHERE uid = '$uid' AND idModulo = '$idModulo'";
                $update_saves_modulo = mysqli_query($db, $update_saves_modulo_sql);

                if ($update_saves_modulo) {
                    echo json_encode("sucesso");
                } else {
                    echo json_encode("erro_na_edicao");
                }
            }
        }
    }

    public function getSemanariosLiberados(
        $idLider,
        $idPg,
        $idModulo,
        $nLicao
    ) {
        require_once '../conexao.php';

        // =====================================================
        // 1. VALIDAÇÃO
        // =====================================================

        if (
            empty($idLider) ||
            empty($idPg) ||
            empty($idModulo) ||
            empty($nLicao)
        ) {
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'dados_vazios'
            ]);
            return;
        }

        $idPg = (int) $idPg;
        $idModulo = (int) $idModulo;
        $nLicao = (int) $nLicao;

        // =====================================================
        // 2. CALCULA QUANTOS SEMANÁRIOS ESTÃO LIBERADOS
        // =====================================================

        $liberados = $this->calcularSemanariosLiberados(
            $db,
            $idLider,
            $idPg,
            $idModulo,
            $nLicao
        );

        // =====================================================
        // 3. RESPOSTA
        // =====================================================

        echo json_encode([
            'sucesso' => true,
            'liberados' => $liberados
        ]);
    }

    public function getQtdDevocional($idLider, $idPG, $idModulo, $nLicao)
    {
        require_once '../conexao.php';

        if ($idLider == 0 || $idLider == '' || $idPG == 0 || $idPG == '' || $idModulo == 0 || $idModulo == '' || $nLicao == 0 || $nLicao == '') {
            echo json_encode("dados_vazios");
        } else {
            // CONSULTAR NA TABELA
            $consulta_1 = "SELECT * FROM check_devocional WHERE id_lider = '$idLider' AND id_pg = '$idPG' AND id_modulo = '$idModulo' AND n_licao = '$nLicao' AND check_devocional = 1";
            $resultado_1 = mysqli_query($db, $consulta_1);
            $cont_1 = mysqli_num_rows($resultado_1);
            echo json_encode($cont_1);
        }
    }

    public function getCriancasLider($idLider, $idPG)
    {
        require_once '../conexao.php';
        if ($idLider == '' || $idPG == '') {
            echo json_encode("dados_vazios");
        } else {
            // CONSULTAR NA TABELA
            $consulta = "SELECT * FROM criancas WHERE id_lider = '$idLider' AND id_pg = '$idPG'";
            $resultado = mysqli_query($db, $consulta);
            $dados = array();

            while ($fetchData = $resultado->fetch_assoc()) {
                $dados[] = $fetchData;
            }
            echo json_encode($dados);
        }
    }

    public function addCriancasLider($idLider, $idPG, $nome, $idade)
    {
        require_once '../conexao.php';
        $data_hj = date('d/m/Y');
        if ($idLider == '' || $idPG == '' || $nome == '' || $idade == '') {
            echo json_encode("dados_vazios");
        } else {
            // CONSULTAR NA TABELA
            $consulta = "SELECT * FROM criancas WHERE id_lider = '$idLider' AND id_pg = '$idPG' AND nome = '$nome' AND data_nascimento = '$idade'";
            $resultado = mysqli_query($db, $consulta);
            $cont = mysqli_num_rows($resultado);

            if ($cont == 0) {
                // add
                $inserir_criancas_sql = "INSERT INTO criancas VALUES (null,'$nome','$idade','$idLider','$idPG','$data_hj')";
                $inserir_criancas = mysqli_query($db, $inserir_criancas_sql);
                if ($inserir_criancas) {
                    echo json_encode("sucesso");
                } else {
                    echo json_encode("erro");
                }
            } else {
                // ja cadastrado
                echo json_encode("ja_cadastrado");
            }
        }
    }

    public function editCriancasLider($idLider, $idPG, $idCriaca, $nome, $idade)
    {
        require_once '../conexao.php';

        if ($idLider == '' || $idPG == '' || $nome == '' || $idade == '' || $idCriaca == '') {
            echo json_encode("dados_vazios");
        } else {

            $consulta = "SELECT * FROM criancas WHERE id = '$idCriaca'";
            $resultado = mysqli_query($db, $consulta);
            $cont = mysqli_num_rows($resultado);

            if ($cont == 0) {
                echo json_encode("crianca_n_encontrada");
            } else {
                $update_anotacao_sql = "UPDATE criancas SET nome = '$nome', data_nascimento = '$idade' WHERE id = '$idCriaca'";
                $update_anotacao = mysqli_query($db, $update_anotacao_sql);

                if ($update_anotacao) {
                    echo json_encode("sucesso");
                } else {
                    echo json_encode("erro_na_edicao");
                }
            }
        }
    }
    public function deleteCriancasLider($id)
    {
        require_once '../conexao.php';

        if ($id == 0 || $id == '') {
            echo json_encode("dados_vazios");
        } else {

            $delete_criancas_sql = "DELETE FROM criancas WHERE id = '$id'";
            $delete_criancas = mysqli_query($db, $delete_criancas_sql);

            $atualizar_autoincrement = "ALTER TABLE criancas AUTO_INCREMENT = 1";
            if ($delete_criancas) {
                mysqli_query($db, $atualizar_autoincrement);
                echo json_encode("sucesso");
            } else {
                echo json_encode("erro_na_exclusao");
            }
        }
    }

    public function editPGHorario($idPG, $horario)
    {
        require_once '../conexao.php';

        if ($idPG == '' || $horario == '') {
            echo json_encode("dados_vazios");
        } else {

            $consulta = "SELECT * FROM pg WHERE id = '$idPG'";
            $resultado = mysqli_query($db, $consulta);
            $cont = mysqli_num_rows($resultado);

            if ($cont == 0) {
                echo json_encode("pg_n_encontrado");
            } else {
                $update_anotacao_sql = "UPDATE pg SET hora_notificacao = '$horario' WHERE id = '$idPG'";
                $update_anotacao = mysqli_query($db, $update_anotacao_sql);

                if ($update_anotacao) {
                    echo json_encode("sucesso");
                } else {
                    echo json_encode("erro_na_edicao");
                }
            }
        }
    }
    public function getStatusModulos($idLider)
    {
        require_once '../conexao.php';

        if ($idLider == '') {
            echo json_encode("dados_vazios");
        } else {
            // CONSULTAR NA TABELA
            $consulta = "SELECT * FROM fav_saves_modulos WHERE uid = '$idLider'";
            $resultado = mysqli_query($db, $consulta);
            $cont = mysqli_num_rows($resultado);

            if ($cont == 0) {
                echo json_encode("erro");
            } else {
                $dados = array();
                while ($fetchData = $resultado->fetch_assoc()) {
                    $dados[] = $fetchData;
                }
                echo json_encode($dados);
            }
        }
    }

    public function getAnotacoes($idLider)
    {
        require_once '../conexao.php';

        if ($idLider == 0 || $idLider == '') {
            echo json_encode("dados_vazios");
        } else {
            // CONSULTAR NA TABELA
            $consulta = "SELECT * FROM anotacoes WHERE id_lider = '$idLider'";
            $resultado = mysqli_query($db, $consulta);
            $cont = mysqli_num_rows($resultado);

            if ($cont == 0) {
                echo json_encode("erro");
            } else {

                $dados = array();

                while ($fetchData = $resultado->fetch_assoc()) {
                    $dados[] = $fetchData;
                }
                echo json_encode($dados);
            }
        }
    }
    public function addAnotacoes($idLider, $title, $texto, $cor)
    {
        require_once '../conexao.php';

        $data = date('d/m/Y');

        if ($idLider == 0 || $idLider == '' || $title == '' || $texto == '' || $cor == '') {
            echo json_encode("dados_vazios");
        } else {

            $inserir_anotacao_sql = "INSERT INTO anotacoes VALUES (null,'$idLider','$title','$texto','$cor','1','$data')";
            $inserir_anotacao = mysqli_query($db, $inserir_anotacao_sql);

            if ($inserir_anotacao) {

                $consulta2 = "SELECT * FROM anotacoes WHERE id_lider = '$idLider' AND title = '$title' AND texto = '$texto'";
                $resultado2 = mysqli_query($db, $consulta2);

                $dados = array();

                while ($fetchData = $resultado2->fetch_assoc()) {
                    $dados[] = $fetchData;
                }
                echo json_encode($dados);
            } else {
                echo json_encode("erro_no_cadastro");
            }
        }
    }


    public function addMomentoOracao($idModulo, $texto)
    {
        require_once '../conexao.php';

        $data = date('Y-m-d');

        if ($idModulo == 0 || $texto == '') {
            echo json_encode("dados_vazios");
        } else {

            $inserir_anotacao_sql = "INSERT INTO momento_oracao VALUES (null,'$idModulo','$texto','$data')";
            $inserir_anotacao = mysqli_query($db, $inserir_anotacao_sql);

            if ($inserir_anotacao) {
                echo "<script>
                    alert('Sucesso!');
                    window.location.href = '../index.php';
                </script>";
            } else {
                echo "<script>
                    alert('Erro!');
                    window.location.href = '../index.php';
                </script>";
            }
        }
    }

    public function addPergunteaoPai($idModulo, $nLicao, $texto)
    {
        require_once '../conexao.php';

        $data = date('Y-m-d');

        if ($idModulo == 0 || $nLicao == 0 || $texto == '') {
            echo json_encode("dados_vazios");
        } else {

            $inserir_anotacao_sql = "INSERT INTO pergunte_ao_pai VALUES (null,'$idModulo','$nLicao','$texto','$data')";
            $inserir_anotacao = mysqli_query($db, $inserir_anotacao_sql);

            if ($inserir_anotacao) {
                echo "<script>
                    alert('Sucesso!');
                    window.location.href = '../index.php';
                </script>";
            } else {
                echo "<script>
                    alert('Erro!');
                    window.location.href = '../index.php';
                </script>";
            }
        }
    }

    public function addDesafioLicao($idModulo, $nLicao, $titulo, $desafio)
    {
        require_once '../conexao.php';

        if ($idModulo == 0 || $nLicao == 0 || $titulo == '' || $desafio == '') {
            echo json_encode("dados_vazios");
        } else {
            $desafio = mysqli_real_escape_string($db, $desafio);

            $inserir_desafio_licao_sql = "INSERT INTO desafio_licao VALUES(null,$idModulo,$nLicao,'$titulo','$desafio')";
            $inserir_desafio_licao = mysqli_query($db, $inserir_desafio_licao_sql);

            if ($inserir_desafio_licao) {
                echo "<script>
                    alert('Sucesso!');
                    window.location.href = '../index.php';
                </script>";
            } else {
                echo "<script>
                    alert('Erro!');
                    window.location.href = '../index.php';
                </script>";
            }
        }
    }

    public function uploadAnotacoes($id, $title, $texto, $checking, $cor)
    {
        require_once '../conexao.php';

        $data = date('d/m/Y');
        if ($id == 0 || $id == '' || $title == '' || $texto == '' || $checking == 0 || $checking == '' || $cor == '') {
            echo json_encode("dados_vazios");
        } else {

            $update_anotacao_sql = "UPDATE anotacoes SET title = '$title', texto = '$texto', checking = '$checking', cor = '$cor', data = '$data' WHERE id = '$id'";
            $update_anotacao = mysqli_query($db, $update_anotacao_sql);

            if ($update_anotacao) {
                echo json_encode("sucesso");
            } else {
                echo json_encode("erro_na_edicao");
            }
        }
    }
    public function deleteAnotacoes($id)
    {
        require_once '../conexao.php';

        if ($id == 0 || $id == '') {
            echo json_encode("dados_vazios");
        } else {

            $delete_anotacao_sql = "DELETE FROM anotacoes WHERE id = '$id'";
            $delete_anotacao = mysqli_query($db, $delete_anotacao_sql);

            $atualizar_autoincrement = "ALTER TABLE anotacoes AUTO_INCREMENT = 1";
            if ($delete_anotacao) {
                mysqli_query($db, $atualizar_autoincrement);
                echo json_encode("sucesso");
            } else {
                echo json_encode("erro_na_exclusao");
            }
        }
    }

    public function getMotivosOracao($idLider)
    {
        require_once '../conexao.php';

        if ($idLider == 0 || $idLider == '') {
            echo json_encode("dados_vazios");
        } else {
            // VERIFICA SE O LIDER TEM PG
            $consulta_pg = "SELECT * FROM pg WHERE id_lider = '$idLider'";
            $resultado_pg = mysqli_query($db, $consulta_pg);
            $cont_pg = mysqli_num_rows($resultado_pg);

            if ($cont_pg == 0) {
                echo json_decode("");
                exit;
            }

            $dados_pg = mysqli_fetch_assoc($resultado_pg);
            $idModulo = $dados_pg['id_modulo'];

            // ==========================================
            // 1. VERIFICA SE JÁ EXISTE ORAÇÃO HOJE
            // ==========================================
            $sqlHoje = "SELECT mo.* FROM oracao_mostrada om INNER JOIN momento_oracao mo ON mo.id = om.id_oracao WHERE om.id_lider = '$idLider' AND mo.id_modulo = '$idModulo' AND om.data_mostrada = CURDATE() LIMIT 1";
            $resultHoje = mysqli_query($db, $sqlHoje);

            $oracaoHoje = mysqli_fetch_assoc($resultHoje);

            // Se já tiver oração hoje
            if ($oracaoHoje) {
                echo json_encode($oracaoHoje['texto']);
                exit;
            }

            // ==========================================
            // 2. BUSCA ORAÇÃO NÃO MOSTRADA
            // ==========================================

            $sqlNova = "SELECT *
                FROM momento_oracao
                WHERE id_modulo = '$idModulo'
                AND id NOT IN (
                    SELECT id_oracao
                    FROM oracao_mostrada
                    WHERE id_lider = '$idLider'
                )
                ORDER BY RAND()
                LIMIT 1
            ";

            $resultNova = mysqli_query($db, $sqlNova);

            $novaOracao = mysqli_fetch_assoc($resultNova);

            // ==========================================
            // 3. SE ACABARAM TODAS → LIMPA HISTÓRICO
            // ==========================================
            if (!$novaOracao) {

                //$sqlLimpar = "DELETE FROM oracao_mostrada WHERE id_lider = '$idLider'";
                $sqlLimpar = "DELETE om
                    FROM oracao_mostrada om
                    INNER JOIN momento_oracao mo
                        ON mo.id = om.id_oracao
                    WHERE om.id_lider = '$idLider'
                    AND mo.id_modulo = '$idModulo'
                ";
                mysqli_query($db, $sqlLimpar);
                // Busca novamente
                $resultNova = mysqli_query($db, $sqlNova);
                $novaOracao = mysqli_fetch_assoc($resultNova);
            }

            // ==========================================
            // 4. SALVA ORAÇÃO DO DIA
            // ==========================================
            $idOracao = $novaOracao['id'];
            $sqlSalvar = "INSERT INTO oracao_mostrada(
                    id_lider,
                    id_oracao,
                    data_mostrada
                )
                VALUES (
                    '$idLider',
                    '$idOracao',
                    CURDATE()
                )
            ";

            mysqli_query($db, $sqlSalvar);

            // ==========================================
            // 5. RETORNA JSON
            // ==========================================
            echo json_encode($novaOracao['texto']);
        }
    }


    public function getDesafio($idModulo, $nLicao)
    {
        require_once '../conexao.php';

        if ($idModulo == '' || $nLicao == '') {
            echo json_encode("dados_vazios");
        } else {
            // VERIFICA SE O LIDER TEM PG
            $consulta_desafio = "SELECT * FROM desafio_licao WHERE id_modulo = '$idModulo' AND n_licao = '$nLicao'";
            $resultado_desafio = mysqli_query($db, $consulta_desafio);
            $cont_desafio = mysqli_num_rows($resultado_desafio);

            if ($cont_desafio == 0) {
                echo json_decode("");
                exit;
            } else {
            }

            $desafio = mysqli_fetch_assoc($resultado_desafio);
            echo json_encode($desafio);
        }
    }

    public function addPerguntaPai($idLider, $idPergunta)
    {
        require_once '../conexao.php';
        $hoje = date('Y-m-d');

        if (empty($idLider)) {
            echo json_encode("dados_vazios");
            exit;
        }

        $query = "SELECT * FROM pergunta_mostrada WHERE id_lider = '$idLider' AND DATE(data_mostrada) = '$hoje'";
        $result = mysqli_query($db, $query);
        $cont = mysqli_num_rows($result);

        if ($cont == 0) {
            $sqlSalvar = "INSERT INTO pergunta_mostrada(
                    id_lider,
                    id_pergunta,
                    data_mostrada
                )
                VALUES (
                    '$idLider',
                    '$idPergunta',
                    CURDATE()
                )
            ";
            mysqli_query($db, $sqlSalvar);
            echo json_encode("sucesso");
        } else {
            echo json_encode("ja_respondeu_hoje");
            exit;
        }
    }

    public function getPerguntaPai($idLider)
    {
        require_once '../conexao.php';

        if (empty($idLider)) {
            echo json_encode("dados_vazios");
            exit;
        }

        $sql = "SELECT
            p.id,
            p.id_modulo,
            p.n_licao,
            p.texto,

            CASE
                WHEN pm.id IS NULL THEN false
                ELSE true
            END AS respondida

        FROM pergunte_ao_pai p

        LEFT JOIN pergunta_mostrada pm
            ON pm.id_pergunta = p.id
            AND pm.id_lider = '$idLider'

        ORDER BY p.id
    ";

        $result = mysqli_query($db, $sql);

        if (!$result) {
            echo json_encode("db_erro");
            exit;
        }

        $lista = [];

        while ($row = mysqli_fetch_assoc($result)) {

            $row['respondida'] = (bool)$row['respondida'];

            $lista[] = $row;
        }

        if (count($lista) == 0) {
            echo json_encode("dados_vazios");
            exit;
        }

        echo json_encode($lista);
    }

    public function updateCheckAtividade(
        $idLider,
        $idPg,
        $idModulo,
        $nLicao,
        $tipo,
        $concluido
    ) {
        require_once '../conexao.php';

        // =====================================================
        // 1. VALIDAÇÃO DOS DADOS
        // =====================================================

        if (
            empty($idLider) ||
            empty($idPg) ||
            empty($idModulo) ||
            empty($nLicao) ||
            empty($tipo)
        ) {
            echo json_encode("dados_vazios");
            return;
        }

        // Atividades permitidas
        $tiposPermitidos = [
            'desafio',
            'versiculo',
            'misterio'
        ];

        if (!in_array($tipo, $tiposPermitidos, true)) {
            echo json_encode("tipo_invalido");
            return;
        }

        // Só aceita 0 ou 1
        $concluido = (int) $concluido;

        if ($concluido !== 0 && $concluido !== 1) {
            echo json_encode("concluido_invalido");
            return;
        }

        // =====================================================
        // 2. VERIFICA SE JÁ EXISTE
        // =====================================================

        $sql = "
        SELECT id
        FROM check_atividade
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND n_licao = ?
          AND tipo = ?
        LIMIT 1
    ";

        $stmt = $db->prepare($sql);

        $stmt->bind_param(
            "siiis",
            $idLider,
            $idPg,
            $idModulo,
            $nLicao,
            $tipo
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        // =====================================================
        // 3. SE JÁ EXISTE, ATUALIZA
        // =====================================================

        if ($resultado->num_rows > 0) {

            $registro = $resultado->fetch_assoc();
            $id = $registro['id'];

            $sqlUpdate = "
            UPDATE check_atividade
            SET
                concluido = ?,
                data_conclusao =
                    CASE
                        WHEN ? = 1 THEN NOW()
                        ELSE NULL
                    END
            WHERE id = ?
        ";

            $stmtUpdate = $db->prepare($sqlUpdate);

            $stmtUpdate->bind_param(
                "iii",
                $concluido,
                $concluido,
                $id
            );

            if (!$stmtUpdate->execute()) {
                echo json_encode("erro_na_edicao");
                return;
            }
        } else {

            // =================================================
            // 4. SE NÃO EXISTE, CRIA
            // =================================================

            $sqlInsert = "
            INSERT INTO check_atividade
            (
                id_lider,
                id_pg,
                id_modulo,
                n_licao,
                tipo,
                concluido,
                data_conclusao
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?,
                CASE
                    WHEN ? = 1 THEN NOW()
                    ELSE NULL
                END
            )
        ";

            $stmtInsert = $db->prepare($sqlInsert);

            $stmtInsert->bind_param(
                "siiisii",
                $idLider,
                $idPg,
                $idModulo,
                $nLicao,
                $tipo,
                $concluido,
                $concluido
            );

            if (!$stmtInsert->execute()) {
                echo json_encode("erro_no_cadastro");
                return;
            }
        }

        // =====================================================
        // 5. SUCESSO
        // =====================================================
        if ($concluido === 1) {
            $this->atualizarPosicaoSeConcluida(
                $db,
                $idLider,
                $idPg,
                $idModulo,
                $nLicao
            );
        }

        echo json_encode("sucesso");
    }

    public function getProgressoCompletoLicao(
        $idLider,
        $idPg,
        $idModulo,
        $nLicao
    ) {
        require_once '../conexao.php';

        // =====================================================
        // 1. VALIDAÇÃO
        // =====================================================

        if (
            empty($idLider) ||
            empty($idPg) ||
            empty($idModulo) ||
            empty($nLicao)
        ) {
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Dados incompletos.'
            ]);
            return;
        }

        // =====================================================
        // 2. LIÇÃO PRINCIPAL
        // =====================================================

        $licaoConcluida = false;

        $sql = "
        SELECT checks
        FROM check_licao
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND n_licao = ?
        LIMIT 1
    ";

        $stmt = $db->prepare($sql);

        $stmt->bind_param(
            "siii",
            $idLider,
            $idPg,
            $idModulo,
            $nLicao
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($row = $resultado->fetch_assoc()) {
            $licaoConcluida = ((int) $row['checks'] === 1);
        }

        // =====================================================
        // 3. SEMANÁRIOS
        // =====================================================

        $semanariosConcluidos = 0;

        $sql = "
        SELECT COUNT(DISTINCT dia) AS total
        FROM check_devocional
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND n_licao = ?
          AND dia BETWEEN 1 AND 6
          AND check_devocional = 1
    ";

        $stmt = $db->prepare($sql);

        $stmt->bind_param(
            "siii",
            $idLider,
            $idPg,
            $idModulo,
            $nLicao
        );

        $stmt->execute();

        $resultado = $stmt->get_result();
        $row = $resultado->fetch_assoc();

        $semanariosConcluidos = (int) ($row['total'] ?? 0);

        // =====================================================
        // 4. DESAFIO / VERSÍCULO / MISTÉRIO
        // =====================================================

        $desafio = false;
        $versiculo = false;
        $misterio = false;

        $sql = "
        SELECT tipo, concluido
        FROM check_atividade
        WHERE id_lider = ?
          AND id_pg = ?
          AND id_modulo = ?
          AND n_licao = ?
    ";

        $stmt = $db->prepare($sql);

        $stmt->bind_param(
            "siii",
            $idLider,
            $idPg,
            $idModulo,
            $nLicao
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        while ($row = $resultado->fetch_assoc()) {

            $concluido = ((int) $row['concluido'] === 1);

            switch ($row['tipo']) {
                case 'desafio':
                    $desafio = $concluido;
                    break;

                case 'versiculo':
                    $versiculo = $concluido;
                    break;

                case 'misterio':
                    $misterio = $concluido;
                    break;
            }
        }

        // =====================================================
        // 5. CALCULA PROGRESSO
        // =====================================================

        $concluidos = 0;

        if ($licaoConcluida) {
            $concluidos++;
        }

        $concluidos += $semanariosConcluidos;

        if ($desafio) {
            $concluidos++;
        }

        if ($versiculo) {
            $concluidos++;
        }

        if ($misterio) {
            $concluidos++;
        }

        $total = 10;

        $porcentagem = (int) round(
            ($concluidos / $total) * 100
        );

        // =====================================================
        // 6. RETORNO
        // =====================================================

        echo json_encode([
            'sucesso' => true,

            'progresso' => [
                'concluidos' => $concluidos,
                'total' => $total,
                'porcentagem' => $porcentagem,

                'licao' => $licaoConcluida,

                'semanarios' => [
                    'concluidos' => $semanariosConcluidos,
                    'total' => 6
                ],

                'desafio' => $desafio,
                'versiculo' => $versiculo,
                'misterio' => $misterio
            ]
        ]);
    }

    /*public function getPerguntaPai($idLider)
    {
        require_once '../conexao.php';

        if ($idLider == 0 || $idLider == '') {
            echo json_encode("dados_vazios");
        } else {

            // VERIFICA SE O LIDER TEM PG
            $consulta_pg = "SELECT * FROM pg WHERE id_lider = '$idLider'";
            $resultado_pg = mysqli_query($db, $consulta_pg);
            $cont_pg = mysqli_num_rows($resultado_pg);

            if ($cont_pg == 0) {
                echo json_decode("");
                exit;
            }

            $dados_pg = mysqli_fetch_assoc($resultado_pg);
            $idModulo = $dados_pg['id_modulo'];
            $nLicao = $dados_pg['n_licao'];

            // ==========================================
            // 1. VERIFICA SE JÁ EXISTE ORAÇÃO HOJE
            // ==========================================
            //$sqlHoje = "SELECT mo.* FROM pergunta_mostrada om INNER JOIN pergunte_ao_pai mo ON mo.id = om.id_pergunta WHERE om.id_lider = '$idLider' AND om.data_mostrada = CURDATE() LIMIT 1";

            $sqlHoje = "SELECT mo.*
                FROM pergunta_mostrada om
                INNER JOIN pergunte_ao_pai mo
                ON mo.id = om.id_pergunta
                WHERE om.id_lider = '$idLider'
                AND mo.id_modulo = '$idModulo'
                AND mo.n_licao = '$nLicao'
                AND om.data_mostrada = CURDATE()
                LIMIT 1
            ";

            $resultHoje = mysqli_query($db, $sqlHoje);

            $perguntaHoje = mysqli_fetch_assoc($resultHoje);

            // Se já tiver oração hoje
            if ($perguntaHoje) {
                echo json_encode($perguntaHoje['texto']);
                exit;
            }

            // ==========================================
            // 2. BUSCA ORAÇÃO NÃO MOSTRADA
            // ==========================================

            $sqlNova = "SELECT *
                FROM pergunte_ao_pai
                WHERE id_modulo = '$idModulo'
                AND n_licao = '$nLicao'
                AND id NOT IN (
                    SELECT id_pergunta
                    FROM pergunta_mostrada
                    WHERE id_lider = '$idLider'
                )
                ORDER BY RAND()
                LIMIT 1
            ";

            $resultNova = mysqli_query($db, $sqlNova);

            $novaPergunta = mysqli_fetch_assoc($resultNova);

            // ==========================================
            // 3. SE ACABARAM TODAS → LIMPA HISTÓRICO
            // ==========================================
            if (!$novaPergunta) {
                $sqlLimpar = "DELETE pm
                    FROM pergunta_mostrada pm
                    INNER JOIN pergunte_ao_pai pp
                        ON pp.id = pm.id_pergunta
                    WHERE pm.id_lider = '$idLider'
                    AND pp.id_modulo = '$idModulo'
                    AND pp.n_licao = '$nLicao'
                ";
                mysqli_query($db, $sqlLimpar);
                // Busca novamente
                $resultNova = mysqli_query($db, $sqlNova);
                $novaPergunta = mysqli_fetch_assoc($resultNova);
            }

            // ==========================================
            // 4. SALVA PERGUNTA DO DIA
            // ==========================================
            $idPergunta = $novaPergunta['id'];
            $sqlSalvar = "INSERT INTO pergunta_mostrada(
                    id_lider,
                    id_pergunta,
                    data_mostrada
                )
                VALUES (
                    '$idLider',
                    '$idPergunta',
                    CURDATE()
                )
            ";

            mysqli_query($db, $sqlSalvar);

            // ==========================================
            // 5. RETORNA JSON
            // ==========================================
            echo json_encode($novaPergunta['texto']);
        }
    }*/
}
