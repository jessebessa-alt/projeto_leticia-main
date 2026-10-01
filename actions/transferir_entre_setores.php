<?php
// actions/transferir_entre_setores.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_setores.php'; 

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
session_start();
$id_admin = $_SESSION['admin_id'] ?? ($_SESSION['id_admin'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_setor_origem = (int) ($_POST['id_setor_origem'] ?? 0);
    $id_setor_destino = (int) ($_POST['id_setor_destino'] ?? 0);
    $descricao = trim($_POST['descricao_transferencia'] ?? 'Transferência direta entre setores');
    
    $valorRecebido = $_POST['valor_transferencia'] ?? '0';
    $valor = (float) str_replace(',', '.', $valorRecebido);
    $redirect_to = '../pages/dashboard.php';

    try {
        if ($id_setor_origem <= 0 || $id_setor_destino <= 0 || $valor <= 0) {
            throw new Exception("Dados inválidos. Selecione origem, destino e valor válido.");
        }
        
        if ($id_setor_origem === $id_setor_destino) {
            throw new Exception("O setor de origem e destino não podem ser o mesmo.");
        }

        $pdo->beginTransaction();

        $origem = buscarSetorPorId($id_setor_origem);
        if ($origem === null || (float)$origem['saldo_atual'] < $valor) {
            throw new Exception("O setor de origem não possui saldo suficiente.");
        }

        $destino = buscarSetorPorId($id_setor_destino);
        if ($destino === null) {
            throw new Exception("O setor de destino não foi encontrado.");
        }

        $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual - :val WHERE id_setor = :id")->execute(['val' => $valor, 'id' => $id_setor_origem]);
        $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual + :val WHERE id_setor = :id")->execute(['val' => $valor, 'id' => $id_setor_destino]);

        $stmtMov = $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('REALOCACAO', :val, NOW(), :desc, 'ATIVA', :adm)");
        $stmtMov->execute(['val' => $valor, 'desc' => $descricao, 'adm' => $id_admin]);
        $id_movimentacao = $pdo->lastInsertId();

        $sqlTransf = "INSERT INTO transferencias (tipo, valor, data, status, id_setor_origem_fk, id_setor_destino_fk, id_admin_fk, id_movimentacao_fk) 
                      VALUES ('REALOCACAO', :val, CURDATE(), 'ATIVA', :origem, :destino, :adm, :mov)";
        $pdo->prepare($sqlTransf)->execute([
            'val' => $valor,
            'origem' => $id_setor_origem,
            'destino' => $id_setor_destino,
            'adm' => $id_admin,
            'mov' => $id_movimentacao
        ]);

        $pdo->commit();
        header("Location: " . $redirect_to);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        
        $msg_url = urlencode("Erro na Transferência: " . $e->getMessage());
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>