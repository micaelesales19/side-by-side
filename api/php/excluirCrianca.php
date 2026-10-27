<?php
require_once 'funcao.php';

$idCrianca = $_POST['idCrianca'] ?? $_GET['idCrianca'];

$funcao = new Funcao();

$funcao->deleteCriancasLider($idCrianca);
