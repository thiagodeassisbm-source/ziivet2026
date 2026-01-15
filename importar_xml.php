<?php
// ATIVAÇÃO DE ERROS PARA DEBUG
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
require_once 'config/configuracoes.php';

$id_admin = $_SESSION['id_admin'] ?? 1;

// Verificação de segurança: Apenas administradores podem realizar importações manuais
if (!temPermissao('usuarios', 'listar')) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Acesso negado: Apenas administradores podem realizar esta operação.'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['xml_file'])) {
    try {
        // Validar arquivo
        if ($_FILES['xml_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Erro no upload do arquivo.");
        }

        // Ler conteúdo do XML
        $xml_content = file_get_contents($_FILES['xml_file']['tmp_name']);
        
        // Remover BOM se existir
        $xml_content = preg_replace('/^\xEF\xBB\xBF/', '', $xml_content);
        
        // Carregar XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        
        if (!$xml) {
            $errors = libxml_get_errors();
            $error_msg = "Erro ao processar XML: ";
            foreach ($errors as $error) {
                $error_msg .= $error->message . " ";
            }
            libxml_clear_errors();
            throw new Exception($error_msg);
        }

        // Registrar namespaces
        $namespaces = $xml->getNamespaces(true);
        
        // Tentar diferentes estruturas de NFe
        $nfe = null;
        if (isset($xml->NFe->infNFe)) {
            $nfe = $xml->NFe->infNFe;
        } elseif (isset($xml->infNFe)) {
            $nfe = $xml->infNFe;
        } elseif (isset($xml->protNFe)) {
            $nfe = $xml->protNFe->infNFe;
        }
        
        if (!$nfe) {
            throw new Exception("Estrutura do XML não reconhecida. Verifique se é um XML de NFe válido.");
        }

        // 1. DADOS DO FORNECEDOR (EMITENTE)
        $emit = $nfe->emit;
        if (!$emit) {
            throw new Exception("Dados do emitente não encontrados no XML.");
        }

        $cnpj_forn = (string)($emit->CNPJ ?? $emit->CPF ?? '');
        if (empty($cnpj_forn)) {
            throw new Exception("CNPJ/CPF do fornecedor não encontrado no XML.");
        }
        
        // Verificar se fornecedor já existe no banco
        $stmt_f = $pdo->prepare("SELECT id, nome_fantasia, razao_social FROM fornecedores WHERE cnpj = ? AND id_admin = ?");
        $stmt_f->execute([$cnpj_forn, $id_admin]);
        $forn_db = $stmt_f->fetch(PDO::FETCH_ASSOC);
        
        $endereco = '';
        if ($emit->enderEmit) {
            $endereco = trim(
                ((string)$emit->enderEmit->xLgr ?? '') . ', ' . 
                ((string)$emit->enderEmit->nro ?? '') . ' - ' . 
                ((string)$emit->enderEmit->xBairro ?? '')
            );
        }
        
        $dados_fornecedor = [
            'id' => $forn_db['id'] ?? null,
            'cnpj' => $cnpj_forn,
            'razao_social' => (string)($emit->xNome ?? ''),
            'nome_fantasia' => (string)($emit->xFant ?? $emit->xNome ?? ''),
            'ie' => (string)($emit->IE ?? ''),
            'endereco' => $endereco,
            'bairro' => (string)($emit->enderEmit->xBairro ?? ''),
            'cidade' => (string)($emit->enderEmit->xMun ?? ''),
            'uf' => (string)($emit->enderEmit->UF ?? ''),
            'cep' => (string)($emit->enderEmit->CEP ?? ''),
            'telefone' => (string)($emit->enderEmit->fone ?? ''),
            'email' => (string)($emit->email ?? ''),
            'existe_no_banco' => !empty($forn_db)
        ];

        // 2. DADOS DA COMPRA (IDE e TOTAIS)
        $ide = $nfe->ide;
        $total = $nfe->total->ICMSTot;
        
        if (!$ide || !$total) {
            throw new Exception("Dados da nota fiscal incompletos.");
        }
        
        $chave_nfe = '';
        if (isset($nfe->attributes()->Id)) {
            $chave_nfe = str_replace('NFe', '', (string)$nfe->attributes()->Id);
        }
        
        $data_emissao = '';
        if (isset($ide->dhEmi)) {
            try {
                $dt = new DateTime((string)$ide->dhEmi);
                $data_emissao = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $data_emissao = date('Y-m-d H:i:s');
            }
        }

        $dados_compra = [
            'nf_numero' => (string)($ide->nNF ?? ''),
            'nf_serie' => (string)($ide->serie ?? ''),
            'data_emissao' => $data_emissao,
            'chave_nfe' => $chave_nfe,
            'natureza_operacao' => (string)($ide->natOp ?? ''),
            'tipo_nf' => (string)($ide->tpNF ?? '1'),
            'valor_produtos' => (float)($total->vProd ?? 0),
            'valor_frete' => (float)($total->vFrete ?? 0),
            'valor_seguro' => (float)($total->vSeg ?? 0),
            'valor_desconto' => (float)($total->vDesc ?? 0),
            'valor_outros' => (float)($total->vOutro ?? 0),
            'valor_icms' => (float)($total->vICMS ?? 0),
            'valor_ipi' => (float)($total->vIPI ?? 0),
            'valor_total' => (float)($total->vNF ?? 0) // vNF é o Valor Líquido (R$ 656,58)
        ];

        // 3. DADOS DOS ITENS (DET)
        $itens = [];
        if (!isset($nfe->det)) {
            throw new Exception("Nenhum item encontrado na nota fiscal.");
        }
        
        $item_numero = 1;
        foreach ($nfe->det as $item) {
            $prod = $item->prod;
            if (!$prod) continue;
            
            $ean = (string)($prod->cEAN ?? '');
            $ean = ($ean !== 'SEM GTIN' && !empty($ean)) ? $ean : '';
            
            $produto_cadastrado = null;
            if (!empty($ean)) {
                $stmt_prod = $pdo->prepare("SELECT id, nome, preco_venda FROM produtos WHERE gtin = ? AND id_admin = ? LIMIT 1");
                $stmt_prod->execute([$ean, $id_admin]);
                $produto_cadastrado = $stmt_prod->fetch(PDO::FETCH_ASSOC);
            }
            
            if (!$produto_cadastrado) {
                $nome_prod = (string)($prod->xProd ?? '');
                if (!empty($nome_prod)) {
                    $stmt_prod = $pdo->prepare("SELECT id, nome, preco_venda FROM produtos WHERE LOWER(nome) LIKE LOWER(?) AND id_admin = ? LIMIT 1");
                    $stmt_prod->execute(['%' . $nome_prod . '%', $id_admin]);
                    $produto_cadastrado = $stmt_prod->fetch(PDO::FETCH_ASSOC);
                }
            }

            $itens[] = [
                'numero_item' => $item_numero++,
                'id_produto' => $produto_cadastrado['id'] ?? null,
                'nome' => (string)($prod->xProd ?? ''),
                'ean' => $ean,
                'ncm' => (string)($prod->NCM ?? ''),
                'cfop' => (string)($prod->CFOP ?? ''),
                'unidade' => (string)($prod->uCom ?? 'UN'),
                'quantidade' => (float)($prod->qCom ?? 0),
                'valor_unitario' => (float)($prod->vUnCom ?? 0),
                'valor_total' => (float)($prod->vProd ?? 0),
                'produto_cadastrado' => !empty($produto_cadastrado),
                'nome_produto_cadastrado' => $produto_cadastrado['nome'] ?? ''
            ];
        }

        // 4. EXTRAÇÃO DE PARCELAS (DUPLICATAS) - ADICIONADO PARA RESOLVER O PROBLEMA
        $parcelas = [];
        if (isset($nfe->cobr->dup)) {
            foreach ($nfe->cobr->dup as $dup) {
                $parcelas[] = [
                    'numero' => (string)($dup->nDup ?? ''),
                    'vencimento' => (string)($dup->dVenc ?? ''),
                    'valor' => (float)($dup->vDup ?? 0)
                ];
            }
        }
        
        // Se não houver parcelas no XML, cria uma parcela única com o valor total
        if (empty($parcelas)) {
            $parcelas[] = [
                'numero' => '1',
                'vencimento' => date('Y-m-d'),
                'valor' => (float)($total->vNF ?? 0)
            ];
        }

        // 5. INFORMAÇÕES ADICIONAIS
        $info_complementar = '';
        if (isset($nfe->infAdic->infCpl)) {
            $info_complementar = (string)$nfe->infAdic->infCpl;
        }

        // Retornar dados estruturados com parcelas incluídas
        echo json_encode([
            'status' => 'success',
            'message' => 'XML importado com sucesso!',
            'dados' => [
                'fornecedor' => $dados_fornecedor,
                'compra' => $dados_compra,
                'itens' => $itens,
                'parcelas' => $parcelas, // Enviando as parcelas para o compras.php
                'info_adicional' => [
                    'total_itens' => count($itens),
                    'info_complementar' => $info_complementar,
                    'fornecedor_cadastrado' => $dados_fornecedor['existe_no_banco']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(400);
echo json_encode([
    'status' => 'error',
    'message' => 'Método de requisição inválido ou arquivo não enviado.'
], JSON_UNESCAPED_UNICODE);