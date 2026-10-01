<?php
// actions/salvar_lancamento_completo.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_compromissos.php';
require_once __DIR__ . '/../crud/crud_contas_receber.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
session_start();
$id_admin_atual = $_SESSION['id_admin'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_operacao = $_POST['tipo_operacao'] ?? 'despesa';
    $status_pagamento = $_POST['status_pagamento'] ?? 'pendente';
    $descricao = trim($_POST['descricao'] ?? '');
    
    $valorRecebido = $_POST['valor'] ?? '0';
    $valor_total = (float) str_replace(',', '.', $valorRecebido);
    
    $id_setor = (int) ($_POST['id_setor'] ?? 0);
    $id_categoria = (int) ($_POST['id_categoria'] ?? 0);
    $metodo_pagamento = trim($_POST['metodo_pagamento'] ?? 'PIX');
    $data_vencimento = trim($_POST['data_vencimento'] ?? '');
    $redirect_to = $_POST['redirect_to'] ?? '../pages/dashboard.php';

    $is_parcelado = isset($_POST['is_parcelado']);
    $total_parcelas = $is_parcelado ? (int)($_POST['qtd_parcelas'] ?? 1) : 1;

    try {
        $pdo->beginTransaction();

        $valor_parcela_base = round($valor_total / $total_parcelas, 2);
        $codigo_parcelamento = ($is_parcelado && $total_parcelas > 1 && $tipo_operacao === 'receita') ? uniqid('REC_') : null;

        for ($i = 1; $i <= $total_parcelas; $i++) {
            $valor_atual = ($i === $total_parcelas) ? ($valor_total - ($valor_parcela_base * ($total_parcelas - 1))) : $valor_parcela_base;
            $meses_add = $i - 1;
            $vencimento_atual = date('Y-m-d', strtotime("+$meses_add months", strtotime($data_vencimento)));
            
            $eh_efetivado = ($status_pagamento === 'efetivado' && $i === 1);
            $desc_atual = ($total_parcelas > 1) ? "{$descricao} (Parc. $i/$total_parcelas)" : $descricao;

            if ($tipo_operacao === 'despesa') {
                if ($eh_efetivado) {
                    $stmtSetor = $pdo->prepare("SELECT saldo_atual FROM setores WHERE id_setor = :id FOR UPDATE");
                    $stmtSetor->execute(['id' => $id_setor]);
                    if ((float)$stmtSetor->fetchColumn() < $valor_atual) throw new Exception("Saldo insuficiente no setor selecionado.");

                    $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual - :val WHERE id_setor = :id")->execute(['val' => $valor_atual, 'id' => $id_setor]);
                    $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('DESPESA', :val, NOW(), :desc, 'ATIVA', :adm)")->execute(['val' => $valor_atual, 'desc' => "Despesa: " . $desc_atual, 'adm' => $id_admin_atual]);
                    $id_mov = $pdo->lastInsertId();

                    $sql = "INSERT INTO despesas (descricao, valor, data, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_movimentacao_fk) VALUES (:desc, :val, :venc, :metodo, 'ATIVA', :setor, :cat, :adm, :mov)";
                    $pdo->prepare($sql)->execute(['desc'=>$desc_atual, 'val'=>$valor_atual, 'venc'=>$vencimento_atual, 'metodo'=>$metodo_pagamento, 'setor'=>$id_setor, 'cat'=>$id_categoria, 'adm'=>$id_admin_atual, 'mov'=>$id_mov]);
                } else {
                    criarCompromisso($desc_atual, $valor_atual, $vencimento_atual, $id_setor, $id_categoria, $id_admin_atual);
                }
            } 
            else {
                if ($eh_efetivado) {
                    $pdo->query("SELECT saldo_atual FROM saldo_geral WHERE id_saldo_geral = 1 FOR UPDATE");
                    $pdo->prepare("UPDATE saldo_geral SET saldo_atual = saldo_atual + :val WHERE id_saldo_geral = 1")->execute(['val' => $valor_atual]);

                    $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('RECEITA', :val, NOW(), :desc, 'ATIVA', :adm)")->execute(['val' => $valor_atual, 'desc' => "Receita: " . $desc_atual, 'adm' => $id_admin_atual]);
                    $id_mov = $pdo->lastInsertId();

                    $sql = "INSERT INTO receitas (descricao, valor, data, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_movimentacao_fk) VALUES (:desc, :val, :venc, :metodo, 'ATIVA', :setor, :cat, :adm, :mov)";
                    $pdo->prepare($sql)->execute(['desc'=>$desc_atual, 'val'=>$valor_atual, 'venc'=>$vencimento_atual, 'metodo'=>$metodo_pagamento, 'setor'=>$id_setor, 'cat'=>$id_categoria, 'adm'=>$id_admin_atual, 'mov'=>$id_mov]);
                } else {
                    $num_p = ($total_parcelas > 1) ? $i : null;
                    $tot_p = ($total_parcelas > 1) ? $total_parcelas : null;
                    
                    criarContaReceber($descricao, $valor_atual, $vencimento_atual, $id_setor, $id_categoria, $id_admin_atual, $num_p, $tot_p, $codigo_parcelamento);
                }
            }
        }

        $pdo->commit();
        header("Location: " . $redirect_to);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $msg_url = urlencode("Erro ao salvar lançamento: " . $e->getMessage());
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>