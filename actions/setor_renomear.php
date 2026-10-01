<?php
// actions/setor_renomear.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_setores.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id_setor'] ?? 0);
    $novo_nome = trim($_POST['novo_nome'] ?? '');
    $redirect = $_POST['redirect_to'] ?? '../pages/dashboard.php';

    if ($id > 0 && !empty($novo_nome)) {
        try {
            $setorAtual = buscarSetorPorId($id);
            
            if ($setorAtual !== null) {
                editarSetor($id, $novo_nome, $setorAtual['descricao']);
            }
        } catch (Exception $e) {
            $msg_url = urlencode("Erro ao renomear setor: " . $e->getMessage());
            $link_url = urlencode($redirect);
            header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
            exit;
        }
    }
    
    header("Location: " . $redirect);
    exit;
}
?>