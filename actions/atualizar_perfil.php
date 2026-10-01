<?php
// actions/atualizar_perfil.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/conexao.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_admin = $_SESSION['admin_id'] ?? ($_SESSION['id_admin'] ?? 0);
    $redirect_to = '../pages/perfil/perfil.php';
    
    if ($id_admin <= 0) {
        $msg_url = urlencode("Sessão inválida. Por favor, faça login novamente.");
        $link_url = urlencode('../autentificacao/login.php');
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    try {
        if (empty($nome) || empty($email)) {
            throw new Exception("Os campos Nome e E-mail são obrigatórios.");
        }

        $stmtCheck = $pdo->prepare("SELECT id_admin FROM administradores WHERE email = :email AND id_admin != :id");
        $stmtCheck->execute(['email' => $email, 'id' => $id_admin]);
        if ($stmtCheck->fetch()) {
            throw new Exception("Este e-mail já está a ser utilizado por outra conta.");
        }

        $pdo->beginTransaction();

        if (!empty($nova_senha)) {
            if ($nova_senha !== $confirmar_senha) {
                throw new Exception("As novas senhas não coincidem.");
            }
            
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE administradores SET nome = :nome, email = :email, senha_hash = :hash WHERE id_admin = :id");
            $stmtUpdate->execute(['nome' => $nome, 'email' => $email, 'hash' => $hash, 'id' => $id_admin]);
        } 
        else {
            $stmtUpdate = $pdo->prepare("UPDATE administradores SET nome = :nome, email = :email WHERE id_admin = :id");
            $stmtUpdate->execute(['nome' => $nome, 'email' => $email, 'id' => $id_admin]);
        }

        $pdo->commit();
        
        $_SESSION['admin_name'] = $nome;

        header("Location: {$redirect_to}?sucesso=1");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        
        $msg_erro = urlencode($e->getMessage());
        header("Location: {$redirect_to}?erro={$msg_erro}");
        exit;
    }
}
?>