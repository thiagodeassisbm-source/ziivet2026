<?php
/**
 * Serviço de NFS-e - Padrão Nacional
 * Comunicação com a API Nacional de NFS-e
 */

namespace App\Services;

class NFSeService
{
    private $config;
    private $certificado;
    private $senhaCertificado;
    
    // URLs da API Nacional NFS-e
    private $urlProducao = 'https://api.nfse.gov.br/v1';
    private $urlHomologacao = 'https://hom-api.nfse.gov.br/v1';
    
    public function __construct($config)
    {
        $this->config = $config;
        $this->loadCertificado();
    }
    
    /**
     * Carrega o certificado digital do banco de dados
     */
    private function loadCertificado()
    {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT certificado_arquivo, senha_certificado FROM configuracoes_fiscais WHERE id_admin = ?");
        $stmt->execute([$this->config['id_admin']]);
        $cert = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($cert && !empty($cert['certificado_arquivo'])) {
            $this->certificado = $cert['certificado_arquivo'];
            $this->senhaCertificado = $cert['senha_certificado'] ?? '';
        }
    }
    
    /**
     * Retorna a URL base da API conforme ambiente
     */
    private function getApiUrl()
    {
        $ambiente = $this->config['ambiente'] ?? 2; // 1=Produção, 2=Homologação
        return ($ambiente == 1) ? $this->urlProducao : $this->urlHomologacao;
    }
    
    /**
     * Emite uma NFS-e
     * 
     * @param array $dadosVenda Dados da venda
     * @return array Resultado da emissão
     */
    public function emitir($dadosVenda)
    {
        try {
            // 1. Gerar DPS (XML)
            $dpsXml = $this->gerarDPS($dadosVenda);
            
            // 2. Assinar DPS
            $dpsAssinada = $this->assinarXML($dpsXml);
            
            // 3. Comprimir e codificar
            $dpsComprimida = $this->comprimirBase64($dpsAssinada);
            
            // 4. Enviar para API
            $resultado = $this->enviarParaAPI($dpsComprimida);
            
            // 5. Processar resposta
            return $this->processarResposta($resultado, $dadosVenda);
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao emitir NFS-e: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Gera o XML da DPS (Declaração Prévia de Serviço)
     */
    private function gerarDPS($dados)
    {
        $dps = new \DOMDocument('1.0', 'UTF-8');
        $dps->formatOutput = false;
        $dps->preserveWhiteSpace = false;
        
        // Raiz
        $pedido = $dps->createElement('Pedido');
        $pedido->setAttribute('xmlns', 'http://www.sped.fazenda.gov.br/nfse');
        $dps->appendChild($pedido);
        
        // InfPedido
        $infPedido = $dps->createElement('InfPedido');
        $infPedido->setAttribute('Id', 'DPS' . $dados['numero_dps']);
        $pedido->appendChild($infPedido);
        
        // Identificação da DPS
        $infDPS = $dps->createElement('infDPS');
        
        // Número e Série
        $this->addElement($dps, $infDPS, 'nDPS', $dados['numero_dps']);
        $this->addElement($dps, $infDPS, 'serie', $this->config['serie_nfse'] ?? '1');
        $this->addElement($dps, $infDPS, 'dEmi', date('Y-m-d'));
        $this->addElement($dps, $infDPS, 'hEmi', date('H:i:s'));
        
        // Tipo de Tributação (1=Tributado no município)
        $this->addElement($dps, $infDPS, 'tpTrib', '1');
        
        // Prestador
        $prest = $dps->createElement('prest');
        $this->addElement($dps, $prest, 'CNPJ', preg_replace('/\D/', '', $this->config['cnpj']));
        $this->addElement($dps, $prest, 'IM', $this->config['inscricao_municipal']);
        $this->addElement($dps, $prest, 'cMunPrest', '5208707'); // Código IBGE Goiânia
        $infDPS->appendChild($prest);
        
        // Tomador
        $tomador = $dps->createElement('tomador');
        $cpfCnpj = preg_replace('/\D/', '', $dados['cliente']['cpf_cnpj']);
        
        if (strlen($cpfCnpj) == 11) {
            $this->addElement($dps, $tomador, 'CPF', $cpfCnpj);
        } else {
            $this->addElement($dps, $tomador, 'CNPJ', $cpfCnpj);
        }
        
        $this->addElement($dps, $tomador, 'xNome', $dados['cliente']['nome']);
        
        // Endereço do tomador
        if (!empty($dados['cliente']['endereco'])) {
            $end = $dps->createElement('end');
            $this->addElement($dps, $end, 'xLgr', $dados['cliente']['endereco']);
            $this->addElement($dps, $end, 'nro', $dados['cliente']['numero'] ?? 'S/N');
            $this->addElement($dps, $end, 'xBairro', $dados['cliente']['bairro'] ?? '');
            $this->addElement($dps, $end, 'cMun', $dados['cliente']['codigo_municipio'] ?? '5208707');
            $this->addElement($dps, $end, 'CEP', preg_replace('/\D/', '', $dados['cliente']['cep'] ?? ''));
            $tomador->appendChild($end);
        }
        
        $infDPS->appendChild($tomador);
        
        // Serviço
        $serv = $dps->createElement('serv');
        
        // Código do serviço (NBS)
        $this->addElement($dps, $serv, 'cServ', $dados['servico']['codigo_nbs']);
        $this->addElement($dps, $serv, 'xServ', $dados['servico']['descricao']);
        
        // Valores
        $this->addElement($dps, $serv, 'vServ', number_format($dados['valor_servico'], 2, '.', ''));
        $this->addElement($dps, $serv, 'vDesc', number_format($dados['desconto'] ?? 0, 2, '.', ''));
        $this->addElement($dps, $serv, 'vBC', number_format($dados['valor_servico'] - ($dados['desconto'] ?? 0), 2, '.', ''));
        
        // Alíquota ISS
        $aliquota = $dados['servico']['aliquota_iss'] ?? 2.00;
        $this->addElement($dps, $serv, 'pISS', number_format($aliquota, 2, '.', ''));
        
        // Valor ISS
        $valorBase = $dados['valor_servico'] - ($dados['desconto'] ?? 0);
        $valorISS = $valorBase * ($aliquota / 100);
        $this->addElement($dps, $serv, 'vISS', number_format($valorISS, 2, '.', ''));
        
        $infDPS->appendChild($serv);
        
        // Valor Total
        $this->addElement($dps, $infDPS, 'vNF', number_format($valorBase, 2, '.', ''));
        
        $infPedido->appendChild($infDPS);
        
        return $dps->saveXML();
    }
    
    /**
     * Adiciona elemento ao XML
     */
    private function addElement($doc, $parent, $name, $value)
    {
        $element = $doc->createElement($name, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
        $parent->appendChild($element);
    }
    
    /**
     * Assina o XML com o certificado digital
     */
    private function assinarXML($xml)
    {
        // TODO: Implementar assinatura digital usando o certificado
        // Por enquanto retorna o XML sem assinatura (funcionará em homologação)
        return $xml;
    }
    
    /**
     * Comprime e codifica em Base64
     */
    private function comprimirBase64($xml)
    {
        $comprimido = gzencode($xml, 9);
        return base64_encode($comprimido);
    }
    
    /**
     * Envia DPS para a API Nacional
     */
    private function enviarParaAPI($dpsComprimida)
    {
        $url = $this->getApiUrl() . '/nfse';
        
        $payload = json_encode([
            'dps' => $dpsComprimida
        ]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        // Configurar certificado digital (mTLS)
        if ($this->certificado) {
            $certFile = tempnam(sys_get_temp_dir(), 'cert');
            file_put_contents($certFile, $this->certificado);
            
            curl_setopt($ch, CURLOPT_SSLCERT, $certFile);
            curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $this->senhaCertificado);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new \Exception('Erro na comunicação com a API: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if (isset($certFile)) {
            unlink($certFile);
        }
        
        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }
    
    /**
     * Processa a resposta da API
     */
    private function processarResposta($resultado, $dadosVenda)
    {
        if ($resultado['http_code'] == 200 || $resultado['http_code'] == 201) {
            $nfse = $resultado['response'];
            
            // Salvar NFS-e no banco
            $this->salvarNFSe($nfse, $dadosVenda);
            
            return [
                'success' => true,
                'numero' => $nfse['numero'] ?? 'N/A',
                'chave' => $nfse['chaveAcesso'] ?? '',
                'url' => $nfse['urlConsulta'] ?? '',
                'xml' => $nfse['xml'] ?? ''
            ];
        } else {
            $erro = $resultado['response']['mensagem'] ?? 'Erro desconhecido';
            return [
                'success' => false,
                'message' => $erro
            ];
        }
    }
    
    /**
     * Salva a NFS-e no banco de dados
     */
    private function salvarNFSe($nfse, $dadosVenda)
    {
        global $pdo;
        
        $stmt = $pdo->prepare("
            INSERT INTO nfse_emitidas 
            (id_venda, numero, chave_acesso, xml, data_emissao, id_admin) 
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        
        $stmt->execute([
            $dadosVenda['id_venda'],
            $nfse['numero'] ?? 0,
            $nfse['chaveAcesso'] ?? '',
            $nfse['xml'] ?? '',
            $this->config['id_admin']
        ]);
        
        // Atualizar número da última NFS-e
        $pdo->prepare("UPDATE configuracoes_fiscais SET num_ultima_nfse = ? WHERE id_admin = ?")
            ->execute([$nfse['numero'], $this->config['id_admin']]);
    }
}
?>
