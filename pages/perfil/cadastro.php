<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$caminhoCrud = __DIR__ . '/../../crud/crud_administradores.php';

if (!file_exists($caminhoCrud)) {
    die("<h3 style='color:red;'>ERRO FATAL:</h3> O ficheiro do CRUD não foi encontrado no caminho:<br> <b>$caminhoCrud</b>");
}

require_once $caminhoCrud;

$erros = [];
$sucesso = '';
$nome_input = '';
$email_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_input = $_POST['nome'] ?? '';
    $email_input = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if ($senha !== $confirmar_senha) {
        $erros['confirmar_senha'] = 'As senhas não coincidem.';
    } else {
        try {
            criarAdministrador($nome_input, $email_input, $senha);
            
            $sucesso = 'Administrador criado com sucesso! Pode fazer login.';
            $nome_input = ''; 
            $email_input = '';
            
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'nome') !== false) {
                $erros['nome'] = $msg;
            } elseif (stripos($msg, 'e-mail') !== false || stripos($msg, 'email') !== false) {
                $erros['email'] = $msg;
            } elseif (stripos($msg, 'senha') !== false) {
                $erros['senha'] = $msg;
            } else {
                $erros['geral'] = $msg;
            }
        } catch (DomainException $e) {
            $erros['email'] = $e->getMessage();
        } catch (Exception $e) {
            $erros['geral'] = 'Erro interno: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyCash - Cadastro de Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/login.css">
</head>
<body>

    <div class="auth-container">
        <div class="auth-header">
            <div class="logo-container">
                <i class="fa-solid fa-user-shield"></i> Cadastro
            </div>
            <p>Registe um novo administrador no sistema.</p>
        </div>

        <form action="cadastro.php" method="POST">
            
            <?php if (!empty($sucesso)): ?>
                <div style="background: #d1f4e0; color: #0d7a46; padding: 10px; border-radius: 8px; text-align: center; margin-bottom: 20px; font-weight: 500; font-size: 14px;">
                    <i class="fa-solid fa-check-circle"></i> <?= $sucesso ?>
                </div>
            <?php endif; ?>

            <?php if (isset($erros['geral'])): ?>
                <div class="erro erro-geral">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= $erros['geral'] ?>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="nome">Nome Completo</label>
                <input type="text" id="nome" name="nome" class="form-control" placeholder="Nome do administrador" value="<?= htmlspecialchars($nome_input, ENT_QUOTES, 'UTF-8') ?>">
                <?php if (isset($erros['nome'])): ?>
                    <span class="erro"><i class="fa-solid fa-triangle-exclamation"></i> <?= $erros['nome'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">E-mail Corporativo</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Insira o seu e-mail" value="<?= htmlspecialchars($email_input, ENT_QUOTES, 'UTF-8') ?>">
                <?php if (isset($erros['email'])): ?>
                    <span class="erro"><i class="fa-solid fa-triangle-exclamation"></i> <?= $erros['email'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" class="form-control" placeholder="Crie uma senha forte">
                <?php if (isset($erros['senha'])): ?>
                    <span class="erro"><i class="fa-solid fa-triangle-exclamation"></i> <?= $erros['senha'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="confirmar_senha">Confirmar Senha</label>
                <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" placeholder="Confirme a sua senha">
                <?php if (isset($erros['confirmar_senha'])): ?>
                    <span class="erro"><i class="fa-solid fa-triangle-exclamation"></i> <?= $erros['confirmar_senha'] ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-primary">Criar Administrador</button>

        </form>
    </div>

</body>
</html>