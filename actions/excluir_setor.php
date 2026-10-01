<?php
// actions/excluir_setor.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/conexao.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   
    $id_setor = (int)($_POST['id_setor'] ?? 0);
    $redirect_to = '../pages/dashboard.php'; 

    if ($id_setor <= 0) {
        $msg_url = urlencode("ID do setor é inválido.");
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT nome, saldo_atual FROM setores WHERE id_setor = :id");
        $stmt->execute(['id' => $id_setor]);
        $setor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$setor) {
            throw new Exception("O setor solicitado não foi encontrado na base de dados.");
        }

        if ((float)$setor['saldo_atual'] != 0) {
            $saldo_formatado = number_format((float)$setor['saldo_atual'], 2, ',', '.');
            throw new Exception("Não é possível excluir o setor '{$setor['nome']}' porque o saldo atual é de R$ {$saldo_formatado}. É obrigatório que o saldo seja exatamente R$ 0,00 antes da exclusão (faça as realocações necessárias).");
        }

        $stmtUpdate = $pdo->prepare("UPDATE setores SET ativo = FALSE WHERE id_setor = :id");
        $stmtUpdate->execute(['id' => $id_setor]);
        
        header("Location: " . $redirect_to);
        exit;

    } catch (Exception $e) {
        $msg_url = urlencode($e->getMessage());
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>