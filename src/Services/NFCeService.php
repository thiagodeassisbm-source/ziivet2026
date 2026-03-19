<?php
/**
 * ZIIPVET - Serviço de Emissão de NFC-e
 * Utiliza a biblioteca NFePHP
 */

namespace App\Services;

use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use NFePHP\Common\Certificate;
use NFePHP\Common\Soap\SoapCurl;
use NFePHP\NFe\Common\Standardize;
use PDO;
use Exception;
use stdClass;

// Patch para constants faltando na biblioteca NFePHP
if (!defined('SOAP_1_1')) define('SOAP_1_1', 1);
if (!defined('SOAP_1_2')) define('SOAP_1_2', 2);

class NFCeService
{
    private PDO $pdo;
    private array $configFiscal;
    private array $configEmpresa;
    private ?Tools $tools = null;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->loadConfig();
    }
    
    /**
     * Carrega configurações do banco de dados
     */
    private function loadConfig(): void
    {
        // Configurações Fiscais (CSC, Certificado, Série)
        $stmt = $this->pdo->prepare("SELECT * FROM configuracoes_fiscais WHERE id_admin = ?");
        $stmt->execute([1]); 
        $this->configFiscal = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Dados da Empresa (CNPJ, Razão Social, Endereço)
        $stmt = $this->pdo->prepare("SELECT * FROM config_clinica WHERE id = 1");
        $stmt->execute();
        $this->configEmpresa = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    /**
     * Inicializa as ferramentas NFePHP
     */
    private function initTools(): void
    {
        if ($this->tools !== null) {
            return;
        }

        if (empty($this->configEmpresa['cnpj'])) {
            throw new Exception("CNPJ da empresa não configurado.");
        }

        // Limpar CNPJ
        $cnpj = preg_replace('/[^0-9]/', '', $this->configEmpresa['cnpj']);
        $uf = 'GO'; // TODO: Pegar do endereço da empresa, mapear sigla para código IBGE se necessário
        
        // Configuração JSON para NFePHP
        $configJson = [
            "atualizacao" => date("Y-m-d H:i:s"),
            "tpAmb" => (int)($this->configFiscal['ambiente'] ?? 2), // 1=Produção, 2=Homologação
            "razaosocial" => $this->configEmpresa['razao_social'],
            "siglaUF" => "GO", // TODO: Melhorar mapeamento de UF
            "cnpj" => $cnpj,
            "schemes" => "PL_009_V4",
            "versao" => "4.00",
            "tokenIBPT" => "",
            "CSC" => $this->configFiscal['csc_producao'] ?? "",
            "CSCid" => $this->configFiscal['csc_id'] ?? ""
        ];
        
        // Tentar carregar configurações de certificado da tabela unificada config_certificados
        $stmtCert = $this->pdo->query("SELECT * FROM config_certificados WHERE id = 1");
        $certConfig = $stmtCert->fetch(PDO::FETCH_ASSOC);

        // Definir caminho e senha
        // IMPORTANTE:
        // - `config_certificados.caminho_arquivo` pode ficar com valor antigo (ex.: "convertido_...").
        // - `configuracoes_fiscais.certificado_arquivo` normalmente reflete o último upload do formulário.
        // Vamos considerar ambos.
        $certFilenamePrimary = basename((string)($certConfig['caminho_arquivo'] ?? ''));
        $certFilenameLegacy = basename((string)($this->configFiscal['certificado_arquivo'] ?? ''));
        $certFilename = $certFilenamePrimary !== '' ? $certFilenamePrimary : $certFilenameLegacy;
        $certPassword = (string)($certConfig['senha_certificado'] ?? "");

        // O servidor pode servir do subdiretório `app/`, mas os certificados podem estar na raiz de `public_html/`.
        // Então tentamos múltiplos diretórios possíveis.
        $certDirs = [
            __DIR__ . '/../../uploads/certificados/',      // /public_html/app/uploads/certificados
            __DIR__ . '/../../../uploads/certificados/',    // /public_html/uploads/certificados
            __DIR__ . '/../../../../uploads/certificados/', // fallback extra
        ];

        $certPath = null;
        // Primeiro tenta arquivos explícitos vindos do banco (novo e legado)
        $primaryNames = array_values(array_unique(array_filter([$certFilenamePrimary, $certFilenameLegacy])));
        foreach ($certDirs as $dirTry) {
            foreach ($primaryNames as $n) {
                $p = $dirTry . $n;
                if (file_exists($p)) {
                    $certPath = $p;
                    $certFilename = $n;
                    break 2;
                }
            }
        }

        if (empty($certFilename) && empty($primaryNames)) {
            throw new Exception("Arquivo do certificado digital não informado (certFilename vazio).");
        }

        // Fallback: em alguns cenários (ex.: pastas recriadas), o arquivo convertido pode não existir,
        // mas o arquivo original pode existir (ou vice-versa).
        if (!$certPath || !file_exists($certPath)) {
            $candidates = [];
            // inclui os dois nomes conhecidos + nome ativo
            $candidates = array_merge($candidates, $primaryNames);
            $candidates[] = $certFilename;

            // Caso a extensão no banco não coincida com a do arquivo no servidor
            if (str_ends_with(strtolower($certFilename), '.p12')) {
                $candidates[] = substr($certFilename, 0, -4) . '.pfx';
            } elseif (str_ends_with(strtolower($certFilename), '.pfx')) {
                $candidates[] = substr($certFilename, 0, -4) . '.p12';
            }

            // Remove prefixos comuns
            if (str_starts_with($certFilename, 'convertido_')) {
                $candidates[] = substr($certFilename, strlen('convertido_'));
            }
            if (str_contains($certFilename, 'convertido_')) {
                $candidates[] = str_replace('convertido_', '', $certFilename);
            }

            // Se o arquivo não convertido estiver com outro prefixo, tenta também
            if (str_starts_with($certFilename, 'cert_')) {
                $candidates[] = substr($certFilename, strlen('cert_'));
            }
            if (str_contains($certFilename, 'cert_')) {
                $candidates[] = str_replace('cert_', '', $certFilename);
            }

            // Dedup + validação
            $candidates = array_values(array_unique(array_filter($candidates)));

            // Normalização por nome: trim + colapsa espaços + ignora caixa (maiúsc/minúsc)
            $normalizeName = function (string $s): string {
                $s = trim($s);
                $s = trim($s, "\"'");
                $s = preg_replace('/\s+/', ' ', $s);
                return strtolower($s);
            };
            $certFilenameNorm = $normalizeName($certFilename);

            $foundPath = null;
            $tried = [];
            foreach ($certDirs as $dirTry) {
                if (!is_dir($dirTry)) continue;
                foreach ($candidates as $name) {
                    $p = $dirTry . $name;
                    $tried[] = $p;
                    if (file_exists($p)) {
                        $foundPath = $p;
                        break 2;
                    }
                }
            }

            // Tolerância extra: procura por nome normalizado dentro dos diretórios permitidos.
            if (!$foundPath) {
                foreach ($certDirs as $dirTry) {
                    if (!is_dir($dirTry)) continue;
                    $all = @scandir($dirTry);
                    if (!is_array($all)) continue;

                    foreach ($all as $entry) {
                        if ($entry === '.' || $entry === '..') continue;
                        if (!is_string($entry)) continue;
                        $full = $dirTry . $entry;
                        if (!is_file($full)) continue;

                        $entryNorm = $normalizeName($entry);
                        if ($entryNorm === $certFilenameNorm) {
                            $tried[] = $full;
                            $foundPath = $full;
                            break 2;
                        }
                    }
                }
            }

            // Fallback adicional: procurar por "hint" numérico no nome do arquivo.
            // Isso cobre cenarios onde o banco aponta para um prefixo diferente (convertido_/cert_/cert_final_/etc),
            // mas o sufixo timestamp permanece igual.
            if (!$foundPath) {
                $timestampHint = null;
                if (preg_match('/_(\d+)\.(p12|pfx)$/i', $certFilename, $m)) {
                    $timestampHint = $m[1];
                } else {
                    // tente extrair qualquer sequência grande de dígitos antes do .p12
                    if (preg_match('/(\d+)\.(p12|pfx)$/i', $certFilename, $m2)) {
                        $timestampHint = $m2[1];
                    }
                }

                if ($timestampHint) {
                    foreach ($certDirs as $dirTry) {
                        if (!is_dir($dirTry)) continue;

                        $all = @scandir($dirTry);
                        if (!is_array($all)) continue;

                        foreach ($all as $entry) {
                            if ($entry === '.' || $entry === '..') continue;
                            if (!is_string($entry)) continue;

                            $full = $dirTry . $entry;
                            if (!is_file($full)) continue;

                            // procura por extensão e presença do hint
                            $extOk = str_ends_with(strtolower($entry), '.p12') || str_ends_with(strtolower($entry), '.pfx');
                            if ($extOk && str_contains($entry, $timestampHint)) {
                                $tried[] = $full;
                                $foundPath = $full;
                                break 2;
                            }
                        }
                    }
                }
            }

            // Último fallback: se existem certificados no diretório, usa o mais recente (mtime).
            if (!$foundPath) {
                foreach ($certDirs as $dirTry) {
                    if (!is_dir($dirTry)) continue;
                    $all = @scandir($dirTry);
                    if (!is_array($all)) continue;
                    $pks = [];
                    foreach ($all as $entry) {
                        if ($entry === '.' || $entry === '..') continue;
                        if (!is_string($entry)) continue;
                        $full = $dirTry . $entry;
                        if (!is_file($full)) continue;
                        $lower = strtolower($entry);
                        if (str_ends_with($lower, '.p12') || str_ends_with($lower, '.pfx')) {
                            $pks[] = $full;
                        }
                    }
                    if (count($pks) >= 1) {
                        usort($pks, static function ($a, $b) {
                            return @filemtime($b) <=> @filemtime($a);
                        });
                        $tried[] = implode(', ', $pks);
                        $foundPath = $pks[0];
                        break;
                    }
                }
            }

            if (!$foundPath) {
                throw new Exception(
                    "Arquivo do certificado digital não encontrado. Tentativas:\n" . implode("\n", $tried)
                );
            }

            $certPath = $foundPath;
        }
        
        $certContent = file_get_contents($certPath);

        // Alguns certificados antigos falham se a senha vier com espaços invisíveis.
        // Tentamos a senha original e variações seguras (sem alterar o valor salvo no banco).
        $passwordCandidates = array_values(array_unique(array_filter([
            $certPassword,
            trim($certPassword),
        ], static fn($v) => $v !== '')));

        if (count($passwordCandidates) === 0) {
            $passwordCandidates = [''];
        }

        $certificate = null;
        $lastError = null;

        // 1) Tentativa normal
        foreach ($passwordCandidates as $pwdTry) {
            try {
                $certificate = Certificate::readPfx($certContent, $pwdTry);
                break;
            } catch (Exception $e) {
                $lastError = $e;
            }
        }

        // 2) Tentativa com OpenSSL legacy
        if (!$certificate) {
            $originalEnv = getenv('OPENSSL_CONF');
            $originalModulesEnv = getenv('OPENSSL_MODULES');
            $legacyConf = realpath(__DIR__ . "/../../legacy_openssl.cnf");
            if (!$legacyConf) {
                $legacyConf = __DIR__ . "/../../legacy_openssl.cnf";
            }

            // Em hospedagens compartilhadas, o OpenSSL 3 pode exigir OPENSSL_MODULES apontando
            // para a pasta de providers (legacy/default). Tentamos caminhos comuns.
            $moduleCandidates = array_values(array_unique(array_filter([
                $originalModulesEnv !== false ? (string)$originalModulesEnv : '',
                '/opt/alt/php83/usr/lib64/ossl-modules',
                '/opt/alt/php82/usr/lib64/ossl-modules',
                '/opt/alt/php81/usr/lib64/ossl-modules',
                '/usr/lib64/ossl-modules',
                '/usr/lib/ossl-modules',
            ], static fn($v) => $v !== '')));

            try {
                putenv("OPENSSL_CONF={$legacyConf}");

                foreach ($moduleCandidates as $modulesPath) {
                    putenv("OPENSSL_MODULES={$modulesPath}");
                    foreach ($passwordCandidates as $pwdTry) {
                        try {
                            $certificate = Certificate::readPfx($certContent, $pwdTry);
                            break 2;
                        } catch (Exception $e) {
                            $lastError = $e;
                        }
                    }
                }
            } finally {
                if ($originalEnv !== false) {
                    putenv("OPENSSL_CONF=$originalEnv");
                } else {
                    putenv("OPENSSL_CONF");
                }

                if ($originalModulesEnv !== false) {
                    putenv("OPENSSL_MODULES=$originalModulesEnv");
                } else {
                    putenv("OPENSSL_MODULES");
                }
            }
        }

        if (!$certificate) {
            $detail = $lastError ? $lastError->getMessage() : 'falha desconhecida';
            throw new Exception(
                "Erro ao ler certificado ($certFilename): senha inválida, arquivo inválido ou criptografia legada incompatível. Detalhe técnico: $detail"
            );
        }
        
        $this->tools = new Tools(json_encode($configJson), $certificate);
        $this->tools->model('65'); // 65 = NFC-e
    }
    
    /**
     * Emite uma NFC-e para uma venda
     */
    public function emitir(int $vendaId)
    {
        $this->initTools();
        
        // 1. Buscar Venda e Cliente
        $venda = $this->getVenda($vendaId);
        if (!$venda) throw new Exception("Venda #$vendaId não encontrada.");
        
        // VERIFICAÇÃO CRÍTICA: Se a venda já tem NFC-e autorizada, não emitir novamente
        if (!empty($venda['nfce_chave']) && $venda['nfce_status'] === 'AUTORIZADA') {
            return [
                'success' => false,
                'message' => "Esta venda já possui NFC-e autorizada (Chave: {$venda['nfce_chave']}). Para emitir uma nova nota, cancele a anterior primeiro."
            ];
        }
        
        $itens = $this->getItensVenda($vendaId);
        if (empty($itens)) throw new Exception("Venda sem itens.");

        // 2. Instanciar Make (Montador do XML)
        $nfe = new Make();

        // 3. Identificação da Nota (infNFe)
        $std = new stdClass();
        $std->versao = '4.00';
        $nfe->taginfNFe($std);

        // 3.1 Identificação (ide)
        $std = new stdClass();
        $std->cUF = '52'; // Código IBGE GO (fixo por enquanto, ideal ser dinâmico)
        $std->cNF = rand(10000000, 99999999);
        $std->natOp = 'VENDA';
        $std->mod = 65; // NFC-e
        $std->serie = $this->configFiscal['nfce_serie'] ?? 1;
        $std->nNF = $this->configFiscal['nfce_numero'] ?? 1;
        // ESTRATÉGIA DE OURO: Sincronizar com o relógio da SEFAZ
        // NOTA: A SEFAZ de Homologação GO está em 2026 (erro deles ou intencional), então não devemos alterar o ano.
        try {
            $respStatus = $this->tools->sefazStatus();
            $st = new Standardize();
            $stdStatus = $st->toStd($respStatus);
            if (!empty($stdStatus->dhRecbto)) {
                // Tira 3 HORAS (10800s) - Provavelmente o servidor está em UTC puro!
                $sefazTime = strtotime($stdStatus->dhRecbto) - 10800; 
                $std->dhEmi = date("Y-m-d\TH:i:sP", $sefazTime);
            } else {
                throw new Exception("Falha data");
            }
        } catch (Exception $e) {
            // Fallback: Usa hora local mas força timezone correto
            date_default_timezone_set('America/Sao_Paulo');
            $std->dhEmi = date("Y-m-d\TH:i:sP", time() - 5);
        } 
        
        $std->tpNF = 1; // Saída
        $std->idDest = 1; // 1=Operação interna
        $std->cMunFG = '5208707'; // Código IBGE Município Goiânia (exemplo)
        $std->tpImp = 4; // DANFE NFC-e
        $std->tpEmis = 1; // Normal
        $std->tpAmb = 2; // Homologação
        $std->finNFe = 1; // Normal
        $std->indFinal = 1; // Consumidor Final
        $std->indPres = 1; // Presencial
        $std->procEmi = 0;
        $std->verProc = 'ZiipVet 4.0';
        $nfe->tagide($std);

        // 3.2 Emitente (emit)
        $std = new stdClass();
        $std->xNome = $this->configEmpresa['razao_social'];
        $std->xFant = $this->configEmpresa['nome_fantasia'];
        $std->IE = preg_replace('/[^0-9]/', '', $this->configEmpresa['inscricao_estadual']);
        $std->CRT = 1; // Forçado Simples Nacional para evitar erro 591
        $std->CNPJ = preg_replace('/[^0-9]/', '', $this->configEmpresa['cnpj']);
        $nfe->tagemit($std);

        // Endereço Emitente
        $std = new stdClass();
        $std->xLgr = $this->configEmpresa['logradouro'];
        $std->nro = $this->configEmpresa['numero'];
        $std->xBairro = $this->configEmpresa['bairro'];
        $std->cMun = '5208707'; // IBGE Goiânia
        $std->xMun = $this->configEmpresa['municipio'];
        $std->UF = 'GO';
        $std->CEP = preg_replace('/[^0-9]/', '', $this->configEmpresa['cep']);
        $std->cPais = '1058';
        $std->xPais = 'BRASIL';
        $nfe->tagenderEmit($std);

        // 3.3 Destinatário (dest) - Opcional na NFC-e (se valor < 10k)
        if (!empty($venda['cliente_cpf'])) {
            $std = new stdClass();
            $std->xNome = $venda['cliente_nome']; // Opcional se tiver CPF
            $std->CPF = preg_replace('/[^0-9]/', '', $venda['cliente_cpf']);
            $std->indIEDest = 9; // Não Contribuinte
            $nfe->tagdest($std);
        }

        // 3.4 Itens (det)
        $i = 0;
        foreach ($itens as $item) {
            $i++; // Incremento importante!
            $std = new stdClass();
            $std->item = $i; // O nome correto da propriedade para nItem no NFePHP é item
            $std->cProd = $item['id_produto'];
            $std->cEAN = "SEM GTIN";
            $std->xProd = $item['nome_produto'];
            // Garantir 8 dígitos. Se vazio, usar NCM genérico válido ou tentar corrigir
            $ncmRaw = preg_replace('/[^0-9]/', '', $item['ncm'] ?? '');
            $std->NCM = (strlen($ncmRaw) === 8) ? $ncmRaw : '00000000'; // 00000000 é aceito em homologação para testes
            
            $std->CFOP = '5102'; // Venda mercadoria
            $std->uCom = !empty($item['unidade']) ? $item['unidade'] : 'UN'; // Validar null
            $std->qCom = number_format($item['quantidade'], 4, '.', '');
            $std->vUnCom = number_format($item['valor_unitario'], 10, '.', '');
            $std->vProd = number_format($item['valor_total'], 2, '.', '');
            $std->cEANTrib = "SEM GTIN";
            $std->uTrib = !empty($item['unidade']) ? $item['unidade'] : 'UN';
            $std->qTrib = number_format($item['quantidade'], 4, '.', '');
            $std->vUnTrib = number_format($item['valor_unitario'], 10, '.', '');
            $std->indTot = 1;
            $nfe->tagprod($std);

            // ... (impostos, mantendo como estava) ...
            
            // Impostos (imposto) 
            $std = new stdClass();
            $std->item = $i; // Vínculo explícito
            $std->vTotTrib = number_format(0.00, 2, '.', ''); // Formatação string
            $nfe->tagimposto($std);

            // ICMS Simples Nacional
            $std = new stdClass();
            $std->item = $i;
            $std->orig = 0;
            $std->CSOSN = '102'; 
            $nfe->tagICMSSN($std); 
            
            // PIS
            $std = new stdClass();
            $std->item = $i;
            $std->CST = '07';
            $nfe->tagPIS($std);

            // COFINS
            $std = new stdClass();
            $std->item = $i;
            $std->CST = '07';
            $nfe->tagCOFINS($std);
        }

        // 3.5 Transporte (OBRIGATÓRIO TER A TAG, mesmo sem frete)
        $std = new stdClass();
        $std->modFrete = 9; // 9 = Sem Ocorrência de Transporte
        $nfe->tagtransp($std);

        // 3.6 Totais (total)
        $std = new stdClass();
        $std->vBC = 0.00;
        $std->vICMS = 0.00;
        $std->vICMSDeson = 0.00;
        $std->vFCP = 0.00;
        $std->vBCST = 0.00;
        $std->vST = 0.00;
        $std->vFCPST = 0.00;
        $std->vFCPSTRet = 0.00;
        $std->vProd = number_format($venda['valor_total'], 2, '.', '');
        $std->vFrete = 0.00;
        $std->vSeg = 0.00;
        $std->vDesc = 0.00;
        $std->vII = 0.00;
        $std->vIPI = 0.00;
        $std->vIPIDevol = 0.00;
        $std->vPIS = 0.00;
        $std->vCOFINS = 0.00;
        $std->vOutro = 0.00;
        $std->vNF = number_format($venda['valor_total'], 2, '.', '');
        $std->vTotTrib = 0.00;
        $nfe->tagICMSTot($std);

        // 3.7 Pagamento (pag) - Wrapper OBRIGATÓRIO
        $std = new stdClass();
        $std->vTroco = null; 
        $nfe->tagpag($std);

        // Detalhe Pagamento
        $std = new stdClass();
        $std->tPag = '01'; // 01=Dinheiro
        $std->vPag = number_format($venda['valor_total'], 2, '.', '');
        $nfe->tagdetPag($std);

        // 4. Montar e Assinar
        $xml = $nfe->getXML();
        $xml = $this->tools->signNFe($xml);

        // 5. Enviar para SEFAZ (SÍNCRONO)
        $idLote = str_pad(100, 15, '0', STR_PAD_LEFT);
        // O terceiro parâmetro '1' indica processamento SÍNCRONO
        $resp = $this->tools->sefazEnviaLote([$xml], $idLote, 1);

        // 6. Processar Retorno
        $st = new Standardize();
        $std = $st->toStd($resp);

        if ($std->cStat != 103 && $std->cStat != 104) {
             // Tratamento para modo síncrono que pode retornar direto no protNFe
             if (isset($std->protNFe)) {
                 $prot = $std->protNFe->infProt;
                 if ($prot->cStat == 100) {
                     $this->incrementarNumeracao();
                     return [
                         'success' => true,
                         'chave' => $prot->chNFe,
                         'protocolo' => $prot->nProt,
                         'xml' => $xml
                     ];
                 } else {
                     throw new Exception("Rejeição: [{$prot->cStat}] {$prot->xMotivo}");
                 }
             }
             throw new Exception("Erro no envio: [{$std->cStat}] {$std->xMotivo}");
        }

        // Obter Recibo e Consultar (se for assíncrono, mas NFC-e costuma ser síncrono no lote)
        if (isset($std->protNFe)) {
            $prot = $std->protNFe->infProt;
            if ($prot->cStat == 100) {
                // SUCESSO!
                // Incrementar numeração
                $this->incrementarNumeracao();
                return [
                    'success' => true,
                    'chave' => $prot->chNFe,
                    'protocolo' => $prot->nProt,
                    'xml' => $xml
                ];
            } else {
                throw new Exception("Rejeição: [{$prot->cStat}] {$prot->xMotivo}");
            }
        }
        
        return [
            'success' => false,
            'message' => 'Retorno desconhecido da SEFAZ',
            'debug' => $std
        ];
    }
    
    private function getVenda(int $vendaId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT v.*, c.nome as cliente_nome, c.cpf_cnpj as cliente_cpf
            FROM vendas v
            LEFT JOIN clientes c ON v.id_cliente = c.id
            WHERE v.id = ?
        ");
        $stmt->execute([$vendaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getItensVenda(int $vendaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, p.nome as nome_produto, p.ncm, p.unidade 
            FROM vendas_itens i
            JOIN produtos p ON i.id_produto = p.id
            WHERE i.id_venda = ?
        ");
        $stmt->execute([$vendaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function incrementarNumeracao()
    {
        $sql = "UPDATE configuracoes_fiscais SET nfce_numero = nfce_numero + 1 WHERE id_admin = 1";
        $this->pdo->exec($sql);
    }
    
    public function verificarConfiguracoes(): array
    {
        $this->loadConfig();
        $erros = [];
        
        if (empty($this->configEmpresa['cnpj'])) $erros[] = "CNPJ empresa não configurado";
        if (empty($this->configFiscal['csc_id'])) $erros[] = "CSC ID não configurado";
        if (empty($this->configFiscal['certificado_arquivo'])) $erros[] = "Certificado digital não enviado";
        
        // Verificar se arquivo do certificado existe
        if (!empty($this->configFiscal['certificado_arquivo'])) {
            $certPath = __DIR__ . '/../../uploads/certificados/' . $this->configFiscal['certificado_arquivo'];
            if (!file_exists($certPath)) {
                $erros[] = "Arquivo de certificado sumiu do servidor";
            }
        }

        return [
            'configurado' => count($erros) === 0,
            'erros' => $erros
        ];
    }
}
