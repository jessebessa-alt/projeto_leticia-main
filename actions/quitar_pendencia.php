<?php
// actions/quitar_pendencia.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_compromissos.php';
require_once __DIR__ . '/../crud/crud_contas_receber.php';
require_once __DIR__ . '/../crud/crud_setores.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
session_start();
$id_admin_atual = $_SESSION['id_admin'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_registro = (int) ($_POST['id_registro'] ?? 0);
    $tipo = $_POST['tipo_quitacao'] ?? '';
    $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
    $valorRecebido = $_POST['valor_final'] ?? '0';
    $redirect_to = $_POST['redirect_to'] ?? '../pages/dashboard.php';
    $valor_final = (float) str_replace(',', '.', $valorRecebido);

    if ($id_registro <= 0 || !in_array($tipo, ['despesa', 'receita']) || $valor_final <= 0) {
        $msg_url = urlencode("Dados de quitação inválidos. Verifique os valores inseridos.");
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($tipo === 'despesa') {
            $pendencia = buscarCompromissoPorId($id_registro);
            if (!$pendencia) throw new Exception("Compromisso não encontrado.");
            if ($pendencia['status'] === 'PAGO') throw new Exception("Este compromisso já foi pago anteriormente.");

            $id_setor = $pendencia['id_setor_fk'];

            $setor = buscarSetorPorId($id_setor);
            if (!$setor || (float)$setor['saldo_atual'] < $valor_final) {
                $nome = $setor ? $setor['nome'] : 'Desconhecido';
                $saldo = $setor ? number_format($setor['saldo_atual'], 2, ',', '.') : '0,00';
                throw new Exception("O setor '$nome' não tem saldo suficiente (R$ $saldo).");
            }

            $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual - :val WHERE id_setor = :id")->execute(['val' => $valor_final, 'id' => $id_setor]);
            $stmtMov = $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('DESPESA', :val, NOW(), :desc, 'ATIVA', :adm)");
            $stmtMov->execute(['val' => $valor_final, 'desc' => "Pagamento: " . $pendencia['descricao'], 'adm' => $id_admin_atual]);
            $id_movimentacao = $pdo->lastInsertId();

            $sqlDesp = "INSERT INTO despesas (descricao, valor, data, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_compromisso_fk, id_movimentacao_fk) VALUES (:desc, :val, :data_pg, 'PIX', 'ATIVA', :setor, :cat, :adm, :comp_fk, :mov)";
            $pdo->prepare($sqlDesp)->execute(['desc' => $pendencia['descricao'], 'val' => $valor_final, 'data_pg' => $data_pagamento, 'setor' => $id_setor, 'cat' => $pendencia['id_categoria_fk'], 'adm' => $id_admin_atual, 'comp_fk' => $id_registro, 'mov' => $id_movimentacao]);
            
            $pdo->prepare("UPDATE compromissos SET status = 'PAGO' WHERE id_compromisso = :id")->execute(['id' => $id_registro]);
            
        } elseif ($tipo === 'receita') {
            $pendencia = buscarContaReceberPorId($id_registro);
            if (!$pendencia) throw new Exception("Conta a Receber não encontrada.");
            if ($pendencia['status'] === 'RECEBIDO') throw new Exception("Esta receita já foi baixada anteriormente.");

            $id_setor = $pendencia['id_setor_fk'];

            $pdo->query("SELECT saldo_atual FROM saldo_geral WHERE id_saldo_geral = 1 FOR UPDATE");
            $pdo->prepare("UPDATE saldo_geral SET saldo_atual = saldo_atual + :val WHERE id_saldo_geral = 1")->execute(['val' => $valor_final]);

            $stmtMov = $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('RECEITA', :val, NOW(), :desc, 'ATIVA', :adm)");
            $stmtMov->execute(['val' => $valor_final, 'desc' => "Recebimento: " . $pendencia['descricao'], 'adm' => $id_admin_atual]);
            $id_movimentacao = $pdo->lastInsertId();

            $sqlRec = "INSERT INTO receitas (descricao, valor, data, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_conta_receber_fk, id_movimentacao_fk) VALUES (:desc, :val, :data_pg, 'PIX', 'ATIVA', :setor, :cat, :adm, :cr_fk, :mov)";
            $pdo->prepare($sqlRec)->execute(['desc' => $pendencia['descricao'], 'val' => $valor_final, 'data_pg' => $data_pagamento, 'setor' => $id_setor, 'cat' => $pendencia['id_categoria_fk'], 'adm' => $id_admin_atual, 'cr_fk' => $id_registro, 'mov' => $id_movimentacao]);
            
            $pdo->prepare("UPDATE contas_receber SET status = 'RECEBIDO' WHERE id_conta_receber = :id")->execute(['id' => $id_registro]);
        }

        $pdo->commit();
        header("Location: " . $redirect_to);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg_url = urlencode("Falha na Quitação: " . $e->getMessage());
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>