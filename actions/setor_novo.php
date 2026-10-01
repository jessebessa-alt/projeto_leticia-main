<?php
// actions/setor_novo.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_setores.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome_setor'] ?? '');
    $redirect = $_POST['redirect_to'] ?? '../pages/dashboard.php';

    if (!empty($nome)) {
        try {
            criarSetor($nome);
        } catch (Exception $e) {
            $msg_url = urlencode("Erro ao criar setor: " . $e->getMessage());
            $link_url = urlencode($redirect);
            header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
            exit;
        }
    }
    
    header("Location: " . $redirect);
    exit;
}
?>