<?php
// ==========================================================
// IMPORTAR DADOS - VERSÃO V13 (SOLUÇÃO DEFINITIVA DUPLICIDADE)
// ==========================================================
ob_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/erro_importacao.log');
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 0);
ini_set('auto_detect_line_endings', true);

require_once 'auth.php';
require_once 'config/configuracoes.php';

$id_admin = $_SESSION['id_admin'] ?? 1;

// Verificação de segurança: Apenas administradores podem realizar importações manuais
if (!temPermissao('usuarios', 'listar')) {
    responderJSON(['status' => 'error', 'message' => 'Acesso negado: Apenas administradores podem realizar esta operação.']);
}

$pasta_temp = __DIR__ . '/temp_uploads';
if (!is_dir($pasta_temp)) {
    @mkdir($pasta_temp, 0755, true);
}
$arquivo_temporario = $pasta_temp . '/import_' . $id_admin . '.csv';

// Funções Auxiliares
function str_utf8($str) {
    if (!$str) return '';
    $str = trim($str); // Remove espaços extras
    if (mb_detect_encoding($str, 'UTF-8, ISO-8859-1, Windows-1252', true) !== 'UTF-8') {
        return mb_convert_encoding($str, 'UTF-8', 'Windows-1252'); 
    }
    return $str;
}

function responderJSON($array) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($array);
    exit;
}

function converterData($dataStr) {
    if (empty($dataStr) || $dataStr == '00/00/0000') return null;
    $partes = explode('/', str_replace(['-', '.'], '/', $dataStr));
    if (count($partes) == 3) {
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return null;
}

// Processamento AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    
    try {
        if ($_POST['acao'] === 'upload_chunk') {
            $modo = ($_POST['is_first'] === 'true') ? 'wb' : 'ab';
            $handle = fopen($arquivo_temporario, $modo);
            fwrite($handle, file_get_contents($_FILES['file_data']['tmp_name']));
            fclose($handle);
            responderJSON(array('status' => 'success'));
        }

        if ($_POST['acao'] === 'ler_cabecalho') {
            $handle = fopen($arquivo_temporario, "r");
            $primeiraLinha = fgets($handle);
            $delimitador = (substr_count($primeiraLinha, ';') >= substr_count($primeiraLinha, ',')) ? ';' : ',';
            rewind($handle);
            $header = fgetcsv($handle, 0, $delimitador, '"');
            fclose($handle);

            $header = array_map(function($col) {
                return str_utf8(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', trim($col)));
            }, $header);

            responderJSON(array('status' => 'success', 'headers' => $header, 'delimitador' => $delimitador));
        }

        if ($_POST['acao'] === 'importar_final') {
            $tipo = $_POST['tipo_importacao'];
            $map = json_decode($_POST['mapeamento'], true);
            $delimitador = $_POST['delimitador'];

            $handle = fopen($arquivo_temporario, "r");
            fgetcsv($handle, 0, $delimitador, '"'); // Pula cabeçalho

            $pdo->beginTransaction();
            
            $countCli = 0; $countPet = 0;
            
            // Cache para esta sessão de importação
            $cacheClientesMap = array(); 

            // Prepara as Queries uma única vez (mais rápido e seguro)
            $stmtInsertCli = $pdo->prepare("INSERT INTO clientes (id_admin, nome, cpf_cnpj, email, telefone, endereco, bairro, cidade, estado, cep, data_cadastro, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'ATIVO')");
            $stmtInsertPet = $pdo->prepare("INSERT INTO pacientes (id_admin, id_cliente, nome_paciente, especie, raca, sexo, pelagem, data_nascimento, data_cadastro, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'ATIVO')");
            $stmtCheckPet = $pdo->prepare("SELECT id FROM pacientes WHERE id_cliente = ? AND nome_paciente = ? LIMIT 1");
            $stmtBuscaCli = $pdo->prepare("SELECT id FROM clientes WHERE id_admin = ? AND (nome = ? OR (cpf_cnpj = ? AND cpf_cnpj != '')) LIMIT 1");

            while (($row = fgetcsv($handle, 0, $delimitador, '"')) !== FALSE) {
                $val = function($key) use ($map, $row) {
                    $idx = isset($map[$key]) ? $map[$key] : '';
                    if ($idx === '' || !isset($row[$idx])) return '';
                    return str_utf8($row[$idx]);
                };

                if ($tipo === 'clientes_animais') {
                    $nomeCli = strtoupper($val('nome_cliente'));
                    if (empty($nomeCli)) continue;

                    $cpfCli = preg_replace('/\D/', '', $val('cpf_cnpj'));
                    $codCliExt = $val('codigo_cliente');
                    
                    // Chave única para o cache (prioriza código, depois CPF, depois Nome)
                    $chaveUnica = !empty($codCliExt) ? 'ID_'.$codCliExt : (!empty($cpfCli) ? 'CPF_'.$cpfCli : 'NOM_'.$nomeCli);

                    if (isset($cacheClientesMap[$chaveUnica])) {
                        $idCliente = $cacheClientesMap[$chaveUnica];
                    } else {
                        // Verifica se o cliente já existe no Banco
                        $stmtBuscaCli->execute(array($id_admin, $nomeCli, $cpfCli));
                        $resBusca = $stmtBuscaCli->fetch(PDO::FETCH_ASSOC);

                        if ($resBusca) {
                            $idCliente = $resBusca['id'];
                        } else {
                            // Insere novo
                            $stmtInsertCli->execute(array(
                                $id_admin, $nomeCli, $cpfCli, $val('email'),
                                $val('telefone'), $val('endereco'), $val('bairro'),
                                $val('cidade'), $val('estado'), preg_replace('/\D/', '', $val('cep'))
                            ));
                            $idCliente = $pdo->lastInsertId();
                            $countCli++;
                        }
                        $cacheClientesMap[$chaveUnica] = $idCliente;
                    }

                    // --- CADASTRO DO ANIMAL COM TRAVA DE REPETIÇÃO ---
                    $nomePet = $val('nome_paciente');
                    if (!empty($nomePet) && $idCliente) {
                        // Só insere se esse cliente não tiver esse animal cadastrado ainda
                        $stmtCheckPet->execute(array($idCliente, $nomePet));
                        if (!$stmtCheckPet->fetch()) {
                            $stmtInsertPet->execute(array(
                                $id_admin, $idCliente, $nomePet,
                                $val('especie') ?: 'Canina',
                                $val('raca') ?: 'SRD',
                                $val('sexo'), $val('pelagem'), 
                                converterData($val('data_nascimento'))
                            ));
                            $countPet++;
                        }
                    }
                }
            }

            $pdo->commit();
            fclose($handle);
            @unlink($arquivo_temporario);

            responderJSON(array('status' => 'success', 'message' => "Processado com sucesso: $countCli novos clientes e $countPet animais vinculados."));
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        responderJSON(array('status' => 'error', 'message' => $e->getMessage()));
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Dados | ZiipVet</title>
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root { --fundo: #ecf0f5; --texto-dark: #333; --primaria: #337ab7; --sucesso: #00a65a; --sidebar-collapsed: 75px; --sidebar-expanded: 260px; --header-height: 80px; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Source Sans Pro', sans-serif; background-color: var(--fundo); color: var(--texto-dark); min-height: 100vh; font-size: 16px; }
        aside.sidebar-container { position: fixed; left: 0; top: 0; height: 100vh; width: var(--sidebar-collapsed); z-index: 1000; transition: width 0.4s; }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }
        header.top-header { position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; height: var(--header-height); z-index: 900; transition: left 0.4s; }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }
        main.main-content { margin-left: var(--sidebar-collapsed); padding: calc(var(--header-height) + 30px) 25px 30px; transition: margin-left 0.4s; }
        aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); }
        .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }
        .page-title { font-size: 28px; font-weight: 600; color: #444; margin-bottom: 25px; }
        .card-import { background: #fff; border-top: 3px solid var(--primaria); border-radius: 3px; box-shadow: 0 1px 1px rgba(0,0,0,0.1); padding: 30px; }
        .step-nav { display: flex; justify-content: space-around; margin-bottom: 40px; border-bottom: 1px solid #ddd; padding-bottom: 15px; }
        .step-item { color: #ccc; font-weight: 600; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .step-item.active { color: var(--primaria); }
        .step-item.done { color: var(--sucesso); }
        .step-num { width: 30px; height: 30px; border-radius: 50%; border: 2px solid currentColor; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        label { font-weight: 700; color: #444; display: block; margin-bottom: 8px; font-size: 15px; }
        .form-control { width: 100%; height: 48px; padding: 10px 15px; font-size: 17px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 20px; }
        .btn-large { padding: 12px 30px; font-size: 17px; font-weight: 600; border-radius: 4px; cursor: pointer; border: none; color: #fff; }
        .btn-prim { background: var(--primaria); }
        .btn-suc { background: var(--sucesso); }
        .progress-box { height: 12px; background: #eee; border-radius: 6px; overflow: hidden; margin: 20px 0; display: none; }
        .progress-bar { width: 0%; height: 100%; background: var(--sucesso); transition: 0.3s; }
        .table-map { width: 100%; border-collapse: collapse; }
        .table-map th { text-align: left; background: #f9fafc; padding: 12px; border-bottom: 2px solid #eee; }
        .table-map td { padding: 12px; border-bottom: 1px solid #f4f4f4; }
        .step-content { display: none; }
        .step-content.active { display: block; animation: fadeIn 0.4s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        <h2 class="page-title">Importação de Dados</h2>

        <div class="card-import">
            <div class="step-nav">
                <div class="step-item active" id="st-1"><span class="step-num">1</span> Seleção e Upload</div>
                <div class="step-item" id="st-2"><span class="step-num">2</span> Mapeamento de Colunas</div>
                <div class="step-item" id="st-3"><span class="step-num">3</span> Conclusão</div>
            </div>

            <div id="step1" class="step-content active">
                <div style="max-width: 600px; margin: auto;">
                    <label>Tipo de Importação</label>
                    <select id="tipo_importacao" class="form-control">
                        <option value="clientes_animais">Clientes e Animais (Arquivo Animais_e_Clientes.csv)</option>
                        <option value="produtos">Produtos e Serviços</option>
                    </select>

                    <label>Arquivo CSV</label>
                    <input type="file" id="arquivo_csv" accept=".csv" class="form-control" style="padding-top: 8px;">
                    
                    <div class="progress-box" id="prog_box"><div class="progress-bar" id="prog_bar"></div></div>

                    <div style="text-align: right; margin-top: 20px;">
                        <button onclick="iniciarUpload()" class="btn-large btn-prim">PRÓXIMO PASSO <i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>

            <div id="step2" class="step-content">
                <div style="background: #fcf8e3; color: #8a6d3b; padding: 15px; border-radius: 4px; margin-bottom: 25px; border: 1px solid #faebcc;">
                    <i class="fas fa-info-circle"></i> Ligue os campos do ZiipVet às colunas do seu arquivo CSV.
                </div>
                <form id="formMap">
                    <table class="table-map">
                        <thead><tr><th width="40%">Campo no ZiipVet</th><th>Sua Coluna no CSV</th></tr></thead>
                        <tbody id="tbodyMap"></tbody>
                    </table>
                </form>
                <div style="text-align: right; margin-top: 30px; display: flex; justify-content: flex-end; gap: 15px;">
                    <button onclick="location.reload()" class="btn-large" style="background:#aaa;">CANCELAR</button>
                    <button onclick="finalizarImportacao()" id="btnFin" class="btn-large btn-suc">IMPORTAR AGORA <i class="fas fa-check"></i></button>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let arquivoCSV = null;
        let delimitador = ';';
        let colunasCSV = [];
        const CHUNK_SIZE = 1024 * 1024;

        const configCampos = {
            'clientes_animais': [
                { id: 'codigo_cliente', label: 'Código do Cliente (ID Externo)', keys: ['cliente - código', 'código', 'ficha'] },
                { id: 'nome_cliente', label: 'Nome do Cliente', keys: ['cliente - nome', 'nome'] },
                { id: 'cpf_cnpj', label: 'CPF / CNPJ', keys: ['cpf', 'cnpj'] },
                { id: 'telefone', label: 'Telefone / Celular', keys: ['telefones', 'celular'] },
                { id: 'email', label: 'E-mail', keys: ['email'] },
                { id: 'cep', label: 'CEP', keys: ['cep'] },
                { id: 'endereco', label: 'Endereço', keys: ['endereço'] },
                { id: 'bairro', label: 'Bairro', keys: ['bairro'] },
                { id: 'cidade', label: 'Cidade', keys: ['cidade'] },
                { id: 'estado', label: 'UF / Estado', keys: ['uf', 'estado'] },
                { id: 'nome_paciente', label: 'Nome do Animal', keys: ['animal - nome', 'nome animal'] },
                { id: 'especie', label: 'Espécie', keys: ['espécie', 'especie'] },
                { id: 'raca', label: 'Raça', keys: ['raça', 'raca'] },
                { id: 'sexo', label: 'Sexo', keys: ['sexo'] },
                { id: 'pelagem', label: 'Pelagem / Cor', keys: ['pelagem', 'cor'] },
                { id: 'data_nascimento', label: 'Data de Nascimento', keys: ['nascimento'] }
            ]
        };

        async function iniciarUpload() {
            const fileInput = document.getElementById('arquivo_csv');
            if(!fileInput.files[0]) return Swal.fire('Atenção', 'Selecione um arquivo.', 'warning');
            arquivoCSV = fileInput.files[0];
            $('#prog_box').show();
            const totalChunks = Math.ceil(arquivoCSV.size / CHUNK_SIZE);
            for (let i = 0; i < totalChunks; i++) {
                const chunk = arquivoCSV.slice(i * CHUNK_SIZE, (i + 1) * CHUNK_SIZE);
                const fd = new FormData();
                fd.append('acao', 'upload_chunk');
                fd.append('file_data', chunk);
                fd.append('is_first', i === 0);
                await $.ajax({ url: 'importar_dados.php', type: 'POST', data: fd, contentType: false, processData: false });
                $('#prog_bar').css('width', Math.round(((i+1)/totalChunks)*100) + '%');
            }
            $.post('importar_dados.php', { acao: 'ler_cabecalho' }, function(res) {
                if(res.status === 'success') {
                    colunasCSV = res.headers;
                    delimitador = res.delimitador;
                    montarTabelaMapeamento();
                }
            }, 'json');
        }

        function montarTabelaMapeamento() {
            const tipo = $('#tipo_importacao').val();
            const lista = configCampos[tipo] || [];
            let html = '';
            lista.forEach(c => {
                let options = '<option value="">-- Ignorar --</option>';
                let selectedIdx = '';
                colunasCSV.forEach((col, idx) => {
                    let colL = col.toLowerCase();
                    if(c.keys.some(k => colL.includes(k))) selectedIdx = idx;
                    options += `<option value="${idx}" ${selectedIdx === idx ? 'selected' : ''}>${col}</option>`;
                });
                html += `<tr><td><label>${c.label}</label></td><td><select name="${c.id}" class="form-control">${options}</select></td></tr>`;
            });
            $('#tbodyMap').html(html);
            $('#step1').removeClass('active'); $('#step2').addClass('active');
            $('#st-1').addClass('done'); $('#st-2').addClass('active');
        }

        function finalizarImportacao() {
            const btn = document.getElementById('btnFin');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> PROCESSANDO...';
            const map = {};
            $('#tbodyMap select').each(function() { if($(this).val() !== "") map[$(this).attr('name')] = $(this).val(); });
            $.post('importar_dados.php', { acao: 'importar_final', tipo_importacao: $('#tipo_importacao').val(), mapeamento: JSON.stringify(map), delimitador: delimitador }, function(res) {
                if(res.status === 'success') {
                    Swal.fire('Sucesso!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Erro', res.message, 'error');
                    btn.disabled = false; btn.innerHTML = 'IMPORTAR AGORA <i class="fas fa-check"></i>';
                }
            }, 'json').fail(function(){ Swal.fire('Erro', 'O servidor falhou.', 'error'); btn.disabled = false; });
        }
    </script>
</body>
</html>