<?php
// actions/editar_registro.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/conexao.php';
require_once __DIR__ . '/../crud/crud_compromissos.php';
require_once __DIR__ . '/../crud/crud_contas_receber.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_setor = (int) $_POST['id_setor'];
    $id_registro = (int) $_POST['id_registro'];
    $tipo_registro = $_POST['tipo_registro']; 
    $redirect_to = $_POST['redirect_to'] ?? '../pages/dashboard.php';

    $descricao = trim($_POST['descricao']);
    $data = trim($_POST['data']);
    $id_categoria = (int) $_POST['id_categoria'];
    
    $valorRecebido = $_POST['valor'];
    $novo_valor = (float) str_replace(',', '.', $valorRecebido);

    try {
        if ($id_registro <= 0 || $novo_valor <= 0 || empty($descricao) || empty($data)) {
            throw new Exception("Dados inválidos. O valor deve ser maior que 0.");
        }

        $pdo->beginTransaction();

        if ($tipo_registro === 'compromisso') {
            editarCompromisso($id_registro, $descricao, $novo_valor, $data, $id_setor, $id_categoria);
        }
        
        elseif ($tipo_registro === 'conta_receber') {
            editarContaReceber($id_registro, $descricao, $novo_valor, $data, $id_setor, $id_categoria);
        }
        
        elseif ($tipo_registro === 'despesa_efetivada') {
            $stmtD = $pdo->prepare("SELECT valor FROM despesas WHERE id_despesa = :id FOR UPDATE");
            $stmtD->execute(['id' => $id_registro]);
            $valor_antigo = (float) $stmtD->fetchColumn();

            if ($valor_antigo !== $novo_valor) {
                $diferenca = $novo_valor - $valor_antigo;
                $stmtSetor = $pdo->prepare("SELECT saldo_atual FROM setores WHERE id_setor = :id FOR UPDATE");
                $stmtSetor->execute(['id' => $id_setor]);
                if ((float)$stmtSetor->fetchColumn() < $diferenca) throw new Exception("O setor não tem saldo para este aumento de despesa.");
                
                $pdo->prepare("UPDATE setores SET saldo_atual = saldo_atual - :dif WHERE id_setor = :id")->execute(['dif' => $diferenca, 'id' => $id_setor]);
            }

            $sql = "UPDATE despesas SET descricao = :desc, valor = :val, data = :data, id_categoria_fk = :cat WHERE id_despesa = :id";
            $pdo->prepare($sql)->execute(['desc'=>$descricao, 'val'=>$novo_valor, 'data'=>$data, 'cat'=>$id_categoria, 'id'=>$id_registro]);
        }
        
        elseif ($tipo_registro === 'receita_efetivada') {
            $stmtR = $pdo->prepare("SELECT valor FROM receitas WHERE id_receita = :id FOR UPDATE");
            $stmtR->execute(['id' => $id_registro]);
            $valor_antigo = (float) $stmtR->fetchColumn();

            if ($valor_antigo !== $novo_valor) {
                $diferenca = $novo_valor - $valor_antigo;
                $pdo->query("SELECT saldo_atual FROM saldo_geral WHERE id_saldo_geral = 1 FOR UPDATE");
                $pdo->prepare("UPDATE saldo_geral SET saldo_atual = saldo_atual + :dif WHERE id_saldo_geral = 1")->execute(['dif' => $diferenca]);
            }

            $sql = "UPDATE receitas SET descricao = :desc, valor = :val, data = :data, id_categoria_fk = :cat WHERE id_receita = :id";
            $pdo->prepare($sql)->execute(['desc'=>$descricao, 'val'=>$novo_valor, 'data'=>$data, 'cat'=>$id_categoria, 'id'=>$id_registro]);
        }

        $pdo->commit();
        header("Location: " . $redirect_to);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $msg_url = urlencode("Erro ao editar: " . $e->getMessage());
        $link_url = urlencode($redirect_to);
        header("Location: ../pages/erro.php?msg={$msg_url}&link={$link_url}");
        exit;
    }
}
?>