<?php
/**
 * =========================================================================================
 * ZIIPVET - IMPRESSÃO DE RECEITA MÉDICA
 * ARQUIVO: imprimir_receita.php
 * VERSÃO: 3.0.0 - LAYOUT CENTRALIZADO
 * =========================================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Receber dados via POST
$id_paciente = $_POST['id_paciente'] ?? null;
$conteudo_receita = $_POST['conteudo_receita'] ?? '';

if (!$id_paciente) {
    die("Erro: Nenhum paciente selecionado.");
}

try {
    // 1. Buscar dados da empresa
    $empresa = $pdo->query("SELECT * FROM minha_empresa WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    if (!$empresa) {
        $empresa = ['nome_fantasia' => 'Clínica Veterinária', 'endereco' => '', 'cidade' => '', 'telefone' => '', 'cnpj' => '', 'logomarca' => ''];
    }
    
    // Ajustar caminho da logomarca para funcionar em /app/consultas/
    if (!empty($empresa['logomarca'])) {
        if (strpos($empresa['logomarca'], 'http') !== 0 && strpos($empresa['logomarca'], '/') !== 0) {
            $empresa['logomarca'] = '../' . $empresa['logomarca'];
        }
    }
    
    // 2. Buscar dados do paciente e tutor
    $sql_paciente = "SELECT p.*, c.nome as nome_tutor, c.cpf_cnpj, c.endereco, c.numero, c.bairro, c.cidade, c.estado, c.telefone
                     FROM pacientes p
                     INNER JOIN clientes c ON p.id_cliente = c.id
                     WHERE p.id = ?";
    $stmt = $pdo->prepare($sql_paciente);
    $stmt->execute([$id_paciente]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paciente) {
        die("Erro: Paciente não encontrado.");
    }
    
    // Calcular idade do paciente
    $idade = '';
    if ($paciente['data_nascimento']) {
        $hoje = new DateTime();
        $nasc = new DateTime($paciente['data_nascimento']);
        $diff = $hoje->diff($nasc);
        $idade = $diff->y . " anos, " . $diff->m . " meses, " . $diff->d . " dias";
    } elseif ($paciente['idade_anos'] || $paciente['idade_meses']) {
        $idade = ($paciente['idade_anos'] ?: 0) . " anos, " . ($paciente['idade_meses'] ?: 0) . " meses";
    }
    
    // Formatar peso
    $peso = $paciente['peso'] ? number_format((float)str_replace(',', '.', $paciente['peso']), 3, ',', '.') : '-';
    
    // Montar endereço completo do responsável
    $endereco_completo = trim($paciente['endereco'] . ' ' . $paciente['numero']);
    if ($paciente['bairro']) $endereco_completo .= ', ' . $paciente['bairro'];
    if ($paciente['cidade']) $endereco_completo .= ' - ' . $paciente['cidade'];
    if ($paciente['estado']) $endereco_completo .= '/' . $paciente['estado'];
    
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

$data_atual = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receita - <?= htmlspecialchars($paciente['nome_paciente']) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        @page {
            size: A4;
            margin: 1cm;
        }
        
        body {
            font-family: 'Open Sans', Arial, sans-serif;
            background: #fff;
            color: #333;
            line-height: 1.4;
            font-size: 13px;
        }
        
        .container {
            max-width: 21cm;
            min-height: 29.7cm;
            margin: 0 auto;
            padding: 40px;
            background: #fff;
            border: 1px solid #ddd;
        }
        
        /* Cabeçalho Centralizado */
        .header {
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 2px solid #333;
            margin-bottom: 25px;
        }
        
        .logo-section {
            margin-bottom: 15px;
        }
        
        .logo {
            max-width: 150px;
            max-height: 150px;
            object-fit: contain;
            margin: 0 auto;
            display: block;
        }
        
        .logo-placeholder {
            width: 150px;
            height: 150px;
            border: 2px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: #999;
            text-align: center;
            margin: 0 auto;
        }
        
        .empresa-info {
            margin-top: 15px;
        }
        
        .empresa-info h1 {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }
        
        .empresa-info p {
            font-size: 12px;
            color: #666;
            margin: 3px 0;
            line-height: 1.4;
        }
        
        /* Título Receita */
        .titulo-receita {
            text-align: center;
            padding: 12px 0;
            border-bottom: 1px solid #333;
            margin-bottom: 20px;
        }
        
        .titulo-receita h2 {
            font-size: 16px;
            font-weight: 700;
            color: #333;
        }
        
        /* Dados do Paciente - Formato Tabela */
        .dados-paciente {
            margin-bottom: 25px;
        }
        
        .dados-tabela {
            width: 100%;
            border-collapse: collapse;
        }
        
        .dados-tabela td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
        }
        
        .dados-tabela td:first-child {
            font-weight: 700;
            color: #333;
            width: 110px;
        }
        
        .dados-tabela td:nth-child(2) {
            color: #555;
        }
        
        .dados-tabela td:nth-child(3) {
            font-weight: 700;
            color: #333;
            width: 110px;
            padding-left: 20px;
        }
        
        .dados-tabela td:nth-child(4) {
            color: #555;
        }
        
        /* Linha Responsável */
        .linha-responsavel {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #ddd;
        }
        
        /* Prescrição */
        .prescricao {
            min-height: 300px;
            margin-bottom: 30px;
            padding: 15px 0;
        }
        
        .prescricao-conteudo {
            font-size: 13px;
            line-height: 1.6;
            color: #333;
        }
        
        .prescricao-conteudo p {
            margin-bottom: 10px;
        }
        
        .prescricao-conteudo ul,
        .prescricao-conteudo ol {
            margin-left: 20px;
            margin-bottom: 10px;
        }
        
        .prescricao-conteudo li {
            margin-bottom: 5px;
        }
        
        /* Rodapé */
        .rodape {
            margin-top: 80px;
        }
        
        .rodape-grid {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        
        .local-data {
            font-size: 12px;
            color: #666;
        }
        
        .assinatura {
            text-align: center;
        }
        
        .linha-assinatura {
            width: 200px;
            border-top: 1px solid #333;
            margin: 0 0 5px 0;
        }
        
        .assinatura p {
            font-size: 11px;
            color: #666;
        }
        
        /* Estilo para Impressão */
        @media print {
            body {
                background: #fff;
            }
            
            .container {
                border: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        /* Botão Imprimir */
        .btn-imprimir {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #1c329f;
            color: #fff;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000;
            font-family: 'Open Sans', sans-serif;
        }
        
        .btn-imprimir:hover {
            background: #15257a;
        }
    </style>
</head>
<body>

<button class="btn-imprimir no-print" onclick="window.print()">
    🖨️ Imprimir
</button>

<div class="container">
    <!-- CABEÇALHO CENTRALIZADO -->
    <div class="header">
        <div class="logo-section">
            <?php if (!empty($empresa['logomarca'])): ?>
                <img src="<?= htmlspecialchars($empresa['logomarca']) ?>" 
                     alt="Logo da Empresa" 
                     class="logo"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="logo-placeholder" style="display: none;">
                    LOGO
                </div>
            <?php else: ?>
                <div class="logo-placeholder">
                    LOGO
                </div>
            <?php endif; ?>
        </div>
        
        <div class="empresa-info">
            <h1><?= htmlspecialchars($empresa['nome_fantasia'] ?? 'Clínica Veterinária') ?></h1>
            <?php if ($empresa['endereco']): ?>
                <p><?= htmlspecialchars($empresa['endereco']) ?><?php if($empresa['bairro']): ?>, <?= htmlspecialchars($empresa['bairro']) ?><?php endif; ?></p>
            <?php endif; ?>
            <?php if ($empresa['cidade']): ?>
                <p><?= htmlspecialchars($empresa['cidade']) ?></p>
            <?php endif; ?>
            <?php if ($empresa['telefone']): ?>
                <p><?= htmlspecialchars($empresa['telefone']) ?></p>
            <?php endif; ?>
            <?php if ($empresa['cnpj']): ?>
                <p>CNPJ: <?= htmlspecialchars($empresa['cnpj']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- TÍTULO -->
    <div class="titulo-receita">
        <h2>Receita</h2>
    </div>
    
    <!-- DADOS DO PACIENTE -->
    <div class="dados-paciente">
        <table class="dados-tabela">
            <tr>
                <td>Animal:</td>
                <td><?= htmlspecialchars($paciente['id_cliente']) ?> - <?= htmlspecialchars($paciente['nome_paciente']) ?></td>
                <td>Peso:</td>
                <td><?= $peso ?> kg em <?= date('d/m/Y') ?></td>
            </tr>
            <tr>
                <td>Espécie:</td>
                <td><?= htmlspecialchars($paciente['especie'] ?? '-') ?></td>
                <td>Sexo:</td>
                <td><?= htmlspecialchars($paciente['sexo'] ?? '-') ?></td>
            </tr>
            <tr>
                <td>Raça:</td>
                <td><?= htmlspecialchars($paciente['raca'] ?? '-') ?></td>
                <td>Idade:</td>
                <td><?= $idade ?: '-' ?></td>
            </tr>
            <tr>
                <td>Pelagem:</td>
                <td><?= htmlspecialchars($paciente['pelagem'] ?? '-') ?></td>
                <td>Chip:</td>
                <td><?= htmlspecialchars($paciente['chip'] ?? '-') ?></td>
            </tr>
        </table>
        
        <div class="linha-responsavel">
            <table class="dados-tabela" style="border: none;">
                <tr>
                    <td style="border: none;">Responsável:</td>
                    <td style="border: none;" colspan="3"><?= htmlspecialchars($paciente['id_cliente']) ?> - <?= htmlspecialchars($paciente['nome_tutor']) ?></td>
                </tr>
                <tr>
                    <td style="border: none;">CPF:</td>
                    <td style="border: none;"><?= htmlspecialchars($paciente['cpf_cnpj'] ?? '-') ?></td>
                    <td style="border: none;"></td>
                    <td style="border: none;"></td>
                </tr>
                <tr>
                    <td style="border: none;">Endereço:</td>
                    <td style="border: none;" colspan="3"><?= htmlspecialchars($endereco_completo ?: '-') ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <!-- PRESCRIÇÃO -->
    <div class="prescricao">
        <div class="prescricao-conteudo">
            <?= $conteudo_receita ?: '<p style="color: #999; font-style: italic;">Nenhuma prescrição informada.</p>' ?>
        </div>
    </div>
    
    <!-- RODAPÉ -->
    <div class="rodape">
        <div class="rodape-grid">
            <div class="local-data">
                <p><?= htmlspecialchars($empresa['cidade'] ?? 'Goiânia') ?>, GO, <?= $data_atual ?></p>
                <p style="margin-top: 5px;">thiago silva de assis</p>
            </div>
            
            <div class="assinatura">
                <div class="linha-assinatura"></div>
                <p>Assinatura do Responsável</p>
            </div>
        </div>
    </div>
</div>

</body>
</html>