<?php
// actions/realocar_saldo.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_setores.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
session_start();
$id_admin = $_SESSION['id_admin'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_setor_destino = (int)($_POST['id_setor_destino'] ?? 0);
    $valorRecebido = $_POST['valor_transferencia'] ?? '0';
    $descricao = trim($_POST['descricao_transferencia'] ?? 'Realocação de Saldo');
    $valor = (float) str_replace(',', '.', $valorRecebido);
    $redirect_to = '../pages/dashboard.php';

    if ($id_setor_destino <= 0 || $valor <= 0) {
        $msg_url = urlencode("Setor ou valor de transferência inválido.");
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }

    try {
        if (buscarSetorPorId($id_setor_destino) === null) {
             throw new Exception("Setor de destino não encontrado.");
        }

        $pdo->beginTransaction();

        $stmtSaldo = $pdo->query("SELECT saldo_atual FROM saldo_geral WHERE id_saldo_geral = 1 FOR UPDATE");
        $saldo_geral = (float) $stmtSaldo->fetchColumn();

        if ($saldo_geral < $valor) throw new Exception("Saldo Geral insuficiente para efetuar esta realocação.");

        $pdo->prepare("UPDATE saldo_geral SET saldo_atual = saldo_atual - :val WHERE id_saldo_geral = 1")->execute(['val' => $valor]);
        $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual + :val WHERE id_setor = :id")->execute(['val' => $valor, 'id' => $id_setor_destino]);

        $stmtMov = $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('DISTRIBUICAO', :val, NOW(), :desc, 'ATIVA', :adm)");
        $stmtMov->execute(['val' => $valor, 'desc' => "Distribuição para setor: " . $descricao, 'adm' => $id_admin]);
        $id_movimentacao = $pdo->lastInsertId();

        $sqlTransf = "INSERT INTO transferencias (tipo, valor, data, status, id_setor_destino_fk, id_admin_fk, id_movimentacao_fk) VALUES ('DISTRIBUICAO', :val, CURDATE(), 'ATIVA', :setor, :adm, :mov)";
        $pdo->prepare($sqlTransf)->execute(['val' => $valor, 'setor' => $id_setor_destino, 'adm' => $id_admin, 'mov' => $id_movimentacao]);

        $pdo->commit();
        header("Location: " . $redirect_to);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg_url = urlencode("Falha na Realocação: " . $e->getMessage());
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>