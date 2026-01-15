<?php
/**
 * =========================================================================================
 * ZIIPVET - CONFIGURAÇÃO MINHA EMPRESA
 * ARQUIVO: minha_empresa.php
 * VERSÃO: 18.0.0 - LAYOUT BLINDADO V16.2
 * =========================================================================================
 */
require_once 'auth.php';
require_once 'config/configuracoes.php';

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
        // Recupera o caminho da logo atual
        $logo_path = $_POST['logo_atual'] ?? '';

        // Processar Upload da Logomarca
        if (isset($_FILES['logomarca']) && $_FILES['logomarca']['error'] === UPLOAD_ERR_OK) {
            $diretorio = 'uploads/empresa/';
            if (!is_dir($diretorio)) {
                mkdir($diretorio, 0777, true);
            }
            
            // Tipos permitidos
            $tipos_permitidos = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $tipo_arquivo = $_FILES['logomarca']['type'];
            
            if (in_array($tipo_arquivo, $tipos_permitidos)) {
                $extensao = pathinfo($_FILES['logomarca']['name'], PATHINFO_EXTENSION);
                $nome_logo = "logo_empresa_" . time() . "." . $extensao;
                $caminho_final = $diretorio . $nome_logo;
                
                if (move_uploaded_file($_FILES['logomarca']['tmp_name'], $caminho_final)) {
                    $logo_path = $caminho_final;
                    
                    // Deletar logo antiga se existir
                    if (!empty($_POST['logo_atual']) && file_exists($_POST['logo_atual'])) {
                        unlink($_POST['logo_atual']);
                    }
                }
            }
        }

        // Verificar se já existe registro
        $existe = $pdo->query("SELECT COUNT(*) FROM minha_empresa WHERE id = 1")->fetchColumn();

        if ($existe > 0) {
            // UPDATE
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
            // INSERT
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
} catch (PDOException $e) {
    $empresa = [];
}

$titulo_pagina = "Minha Empresa";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <base href="https://www.lepetboutique.com.br/app/">
    
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
    :root { 
        --fundo: #ecf0f5; 
        --primaria: #1c329f; 
        --sucesso: #28a745; 
        --borda: #d2d6de; 
        --sidebar-collapsed: 75px; 
        --sidebar-expanded: 260px; 
        --header-height: 80px; 
        --transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    
    body { 
        font-family: 'Open Sans', sans-serif; 
        background-color: var(--fundo); 
        font-size: 15px; 
        color: #333; 
        overflow-x: hidden;
        line-height: 1.6;
    }

    /* Layout V16.2 Estruturado e Blindado */
    aside.sidebar-container { 
        position: fixed; left: 0; top: 0; height: 100vh; width: var(--sidebar-collapsed); 
        z-index: 1000; background: #fff; transition: width var(--transition); 
        box-shadow: 2px 0 5px rgba(0,0,0,0.05); 
    }
    aside.sidebar-container:hover { width: var(--sidebar-expanded); }
    
    header.top-header { 
        position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; 
        height: var(--header-height); z-index: 900; transition: left var(--transition);
        background: #fff; border-bottom: 1px solid #eee;
        margin: 0 !important;
    }
    aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }
    
    main.main-content { 
        margin-left: var(--sidebar-collapsed); 
        padding: calc(var(--header-height) + 30px) 30px 40px; 
        transition: margin-left var(--transition); 
        width: calc(100% - var(--sidebar-collapsed)); /* Blindagem de largura */
    }
    aside.sidebar-container:hover ~ main.main-content { 
        margin-left: var(--sidebar-expanded); 
        width: calc(100% - var(--sidebar-expanded)); 
    }

    /* Card Padronizado - AGORA EM TELA CHEIA */
    .card-empresa { 
        background: #fff; 
        padding: 40px; 
        border-radius: 12px; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
        border-top: 5px solid var(--primaria);
        width: 100%;           /* Ocupa 100% do main-content */
        max-width: none;       /* Removido o limite de 1200px */
        margin: 0;             /* Removido margin auto */
    }
    
    .card-empresa h2 {
        color: #2c3e50; 
        margin-bottom: 30px; 
        font-weight: 700; 
        font-size: 26px;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    /* Logo Preview Section */
    .logo-section {
        display: flex; 
        align-items: center; 
        gap: 30px; 
        background: #f8f9fa; 
        padding: 30px; 
        border-radius: 10px; 
        border: 1px solid #e9ecef; 
        margin-bottom: 35px;
    }
    
    .logo-preview { 
        width: 150px; 
        height: 150px; 
        object-fit: contain; 
        background: #fff; 
        border-radius: 10px; 
        border: 2px solid var(--borda);
        padding: 15px;
    }

    /* Form Grid */
    .form-grid { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 25px; 
    }
    
    .form-group { 
        display: flex; 
        flex-direction: column; 
        gap: 8px; 
    }
    
    .full-width { grid-column: span 3; }
    .half-width { grid-column: span 2; }

    label { 
        font-size: 13px; 
        font-weight: 700; 
        color: #2c3e50; 
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    input { 
        padding: 14px 16px; 
        border: 1px solid var(--borda); 
        border-radius: 8px; 
        font-size: 15px; 
        outline: none; 
        background: #fff; 
        transition: all 0.3s ease;
    }
    
    input:focus { 
        border-color: var(--primaria); 
        box-shadow: 0 0 0 3px rgba(28, 50, 159, 0.1); 
    }

    .info-box {
        background: #e7f3ff;
        border-left: 4px solid var(--primaria);
        padding: 15px 20px;
        border-radius: 6px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        gap: 15px;
        font-weight: 600;
    }

    /* Botão Salvar Profissional */
    .btn-salvar { 
        background: linear-gradient(135deg, var(--sucesso) 0%, #20c997 100%);
        color: #fff; 
        border: none; 
        padding: 18px 45px; 
        border-radius: 8px; 
        font-weight: 800; 
        font-size: 14px; 
        cursor: pointer; 
        text-transform: uppercase; 
        margin-top: 35px; 
        transition: all 0.3s ease; 
        display: flex; 
        align-items: center; 
        gap: 12px;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
    }
    
    .btn-salvar:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 6px 16px rgba(40, 167, 69, 0.3); 
        filter: brightness(1.1);
    }

    .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }
</style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="card-empresa">
            <h2>
                <i class="fas fa-building" style="color: var(--primaria);"></i> 
                Dados da Empresa
            </h2>
            
            <div class="info-box">
                <i class="fas fa-info-circle" style="color: var(--primaria); font-size: 20px;"></i>
                Estes dados serão utilizados em relatórios, notas fiscais e documentos oficiais emitidos pelo ZiipVet.
            </div>
            
            <form action="minha_empresa.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="logo_atual" value="<?= htmlspecialchars($empresa['logomarca'] ?? '') ?>">

                <div class="logo-section">
                    <img id="preview" 
                         src="<?= !empty($empresa['logomarca']) ? $empresa['logomarca'] : 'https://via.placeholder.com/150/1c329f/FFFFFF?text=Logo' ?>" 
                         class="logo-preview"
                         alt="Logo da Empresa">
                    <div class="form-group" style="flex: 1;">
                        <label>Logomarca da Clínica</label>
                        <input type="file" 
                               name="logomarca" 
                               accept="image/*" 
                               onchange="previewImage(this)" 
                               style="padding: 12px; border-style: dashed; font-size: 14px; background: #fff; border: 2px dashed var(--borda);">
                        <small style="color: #888; font-size: 13px; margin-top: 5px; font-weight: 500;">
                            <i class="fas fa-image"></i> PNG, JPG ou GIF. Recomendado: 300x300px.
                        </small>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group half-width">
                        <label for="razao_social">Razão Social *</label>
                        <input type="text" id="razao_social" name="razao_social" 
                               value="<?= htmlspecialchars($empresa['razao_social'] ?? '') ?>" 
                               required placeholder="Ex: Clínica Veterinária ZiipVet LTDA">
                    </div>
                    <div class="form-group">
                        <label for="cnpj">CNPJ</label>
                        <input type="text" id="cnpj" name="cnpj" 
                               value="<?= htmlspecialchars($empresa['cnpj'] ?? '') ?>" 
                               placeholder="00.000.000/0000-00" maxlength="18">
                    </div>

                    <div class="form-group half-width">
                        <label for="nome_fantasia">Nome Fantasia *</label>
                        <input type="text" id="nome_fantasia" name="nome_fantasia" 
                               value="<?= htmlspecialchars($empresa['nome_fantasia'] ?? '') ?>" 
                               required placeholder="Ex: ZiipVet">
                    </div>
                    <div class="form-group">
                        <label for="telefone">Telefone / WhatsApp</label>
                        <input type="text" id="telefone" name="telefone" 
                               value="<?= htmlspecialchars($empresa['telefone'] ?? '') ?>" 
                               placeholder="(00) 00000-0000" maxlength="15">
                    </div>

                    <div class="form-group full-width">
                        <label for="endereco">Endereço Completo</label>
                        <input type="text" id="endereco" name="endereco" 
                               value="<?= htmlspecialchars($empresa['endereco'] ?? '') ?>" 
                               placeholder="Rua, Número, Complemento">
                    </div>

                    <div class="form-group">
                        <label for="bairro">Bairro</label>
                        <input type="text" id="bairro" name="bairro" 
                               value="<?= htmlspecialchars($empresa['bairro'] ?? '') ?>" 
                               placeholder="Ex: Centro">
                    </div>
                    <div class="form-group half-width">
                        <label for="cidade">Cidade / UF</label>
                        <input type="text" id="cidade" name="cidade" 
                               value="<?= htmlspecialchars($empresa['cidade'] ?? '') ?>" 
                               placeholder="Ex: Goiânia / GO">
                    </div>
                </div>

                <button type="submit" class="btn-salvar">
                    <i class="fas fa-save"></i> Atualizar Informações
                </button>
            </form>
        </div>
        
        <div style="height: 50px;"></div>
    </main>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        <?php if ($msg_feedback): ?>
        Swal.fire({
            title: '<?= $status_feedback == "success" ? "Sucesso!" : "Erro!" ?>',
            text: '<?= addslashes($msg_feedback) ?>',
            icon: '<?= $status_feedback ?>',
            confirmButtonColor: '#1c329f',
            confirmButtonText: 'OK'
        });
        <?php endif; ?>
    </script>
</body>
</html>