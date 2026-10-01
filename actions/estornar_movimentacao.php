<?php
// actions/estornar_movimentacao.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/conexao.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_movimentacao = (int)($_POST['id_movimentacao'] ?? 0);
    $id_admin_logado = $_SESSION['admin_id'] ?? ($_SESSION['id_admin'] ?? 1);
    $redirect_to = '../pages/historico.php';

    if ($id_movimentacao <= 0) {
        $msg_url = urlencode("O ID da movimentação é inválido.");
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtMov = $pdo->prepare("SELECT * FROM movimentacoes WHERE id_movimentacao = :id FOR UPDATE");
        $stmtMov->execute(['id' => $id_movimentacao]);
        $mov = $stmtMov->fetch(PDO::FETCH_ASSOC);

        if (!$mov) throw new Exception("Movimentação não encontrada no sistema.");
        
        if ($mov['status'] === 'ESTORNADA') throw new Exception("Esta movimentação já foi estornada anteriormente.");

        $estornoRealizado = false;

        $stmtD = $pdo->prepare("SELECT * FROM despesas WHERE id_movimentacao_fk = :id");
        $stmtD->execute(['id' => $id_movimentacao]);
        if ($desp = $stmtD->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual + :val WHERE id_setor = :setor")
                ->execute(['val' => $desp['valor'], 'setor' => $desp['id_setor_fk']]);
            
            $pdo->prepare("UPDATE despesas SET status = 'ESTORNADA' WHERE id_despesa = :id")
                ->execute(['id' => $desp['id_despesa']]);

            if (!empty($desp['id_compromisso_fk'])) {
                $pdo->prepare("UPDATE compromissos SET status = 'PENDENTE' WHERE id_compromisso = :id")
                    ->execute(['id' => $desp['id_compromisso_fk']]);
            }
            $estornoRealizado = true;
        }

        if (!$estornoRealizado) {
            $stmtR = $pdo->prepare("SELECT * FROM receitas WHERE id_movimentacao_fk = :id");
            $stmtR->execute(['id' => $id_movimentacao]);
            if ($rec = $stmtR->fetch(PDO::FETCH_ASSOC)) {
                $pdo->prepare("UPDATE saldo_geral SET saldo_atual = saldo_atual - :val WHERE id_saldo_geral = 1")
                    ->execute(['val' => $rec['valor']]);
                
                $pdo->prepare("UPDATE receitas SET status = 'ESTORNADA' WHERE id_receita = :id")
                    ->execute(['id' => $rec['id_receita']]);

                if (!empty($rec['id_conta_receber_fk'])) {
                    $pdo->prepare("UPDATE contas_receber SET status = 'PENDENTE' WHERE id_conta_receber = :id")
                        ->execute(['id' => $rec['id_conta_receber_fk']]);
                }
                $estornoRealizado = true;
            }
        }

        if (!$estornoRealizado) {
            $stmtT = $pdo->prepare("SELECT * FROM transferencias WHERE id_movimentacao_fk = :id");
            $stmtT->execute(['id' => $id_movimentacao]);
            if ($transf = $stmtT->fetch(PDO::FETCH_ASSOC)) {
                $pdo->prepare("UPDATE saldo_geral SET saldo_atual = saldo_atual + :val WHERE id_saldo_geral = 1")
                    ->execute(['val' => $transf['valor']]);
                $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual - :val WHERE id_setor = :setor")
                    ->execute(['val' => $transf['valor'], 'setor' => $transf['id_setor_destino_fk']]);
                
                $pdo->prepare("UPDATE transferencias SET status = 'ESTORNADA' WHERE id_transferencia = :id")
                    ->execute(['id' => $transf['id_transferencia']]);
                
                $estornoRealizado = true;
            }
        }

        $pdo->prepare("UPDATE movimentacoes SET status = 'ESTORNADA' WHERE id_movimentacao = :id")
            ->execute(['id' => $id_movimentacao]);

        $descricao_estorno = "ESTORNO: " . $mov['descricao'];
        $stmtEstorno = $pdo->prepare("
            INSERT INTO movimentacoes 
            (tipo, valor, criado_em, descricao, status, id_admin_fk, id_movimentacao_origem_fk) 
            VALUES 
            ('ESTORNO', :valor, NOW(), :descricao, 'ATIVA', :id_admin, :id_origem)
        ");
        $stmtEstorno->execute([
            'valor' => $mov['valor'],
            'descricao' => $descricao_estorno,
            'id_admin' => $id_admin_logado,
            'id_origem' => $id_movimentacao
        ]);

        $pdo->commit();
        header("Location: " . $redirect_to);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg_url = urlencode("Não foi possível Estornar: " . $e->getMessage());
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>