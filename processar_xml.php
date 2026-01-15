<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/auth.php';
use App\Application\Service\FileUploaderService;

// Verificação de segurança: Apenas administradores podem processar XML
if (!temPermissao('usuarios', 'listar')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado: Apenas administradores podem realizar esta operação.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Limpar buffers de saída
while (ob_get_level()) {
    ob_end_clean();
}

// Configurar erros (não exibir na tela)
error_reporting(0);
ini_set('display_errors', 0);

// Função para retornar JSON
function retornarJSON($dados) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['xml_file'])) {
    retornarJSON(['status' => 'error', 'message' => 'Arquivo não enviado']);
}

// Sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$id_admin = $_SESSION['id_admin'] ?? 1;

// Conexão com banco via configuração central
require_once __DIR__ . '/config/configuracoes.php';
// $pdo já está disponível via configuracoes.php

// Processar XML via Serviço Seguro
try {
    $uploader = new FileUploaderService();
    $uploadDir = __DIR__ . '/uploads/temp_xml'; // Pasta para processamento temporário
    
    // Tipos XML permitidos
    $allowedTypes = ['text/xml', 'application/xml'];
    
    // Realiza o upload seguro (valida MIME real e renomeia o arquivo)
    $fileName = $uploader->upload($_FILES['xml_file'], $uploadDir, $allowedTypes);
    $filePath = $uploadDir . '/' . $fileName;

    // Ler conteúdo do arquivo seguro
    $xml_content = file_get_contents($filePath);
    
    // Remover o arquivo temporário após leitura (opcional, dependendo da necessidade de auditoria)
    unlink($filePath);

    if (empty($xml_content)) {
        throw new Exception("Arquivo XML vazio");
    }
    
    // Remover BOM
    $xml_content = preg_replace('/^\xEF\xBB\xBF/', '', trim($xml_content));
    
    // Carregar XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xml_content, 'SimpleXMLElement', LIBXML_NOCDATA);
    
    if (!$xml) {
        $errors = libxml_get_errors();
        $error_msg = "XML inválido";
        if (!empty($errors)) {
            $error_msg .= ": " . $errors[0]->message;
        }
        libxml_clear_errors();
        throw new Exception($error_msg);
    }

    // Detectar estrutura
    $nfe = null;
    if (isset($xml->NFe->infNFe)) {
        $nfe = $xml->NFe->infNFe;
    } elseif (isset($xml->infNFe)) {
        $nfe = $xml->infNFe;
    } else {
        throw new Exception("Estrutura XML não reconhecida");
    }

    // ===== 1. FORNECEDOR =====
    $emit = $nfe->emit;
    if (!$emit) {
        throw new Exception("Dados do emitente não encontrados");
    }
    
    $cnpj = (string)($emit->CNPJ ?? $emit->CPF ?? '');
    if (empty($cnpj)) {
        throw new Exception("CNPJ/CPF não encontrado");
    }
    
    // Buscar fornecedor
    $stmt = $pdo->prepare("SELECT id FROM fornecedores WHERE cnpj = ? AND id_admin = ? LIMIT 1");
    $stmt->execute([$cnpj, $id_admin]);
    $forn = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $fornecedor = [
        'id' => $forn['id'] ?? null,
        'cnpj' => $cnpj,
        'razao_social' => (string)($emit->xNome ?? ''),
        'nome_fantasia' => (string)($emit->xFant ?? $emit->xNome ?? ''),
        'cep' => (string)($emit->enderEmit->CEP ?? ''),
        'endereco' => (string)($emit->enderEmit->xLgr ?? ''),
        'bairro' => (string)($emit->enderEmit->xBairro ?? ''),
        'cidade' => (string)($emit->enderEmit->xMun ?? ''),
        'telefone' => (string)($emit->enderEmit->fone ?? ''),
        'existe_no_banco' => !empty($forn)
    ];

    // ===== 2. COMPRA =====
    $ide = $nfe->ide;
    $total = $nfe->total->ICMSTot;
    
    if (!$ide || !$total) {
        throw new Exception("Dados da nota incompletos");
    }
    
    $chave = '';
    if (isset($nfe->attributes()->Id)) {
        $chave = str_replace('NFe', '', (string)$nfe->attributes()->Id);
    }
    
    $data_emissao = date('Y-m-d H:i:s');
    if (isset($ide->dhEmi)) {
        try {
            $data_emissao = (new DateTime((string)$ide->dhEmi))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // Usar data atual
        }
    }
    
    $compra = [
        'nf_numero' => (string)($ide->nNF ?? ''),
        'nf_serie' => (string)($ide->serie ?? ''),
        'data_emissao' => $data_emissao,
        'chave_nfe' => $chave,
        'valor_produtos' => (float)($total->vProd ?? 0),
        'valor_frete' => (float)($total->vFrete ?? 0),
        'valor_total' => (float)($total->vNF ?? 0)
    ];

    // ===== 3. ITENS =====
    $itens = [];
    $n = 0;
    
    if (!isset($nfe->det) || count($nfe->det) === 0) {
        throw new Exception("Nenhum item encontrado");
    }
    
    foreach ($nfe->det as $item) {
        $n++;
        $prod = $item->prod;
        if (!$prod) continue;
        
        $ean = (string)($prod->cEAN ?? '');
        $ean = ($ean === 'SEM GTIN' || $ean === 'SEM') ? '' : $ean;
        
        $prod_db = null;
        if (!empty($ean)) {
            $stmt = $pdo->prepare("SELECT id FROM produtos WHERE gtin = ? AND id_admin = ? LIMIT 1");
            $stmt->execute([$ean, $id_admin]);
            $prod_db = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        $itens[] = [
            'numero_item' => $n,
            'id_produto' => $prod_db['id'] ?? null,
            'nome' => (string)($prod->xProd ?? ''),
            'ean' => $ean,
            'ncm' => (string)($prod->NCM ?? ''),
            'cfop' => (string)($prod->CFOP ?? ''),
            'quantidade' => (float)($prod->qCom ?? 0),
            'valor_unitario' => (float)($prod->vUnCom ?? 0),
            'valor_total' => (float)($prod->vProd ?? 0),
            'produto_cadastrado' => !empty($prod_db)
        ];
    }

    // ===== 4. PARCELAS (DUPLICATAS) - CORREÇÃO AQUI! =====
    $parcelas = [];
    
    // Verificar se existe nó de cobrança com duplicatas
    if (isset($nfe->cobr->dup)) {
        foreach ($nfe->cobr->dup as $dup) {
            // Extrair vencimento
            $venc = (string)($dup->dVenc ?? '');
            try {
                if (!empty($venc)) {
                    // Tentar converter formato YYYY-MM-DD
                    $dt = new DateTime($venc);
                    $venc = $dt->format('Y-m-d');
                } else {
                    // Se não tem vencimento, usar 30 dias
                    $venc = date('Y-m-d', strtotime('+30 days'));
                }
            } catch (Exception $e) {
                $venc = date('Y-m-d', strtotime('+30 days'));
            }
            
            // Extrair valor da duplicata
            $valor = (float)($dup->vDup ?? 0);
            
            // IMPORTANTE: Só adicionar se tiver valor válido
            if ($valor > 0) {
                $parcelas[] = [
                    'numero' => (string)($dup->nDup ?? count($parcelas) + 1),
                    'vencimento' => $venc,
                    'valor' => $valor
                ];
            }
        }
    }
    
    // Se não encontrou parcelas no XML ou todas vieram zeradas
    if (empty($parcelas)) {
        // Criar parcela única com valor total da nota
        $parcelas[] = [
            'numero' => '001',
            'vencimento' => date('Y-m-d', strtotime('+30 days')),
            'valor' => $compra['valor_total']
        ];
    }

    // ===== RETORNAR SUCESSO =====
    retornarJSON([
        'status' => 'success',
        'message' => 'XML importado com sucesso!',
        'dados' => [
            'fornecedor' => $fornecedor,
            'compra' => $compra,
            'itens' => $itens,
            'parcelas' => $parcelas,
            'info_adicional' => [
                'total_itens' => count($itens),
                'total_parcelas' => count($parcelas),
                'valor_total_parcelas' => array_sum(array_column($parcelas, 'valor'))
            ]
        ]
    ]);
    
} catch (Exception $e) {
    retornarJSON([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}