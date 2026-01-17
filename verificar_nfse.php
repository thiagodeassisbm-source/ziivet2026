<?php
/**
 * VERIFICADOR DE CONFIGURAÇÕES NFS-e (Nota Fiscal de Serviço Eletrônica)
 * Este arquivo testa se todas as configurações necessárias estão corretas
 */

require_once 'auth.php';
require_once 'config/configuracoes.php';

$id_admin = $_SESSION['id_admin'] ?? 1;

echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <title>Verificador NFS-e - ZiipVet</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #622599; border-bottom: 3px solid #622599; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; border-left: 4px solid #622599; padding-left: 10px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .ok { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🧪 Verificador de Configurações NFS-e</h1>";
echo "<p style='color:#666;'>Este teste verifica se o sistema está pronto para emitir Notas Fiscais de Serviço.</p>";

// ========== 1. VERIFICAR CONFIGURAÇÕES FISCAIS ==========
echo "<h2>1️⃣ Configurações Fiscais Básicas</h2>";

try {
    $stmt = $pdo->prepare("SELECT * FROM configuracoes_fiscais WHERE id_admin = ?");
    $stmt->execute([$id_admin]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        echo "<div class='status ok'>✅ Registro de configurações encontrado no banco de dados.</div>";
        
        echo "<table>";
        echo "<tr><th>Campo</th><th>Valor</th><th>Status</th></tr>";
        
        // Inscrição Municipal
        $im = $config['inscricao_municipal'] ?? '';
        $im_status = !empty($im) ? "<span class='badge badge-success'>OK</span>" : "<span class='badge badge-danger'>FALTANDO</span>";
        echo "<tr><td>Inscrição Municipal</td><td>" . htmlspecialchars($im) . "</td><td>$im_status</td></tr>";
        
        // CNAE Principal
        $cnae = $config['cnae_principal'] ?? '';
        $cnae_status = !empty($cnae) ? "<span class='badge badge-success'>OK</span>" : "<span class='badge badge-warning'>Opcional</span>";
        echo "<tr><td>CNAE Principal</td><td>" . htmlspecialchars($cnae) . "</td><td>$cnae_status</td></tr>";
        
        // Regime Tributário
        $regime = $config['regime_tributario'] ?? '';
        $regime_nome = $regime == 1 ? 'Simples Nacional' : ($regime == 3 ? 'Regime Normal' : 'Não definido');
        $regime_status = !empty($regime) ? "<span class='badge badge-success'>OK</span>" : "<span class='badge badge-danger'>FALTANDO</span>";
        echo "<tr><td>Regime Tributário</td><td>$regime_nome</td><td>$regime_status</td></tr>";
        
        // Série NFS-e
        $serie = $config['serie_nfse'] ?? '';
        $serie_status = !empty($serie) ? "<span class='badge badge-success'>OK</span>" : "<span class='badge badge-warning'>Usando padrão: 1</span>";
        echo "<tr><td>Série NFS-e</td><td>" . ($serie ?: '1 (padrão)') . "</td><td>$serie_status</td></tr>";
        
        // Última NFS-e
        $ultima = $config['num_ultima_nfse'] ?? 0;
        echo "<tr><td>Última NFS-e Emitida</td><td>$ultima</td><td><span class='badge badge-success'>OK</span></td></tr>";
        
        echo "</table>";
        
    } else {
        echo "<div class='status error'>❌ Nenhuma configuração fiscal encontrada. Configure em: NFS-e > Configurações NFS-e</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='status error'>❌ Erro ao buscar configurações: " . $e->getMessage() . "</div>";
}

// ========== 2. VERIFICAR SERVIÇOS CADASTRADOS ==========
echo "<h2>2️⃣ Serviços Cadastrados (NBS / LC 116)</h2>";

try {
    $stmt = $pdo->prepare("SELECT * FROM nfse_servicos_config WHERE id_admin = ?");
    $stmt->execute([$id_admin]);
    $servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($servicos) > 0) {
        echo "<div class='status ok'>✅ " . count($servicos) . " serviço(s) configurado(s).</div>";
        echo "<table>";
        echo "<tr><th>CNAE</th><th>Item LC 116</th><th>Código NBS</th><th>Alíquota ISS</th></tr>";
        foreach ($servicos as $s) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($s['cnae']) . "</td>";
            echo "<td>" . htmlspecialchars($s['item_lc116']) . "</td>";
            echo "<td>" . htmlspecialchars($s['codigo_nbs']) . "</td>";
            echo "<td>" . number_format($s['aliquota_iss'], 2, ',', '.') . "%</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='status warning'>⚠️ Nenhum serviço cadastrado. Configure em: NFS-e > Configurações NFS-e > Aba 'Serviços Prestados'</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='status error'>❌ Erro ao buscar serviços: " . $e->getMessage() . "</div>";
}

// ========== 3. VERIFICAR DADOS DA EMPRESA ==========
echo "<h2>3️⃣ Dados da Empresa (Emitente)</h2>";

try {
    $stmt = $pdo->query("SELECT * FROM minha_empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($empresa) {
        echo "<table>";
        echo "<tr><th>Campo</th><th>Valor</th><th>Status</th></tr>";
        
        $razao = $empresa['razao_social'] ?? '';
        $razao_status = !empty($razao) ? "<span class='badge badge-success'>OK</span>" : "<span class='badge badge-danger'>FALTANDO</span>";
        echo "<tr><td>Razão Social</td><td>" . htmlspecialchars($razao) . "</td><td>$razao_status</td></tr>";
        
        $cnpj = $empresa['cnpj'] ?? '';
        $cnpj_status = !empty($cnpj) ? "<span class='badge badge-success'>OK</span>" : "<span class='badge badge-danger'>FALTANDO</span>";
        echo "<tr><td>CNPJ</td><td>" . htmlspecialchars($cnpj) . "</td><td>$cnpj_status</td></tr>";
        
        $endereco = $empresa['endereco'] ?? '';
        $endereco_status = !empty($endereco) ? "<span class='badge badge-success'>OK</span>" : "<span class='badge badge-warning'>Recomendado</span>";
        echo "<tr><td>Endereço</td><td>" . htmlspecialchars($endereco) . "</td><td>$endereco_status</td></tr>";
        
        $cidade = $empresa['cidade'] ?? '';
        $cidade_status = !empty($cidade) ? "<span class='badge badge-success'>OK</span>" : "<span class='badge badge-danger'>FALTANDO</span>";
        echo "<tr><td>Cidade</td><td>" . htmlspecialchars($cidade) . "</td><td>$cidade_status</td></tr>";
        
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div class='status error'>❌ Erro ao buscar dados da empresa: " . $e->getMessage() . "</div>";
}

// ========== 4. VERIFICAR CERTIFICADO DIGITAL ==========
echo "<h2>4️⃣ Certificado Digital</h2>";

try {
    $stmt = $pdo->prepare("SELECT certificado_nome, certificado_validade, LENGTH(certificado_arquivo) as tamanho FROM configuracoes_fiscais WHERE id_admin = ?");
    $stmt->execute([$id_admin]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cert && $cert['tamanho'] > 0) {
        $tamanho_kb = number_format($cert['tamanho'] / 1024, 2);
        echo "<div class='status ok'>✅ Certificado digital encontrado no banco de dados ({$tamanho_kb} KB)</div>";
        echo "<table>";
        echo "<tr><th>Campo</th><th>Valor</th></tr>";
        echo "<tr><td>Nome do Arquivo</td><td>" . htmlspecialchars($cert['certificado_nome'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td>Validade</td><td>" . htmlspecialchars($cert['certificado_validade'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td>Tamanho</td><td>{$tamanho_kb} KB</td></tr>";
        echo "</table>";
    } else {
        echo "<div class='status warning'>⚠️ Certificado digital não encontrado. Faça upload em: NFC-e > Configurações Fiscais > Aba 'Certificado'</div>";
    }
} catch (Exception $e) {
    echo "<div class='status error'>❌ Erro ao verificar certificado: " . $e->getMessage() . "</div>";
}

// ========== 5. ARQUIVO DE EMISSÃO ==========
echo "<h2>5️⃣ Arquivo de Emissão NFS-e</h2>";

$emitir_path = __DIR__ . '/nfe/nfse/emitir_nfse.php';
if (file_exists($emitir_path)) {
    echo "<div class='status ok'>✅ Arquivo de emissão existe: $emitir_path</div>";
} else {
    echo "<div class='status error'>❌ Arquivo de emissão NÃO encontrado: $emitir_path</div>";
    echo "<div class='status warning'>⚠️ Este arquivo precisa ser criado para processar a emissão de NFS-e.</div>";
}

// ========== RESUMO FINAL ==========
echo "<h2>📊 Resumo e Próximos Passos</h2>";

$problemas = [];
if (empty($config['inscricao_municipal'])) $problemas[] = "Configure a Inscrição Municipal";
if (empty($config['regime_tributario'])) $problemas[] = "Defina o Regime Tributário";
if (empty($empresa['razao_social'])) $problemas[] = "Preencha a Razão Social da empresa";
if (empty($empresa['cnpj'])) $problemas[] = "Preencha o CNPJ";
if (!file_exists($emitir_path)) $problemas[] = "Criar o arquivo emitir_nfse.php";

if (count($problemas) == 0) {
    echo "<div class='status ok'>✅ <strong>Sistema pronto para emitir NFS-e!</strong></div>";
    echo "<div class='status info'>💡 <strong>Próximo passo:</strong> Realize uma venda de serviço e teste a emissão.</div>";
} else {
    echo "<div class='status warning'>⚠️ <strong>Pendências encontradas:</strong></div>";
    echo "<ul>";
    foreach ($problemas as $p) {
        echo "<li>$p</li>";
    }
    echo "</ul>";
}

echo "<div style='margin-top:30px; padding:15px; background:#f8f9fa; border-radius:5px;'>";
echo "<strong>🔗 Links Úteis:</strong><br>";
echo "• <a href='nfe/nfse/config_nfse.php'>Configurações NFS-e</a><br>";
echo "• <a href='nfe/configuracoes_nfe.php'>Configurações Fiscais Gerais</a><br>";
echo "• <a href='vendas.php'>Ponto de Venda (PDV)</a>";
echo "</div>";

echo "</div></body></html>";
?>
