<?php
/**
 * =========================================================================================
 * ZIIPVET - CONFIGURAÇÃO MINHA EMPRESA
 * ARQUIVO: minha_empresa.php
 * VERSÃO: 18.1.0 - LAYOUT NORMALIZADO 2026
 * =========================================================================================
 */
require_once 'auth.php';
require_once 'config/configuracoes.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\Application\Service\FileUploaderService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$msg_feedback = "";
$status_feedback = "";

// ==============================================================
// 1. PROCESSAMENTO DO FORMULÁRIO (POST)
// ==============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $logo_path = $_POST['logo_atual'] ?? '';

        if (isset($_FILES['logomarca']) && $_FILES['logomarca']['error'] === UPLOAD_ERR_OK) {
            try {
                $uploader = new FileUploaderService();
                $diretorio = 'uploads/empresa/';
                $tipos_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
                $nome_logo = $uploader->upload($_FILES['logomarca'], $diretorio, $tipos_permitidos);
                $logo_path = $diretorio . $nome_logo;
                
                if (!empty($_POST['logo_atual']) && file_exists($_POST['logo_atual'])) {
                    unlink($_POST['logo_atual']);
                }
            } catch (Exception $e) {
                $msg_feedback = "Erro no upload: " . $e->getMessage();
                $status_feedback = "error";
                throw $e; 
            }
        }

        $existe = $pdo->query("SELECT COUNT(*) FROM minha_empresa WHERE id = 1")->fetchColumn();

        if ($existe > 0) {
            $sql = "UPDATE minha_empresa SET 
                    razao_social = :razao,
                    nome_fantasia = :fantasia,
                    cnpj = :cnpj,
                    telefone = :telefone,
                    logomarca = :logo,
                    endereco = :endereco,
                    bairro = :bairro,
                    cidade = :cidade
                    WHERE id = 1";
        } else {
            $sql = "INSERT INTO minha_empresa (id, razao_social, nome_fantasia, cnpj, telefone, logomarca, endereco, bairro, cidade) 
                    VALUES (1, :razao, :fantasia, :cnpj, :telefone, :logo, :endereco, :bairro, :cidade)";
        }
        
        $stmt = $pdo->prepare($sql);
        $resultado = $stmt->execute([
            ':razao'    => $_POST['razao_social'],
            ':fantasia' => $_POST['nome_fantasia'],
            ':cnpj'     => $_POST['cnpj'],
            ':telefone' => $_POST['telefone'],
            ':logo'     => $logo_path,
            ':endereco' => $_POST['endereco'],
            ':bairro'   => $_POST['bairro'],
            ':cidade'   => $_POST['cidade']
        ]);

        if ($resultado) {
            $msg_feedback = "Informações da empresa salvas com sucesso!";
            $status_feedback = "success";
        } else {
            $msg_feedback = "Erro ao salvar as informações.";
            $status_feedback = "error";
        }

    } catch (PDOException $e) {
        $msg_feedback = "Erro ao salvar: " . $e->getMessage();
        $status_feedback = "error";
    }
}

// ==============================================================
// 2. CARREGAR DADOS ATUAIS
// ==============================================================
try {
    $empresa = $pdo->query("SELECT * FROM minha_empresa WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    if (!$empresa) {
        $pdo->exec("INSERT INTO minha_empresa (id, razao_social, nome_fantasia) VALUES (1, 'Sua Empresa LTDA', 'Sua Empresa')");
        $empresa = $pdo->query("SELECT * FROM minha_empresa WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) { $empresa = []; }

$titulo_pagina = "Minha Empresa";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$titulo_pagina?> | ZiipVet</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Exo:wght@300;400;600;700;800&family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/header.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .card-empresa { 
            background: #fff; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
            border-top: 3px solid var(--cor-principal);
            margin-bottom: 30px;
        }
        
        .page-header-title {
            font-size: 26px;
            margin-bottom: 25px;
            color: #444;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header-title i {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--cor-principal), #8e44ad);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .logo-section {
            display: flex; align-items: center; gap: 30px; 
            background: #f8f9fa; padding: 30px; border-radius: 10px; 
            border: 1px solid #e9ecef; margin-bottom: 35px;
        }
        
        .logo-preview { 
            width: 150px; height: 150px; object-fit: contain; 
            background: #fff; border-radius: 10px; border: 2px solid #ddd; padding: 15px;
        }

        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .full-width { grid-column: span 3; }
        .half-width { grid-column: span 2; }

        label { font-size: 13px; font-weight: 700; color: #2c3e50; text-transform: uppercase; letter-spacing: 0.5px; }
        input { padding: 12px 16px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; outline: none; background: #fff; transition: all 0.3s ease; }
        input:focus { border-color: var(--cor-principal); box-shadow: 0 0 0 3px rgba(98, 37, 153, 0.1); }

        .info-box {
            background: #e7f3ff; border-left: 4px solid var(--cor-principal);
            padding: 15px 20px; border-radius: 6px; margin-bottom: 30px;
            display: flex; align-items: center; gap: 15px; font-weight: 600;
        }

        .btn-salvar { 
            background: var(--cor-principal); color: #fff; border: none; padding: 15px 35px; 
            border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; 
            text-transform: uppercase; margin-top: 25px; transition: all 0.3s ease; 
            display: inline-flex; align-items: center; gap: 10px;
        }
        .btn-salvar:hover { background: #4a1c74; transform: translateY(-2px); }
        
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width, .half-width { grid-column: span 1; }
            .logo-section { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container">
        <?php include 'menu/menulateral.php'; ?>
    </aside>

    <header class="top-header">
        <?php include 'menu/faixa.php'; ?>
    </header>

    <main class="main-content">
        <h2 class="page-header-title">
            <i class="fas fa-building"></i> 
            Dados da Empresa
        </h2>
        
        <div class="card-empresa">
            <div class="info-box">
                <i class="fas fa-info-circle" style="color: var(--cor-principal); font-size: 20px;"></i>
                Estes dados serão utilizados em relatórios, notas fiscais e documentos oficiais.
            </div>
            
            <form action="minha_empresa.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="logo_atual" value="<?= htmlspecialchars($empresa['logomarca'] ?? '') ?>">

                <div class="logo-section">
                    <img id="preview" src="<?= !empty($empresa['logomarca']) ? $empresa['logomarca'] : 'https://via.placeholder.com/150' ?>" class="logo-preview">
                    <div class="form-group" style="flex: 1;">
                        <label>Logomarca da Clínica</label>
                        <input type="file" name="logomarca" accept="image/*" onchange="previewImage(this)" style="padding: 10px; border: 2px dashed #ddd;">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group half-width">
                        <label>Razão Social *</label>
                        <input type="text" name="razao_social" value="<?= htmlspecialchars($empresa['razao_social'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>CNPJ</label>
                        <input type="text" name="cnpj" value="<?= htmlspecialchars($empresa['cnpj'] ?? '') ?>">
                    </div>

                    <div class="form-group half-width">
                        <label>Nome Fantasia *</label>
                        <input type="text" name="nome_fantasia" value="<?= htmlspecialchars($empresa['nome_fantasia'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?= htmlspecialchars($empresa['telefone'] ?? '') ?>">
                    </div>

                    <div class="form-group full-width">
Rede, Número, Complemento">
                        <label>Endereço Completo</label>
                        <input type="text" name="endereco" value="<?= htmlspecialchars($empresa['endereco'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" value="<?= htmlspecialchars($empresa['bairro'] ?? '') ?>">
                    </div>
                    <div class="form-group half-width">
                        <label>Cidade / UF</label>
                        <input type="text" name="cidade" value="<?= htmlspecialchars($empresa['cidade'] ?? '') ?>">
                    </div>
                </div>

                <button type="submit" class="btn-salvar">
                    <i class="fas fa-save"></i> Atualizar Informações
                </button>
            </form>
        </div>
    </main>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { preview.src = e.target.result; }
                reader.readAsDataURL(input.files[0]);
            }
        }
        <?php if ($msg_feedback): ?>
        Swal.fire({
            title: '<?= $status_feedback == "success" ? "Sucesso!" : "Erro!" ?>',
            text: '<?= addslashes($msg_feedback) ?>',
            icon: '<?= $status_feedback ?>',
            confirmButtonColor: '#622599'
        });
        <?php endif; ?>
    </script>
</body>
</html>