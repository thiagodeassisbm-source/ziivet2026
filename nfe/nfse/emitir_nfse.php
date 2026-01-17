<?php
/**
 * Emissão de NFS-e (Nota Fiscal de Serviço Eletrônica)
 * Padrão Nacional NFS-e
 */

require_once '../../auth.php';
require_once '../../config/configuracoes.php';
require_once '../../vendor/autoload.php';

use App\Services\NFSeService;

header('Content-Type: application/json');

$id_admin = $_SESSION['id_admin'] ?? 1;

try {
    // Receber ID da venda
    $id_venda = $_POST['id_venda'] ?? 0;
    
    if (!$id_venda) {
        throw new Exception('ID da venda não informado');
    }
    
    // Buscar dados da venda
    $stmt = $pdo->prepare("
        SELECT v.*, c.nome as cliente_nome, c.cpf_cnpj, c.endereco, c.numero, 
               c.bairro, c.cep, c.cidade, c.codigo_municipio
        FROM vendas v
        LEFT JOIN clientes c ON v.id_cliente = c.id
        WHERE v.id = ? AND v.id_admin = ?
    ");
    $stmt->execute([$id_venda, $id_admin]);
    $venda = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venda) {
        throw new Exception('Venda não encontrada');
    }
    
    // Buscar itens de serviço da venda
    $stmt = $pdo->prepare("
        SELECT vi.*, p.produto as descricao, p.tipo
        FROM vendas_itens vi
        INNER JOIN produtos p ON vi.id_produto = p.id
        WHERE vi.id_venda = ? AND p.tipo = 'servico'
    ");
    $stmt->execute([$id_venda]);
    $itensServico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($itensServico)) {
        throw new Exception('Nenhum serviço encontrado nesta venda');
    }
    
    // Buscar configurações fiscais
    $stmt = $pdo->prepare("SELECT * FROM configuracoes_fiscais WHERE id_admin = ?");
    $stmt->execute([$id_admin]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config) {
        throw new Exception('Configurações fiscais não encontradas');
    }
    
    // Buscar dados da empresa
    $stmt = $pdo->query("SELECT * FROM minha_empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empresa) {
        throw new Exception('Dados da empresa não encontrados');
    }
    
    // Buscar configuração do serviço (NBS, Alíquota, etc)
    $stmt = $pdo->prepare("SELECT * FROM nfse_servicos_config WHERE id_admin = ? LIMIT 1");
    $stmt->execute([$id_admin]);
    $servicoConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$servicoConfig) {
        throw new Exception('Configuração de serviço não encontrada. Configure em: NFS-e > Configurações > Serviços Prestados');
    }
    
    // Calcular valores
    $valorTotal = 0;
    $descricaoServicos = [];
    
    foreach ($itensServico as $item) {
        $valorTotal += $item['valor_total'];
        $descricaoServicos[] = $item['descricao'];
    }
    
    // Preparar dados para emissão
    $numeroProximaDPS = ($config['num_ultima_nfse'] ?? 0) + 1;
    
    $dadosEmissao = [
        'id_venda' => $id_venda,
        'numero_dps' => str_pad($numeroProximaDPS, 15, '0', STR_PAD_LEFT),
        'valor_servico' => $valorTotal,
        'desconto' => $venda['desconto'] ?? 0,
        'cliente' => [
            'nome' => $venda['cliente_nome'],
            'cpf_cnpj' => $venda['cpf_cnpj'],
            'endereco' => $venda['endereco'] ?? '',
            'numero' => $venda['numero'] ?? 'S/N',
            'bairro' => $venda['bairro'] ?? '',
            'cep' => $venda['cep'] ?? '',
            'cidade' => $venda['cidade'] ?? 'Goiânia',
            'codigo_municipio' => $venda['codigo_municipio'] ?? '5208707'
        ],
        'servico' => [
            'codigo_nbs' => $servicoConfig['codigo_nbs'],
            'descricao' => implode(', ', $descricaoServicos),
            'aliquota_iss' => $servicoConfig['aliquota_iss']
        ]
    ];
    
    // Adicionar configurações
    $configEmissao = [
        'id_admin' => $id_admin,
        'cnpj' => $empresa['cnpj'],
        'inscricao_municipal' => $config['inscricao_municipal'],
        'serie_nfse' => $config['serie_nfse'] ?? '1',
        'ambiente' => $config['ambiente'] ?? 2
    ];
    
    // Instanciar serviço de NFS-e
    $nfseService = new NFSeService($configEmissao);
    
    // Emitir NFS-e
    $resultado = $nfseService->emitir($dadosEmissao);
    
    // Retornar resultado
    echo json_encode($resultado);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
