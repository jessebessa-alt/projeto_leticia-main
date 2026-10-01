<?php
// actions/recolher_saldo.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_setores.php'; 

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

session_start();
$id_admin = $_SESSION['id_admin'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_setor_origem = (int) ($_POST['id_setor_origem'] ?? 0);
    $descricao = trim($_POST['descricao_recolhimento'] ?? 'Recolhimento de Saldo');
    
    $valorRecebido = $_POST['valor_recolhimento'] ?? '0';
    $valor = (float) str_replace(',', '.', $valorRecebido);
    $redirect_to = '../pages/dashboard.php';

    try {
        if ($id_setor_origem <= 0 || $valor <= 0) {
            throw new Exception("Setor ou valor de recolhimento inválido.");
        }

        $pdo->beginTransaction();

        $setor = buscarSetorPorId($id_setor_origem);

        if ($setor === null || (float)$setor['saldo_atual'] < $valor) {
            throw new Exception("O setor não possui saldo suficiente ou não foi encontrado.");
        }

        $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual - :val WHERE id_setor = :id")->execute(['val' => $valor, 'id' => $id_setor_origem]);
        $pdo->prepare("UPDATE saldo_geral SET saldo_atual = saldo_atual + :val WHERE id_saldo_geral = 1")->execute(['val' => $valor]);

        $stmtMov = $pdo->prepare("INSERT INTO movimentacoes (tipo, valor, criado_em, descricao, status, id_admin_fk) VALUES ('RECEITA', :val, NOW(), :desc, 'ATIVA', :adm)");
        $stmtMov->execute(['val' => $valor, 'desc' => "Recolhimento de setor: " . $descricao, 'adm' => $id_admin]);

        $pdo->commit();
        header("Location: " . $redirect_to);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $msg_url = urlencode("Falha no Recolhimento: " . $e->getMessage());
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>