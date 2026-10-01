<?php
// actions/salvar_transacao.php
require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_compromissos.php';
require_once __DIR__ . '/../crud/crud_contas_receber.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
session_start();
$id_admin_atual = $_SESSION['id_admin'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_operacao = $_POST['tipo_operacao'] ?? 'despesa';
    $descricao = trim($_POST['descricao'] ?? '');
    
    $valorRecebido = $_POST['valor'] ?? '0';
    $valor = (float) str_replace(',', '.', $valorRecebido);
    
    $id_setor = (int) ($_POST['id_setor'] ?? 0);
    $data_vencimento = trim($_POST['data'] ?? '');
    $status_pagamento = $_POST['status_pagamento'] ?? '';
    $metodo_pagamento = 'OUTRO'; 

    try {
        if ($descricao === '' || strlen($descricao) > 255) throw new Exception("Descrição inválida.");
        if (!is_finite($valor) || $valor < 0.01) throw new Exception("Valor inválido.");
        if ($id_setor <= 0) throw new Exception("Setor obrigatório.");
        
        $dataObj = DateTime::createFromFormat('!Y-m-d', $data_vencimento);
        if ($dataObj === false || $dataObj->format('Y-m-d') !== $data_vencimento) throw new Exception("Data inválida.");

        $tipo_cat = ($tipo_operacao === 'receita') ? 'RECEITA' : 'DESPESA';
        $stmtCat = $pdo->prepare("SELECT id_categoria, ativo FROM categorias WHERE tipo = :tipo LIMIT 1");
        $stmtCat->execute(['tipo' => $tipo_cat]);
        $categoria = $stmtCat->fetch(PDO::FETCH_ASSOC);
        
        if ($categoria === false || !(bool)$categoria['ativo']) {
            throw new DomainException('Nenhuma categoria ativa encontrada para o tipo selecionado.');
        }
        $id_categoria = $categoria['id_categoria'];

        $pdo->beginTransaction();

        if ($tipo_operacao === 'despesa' && $status_pagamento === 'efetivado') {
            $stmtSetor = $pdo->prepare("SELECT saldo_atual FROM setores WHERE id_setor = :id FOR UPDATE");
            $stmtSetor->execute(['id' => $id_setor]);
            if ((float)$stmtSetor->fetchColumn() < $valor) throw new DomainException("Saldo insuficiente no setor.");

            $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('DESPESA', :val, NOW(), :desc, 'ATIVA', :adm)")->execute(['val' => $valor, 'desc' => $descricao, 'adm' => $id_admin_atual]);
            $id_mov = $pdo->lastInsertId();
            
            $pdo->prepare("INSERT INTO despesas (descricao, valor, data, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_movimentacao_fk) VALUES (:desc, :val, :data, :metodo, 'ATIVA', :setor, :cat, :adm, :mov)")->execute(['desc' => $descricao, 'val' => $valor, 'data' => $data_vencimento, 'metodo' => $metodo_pagamento, 'setor' => $id_setor, 'cat' => $id_categoria, 'adm' => $id_admin_atual, 'mov' => $id_mov]);
            $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual - :val WHERE id_setor = :id")->execute(['val' => $valor, 'id' => $id_setor]);

        } elseif ($tipo_operacao === 'receita' && $status_pagamento === 'efetivado') {
            $pdo->query("SELECT saldo_atual FROM saldo_geral WHERE id_saldo_geral = 1 FOR UPDATE");

            $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('RECEITA', :val, NOW(), :desc, 'ATIVA', :adm)")->execute(['val' => $valor, 'desc' => $descricao, 'adm' => $id_admin_atual]);
            $id_mov = $pdo->lastInsertId();
            
            $pdo->prepare("INSERT INTO receitas (descricao, valor, data, metodo_pagamento, status, id_setor_fk, id_categoria_fk, id_admin_fk, id_movimentacao_fk) VALUES (:desc, :val, :data, :metodo, 'ATIVA', :setor, :cat, :adm, :mov)")->execute(['desc' => $descricao, 'val' => $valor, 'data' => $data_vencimento, 'metodo' => $metodo_pagamento, 'setor' => $id_setor, 'cat' => $id_categoria, 'adm' => $id_admin_atual, 'mov' => $id_mov]);
            $pdo->prepare("UPDATE saldo_geral SET saldo_atual = saldo_atual + :val WHERE id_saldo_geral = 1")->execute(['val' => $valor]);

        } elseif ($tipo_operacao === 'despesa' && $status_pagamento === 'pendente') {
            criarCompromisso($descricao, $valor, $data_vencimento, $id_setor, $id_categoria, $id_admin_atual);
        } elseif ($tipo_operacao === 'receita' && $status_pagamento === 'pendente') {
            criarContaReceber($descricao, $valor, $data_vencimento, $id_setor, $id_categoria, $id_admin_atual);
        }

        $pdo->commit();
        header("Location: ../pages/setores/detalhes.php?id=" . $id_setor);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        
        $msg_url = urlencode("Erro ao salvar transação: " . $e->getMessage());
        $link_url = urlencode("../pages/setores/detalhes.php?id={$id_setor}");
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>