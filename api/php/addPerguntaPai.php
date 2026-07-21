<?php
require_once 'funcao.php';

$idLider = $_POST['id_lider'] ?? $_GET['id_lider'];
$idPergunta = $_POST['id_pergunta'] ?? $_GET['id_pergunta'];

$funcao = new Funcao();

$funcao->addPerguntaPai($idLider, $idPergunta);
