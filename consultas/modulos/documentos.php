<?php
/**
 * MÓDULO DE EMISSÃO DE DOCUMENTOS
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

setlocale(LC_TIME, 'pt_BR.utf8', 'pt_BR', 'portuguese');

$usuario_logado = $_SESSION['usuario_nome'] ?? 'Veterinário';
$cidade_unidade = "Goiânia";

$meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
          'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$data_hoje_extenso = date('d') . " de " . $meses[date('n')-1] . " de " . date('Y');

// ============================================================================
// BUSCAR DADOS DO CLIENTE (RESPONSÁVEL) - IMPORTANTE!
// ============================================================================
$dados_cliente = [];
if (isset($dados_paciente['id_cliente']) && !empty($dados_paciente['id_cliente'])) {
    $stmt_cliente = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt_cliente->execute([$dados_paciente['id_cliente']]);
    $dados_cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC) ?: [];
}

// ============================================================================
// DADOS DO PACIENTE
// ============================================================================
$nome_animal = $dados_paciente['nome_paciente'] ?? $dados_paciente['nome'] ?? '';
$especie = $dados_paciente['especie'] ?? '';
$raca = $dados_paciente['raca'] ?? '';
$sexo = $dados_paciente['sexo'] ?? '';
$nascimento = !empty($dados_paciente['data_nascimento']) ? date('d/m/Y', strtotime($dados_paciente['data_nascimento'])) : '';
$pelagem = $dados_paciente['pelagem'] ?? '';
$peso = $dados_paciente['peso'] ?? '';
$chip = $dados_paciente['chip'] ?? 'Não informado';

$idade_texto = '';
if (!empty($dados_paciente['data_nascimento'])) {
    $nasc = new DateTime($dados_paciente['data_nascimento']);
    $hoje = new DateTime();
    $diff = $hoje->diff($nasc);
    $idade_texto = $diff->y . ' anos';
    if ($diff->m > 0) $idade_texto .= ', ' . $diff->m . ' meses';
    if ($diff->d > 0 && $diff->y == 0 && $diff->m == 0) $idade_texto = $diff->d . ' dias';
}

// ============================================================================
// DADOS DO CLIENTE (RESPONSÁVEL)
// ============================================================================
$nome_tutor = $dados_cliente['nome'] ?? '';
$cpf_tutor = $dados_cliente['cpf_cnpj'] ?? '';
$rg_tutor = $dados_cliente['rg'] ?? '';

$endereco_parts = array_filter([
    $dados_cliente['endereco'] ?? '',
    ($dados_cliente['numero'] ?? '') ? 'nº ' . $dados_cliente['numero'] : '',
    $dados_cliente['complemento'] ?? '',
    $dados_cliente['bairro'] ?? ''
]);
$endereco = implode(', ', $endereco_parts);

$cidade_tutor = $dados_cliente['cidade'] ?? '';
$uf_tutor = $dados_cliente['estado'] ?? '';
$cep = $dados_cliente['cep'] ?? '';
$fone_tutor = $dados_cliente['telefone'] ?? '';
$email_tutor = $dados_cliente['email'] ?? '';
$id_cliente = $dados_cliente['id'] ?? 0;
?>

<div class="secao-titulo"><i class="fas fa-file-alt"></i> Emissão de Documentos</div>

<div class="form-group">
    <label>Modelo do Documento</label>
    <select id="modelo_documento" onchange="carregarModeloDocumento(this.value)" style="max-width: 600px;">
        <option value="">Escolha um modelo...</option>
        <option value="atestado_vacina">Atestado de aplicação de vacina</option>
        <option value="atestado_obito">Atestado de óbito</option>
        <option value="atestado_saude">Atestado de saúde</option>
        <option value="guia_transito">Guia de Trânsito</option>
        <option value="receita_especial">Receituário de Controle Especial</option>
        <option value="solicitacao_exame">Solicitação de Exame</option>
        <option value="termo_autorizacao_exame">Termo de Autorização para Exame</option> 
        <option value="atestado_vacina_historico">Atestado de Histórico de Vacinas</option>   
        <option value="termo_procedimento_cirurgico">Termo de Autorização para Procedimento Cirúrgico</option>
        <option value="termo_anestesico">Termo de Autorização Anestésico</option>
        <option value="termo_eutanasia">Termo de Eutanásia</option>
    </select>
</div>

<div class="form-group">
    <div id="editor-documentos"></div>
</div>

<div class="form-actions">
    <button type="button" class="btn-acao btn-salvar" onclick="salvarDocumento()"><i class="fas fa-save"></i> Salvar Documento</button>
    <button type="button" class="btn-acao btn-cancelar" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
    <a href="consultas/realizar_consulta.php?id_paciente=<?= $dados_paciente['id'] ?? '' ?>" class="btn-acao btn-cancelar"><i class="fas fa-times"></i> Cancelar</a>
</div>

<script>
const DADOS_PACIENTE = {
    nome: <?= json_encode($nome_animal) ?>,
    especie: <?= json_encode($especie) ?>,
    raca: <?= json_encode($raca) ?>,
    sexo: <?= json_encode($sexo) ?>,
    nascimento: <?= json_encode($nascimento) ?>,
    pelagem: <?= json_encode($pelagem) ?>,
    peso: <?= json_encode($peso) ?>,
    chip: <?= json_encode($chip) ?>,
    idade: <?= json_encode($idade_texto) ?>,
    codigo: <?= $dados_paciente['id'] ?? 0 ?>
};

const DADOS_TUTOR = {
    nome: <?= json_encode($nome_tutor) ?>,
    cpf: <?= json_encode($cpf_tutor) ?>,
    rg: <?= json_encode($rg_tutor) ?>,
    endereco: <?= json_encode($endereco) ?>,
    cidade: <?= json_encode($cidade_tutor) ?>,
    uf: <?= json_encode($uf_tutor) ?>,
    cep: <?= json_encode($cep) ?>,
    telefone: <?= json_encode($fone_tutor) ?>,
    email: <?= json_encode($email_tutor) ?>,
    codigo: <?= intval($id_cliente) ?>
};

const VET_LOGADO = <?= json_encode($usuario_logado) ?>;
const DATA_EXTENSO = <?= json_encode($data_hoje_extenso) ?>;
const DATA_HOJE = '<?= date('d/m/Y') ?>';
const CIDADE = 'Goiânia';

console.log('DADOS CARREGADOS:');
console.log('Paciente:', DADOS_PACIENTE);
console.log('Responsável:', DADOS_TUTOR);

function substituirPlaceholders(texto) {
    return texto
        .replace(/\{NOME_ANIMAL\}/g, DADOS_PACIENTE.nome)
        .replace(/\{ESPECIE\}/g, DADOS_PACIENTE.especie)
        .replace(/\{RACA\}/g, DADOS_PACIENTE.raca)
        .replace(/\{SEXO\}/g, DADOS_PACIENTE.sexo)
        .replace(/\{NASCIMENTO\}/g, DADOS_PACIENTE.nascimento)
        .replace(/\{PELAGEM\}/g, DADOS_PACIENTE.pelagem)
        .replace(/\{PESO\}/g, DADOS_PACIENTE.peso)
        .replace(/\{CHIP\}/g, DADOS_PACIENTE.chip)
        .replace(/\{IDADE_ANIMAL\}/g, DADOS_PACIENTE.idade)
        .replace(/\{COD_ANIMAL\}/g, DADOS_PACIENTE.codigo)
        .replace(/\{NOME_TUTOR\}/g, DADOS_TUTOR.nome)
        .replace(/\{CPF_TUTOR\}/g, DADOS_TUTOR.cpf)
        .replace(/\{RG_TUTOR\}/g, DADOS_TUTOR.rg)
        .replace(/\{ENDERECO\}/g, DADOS_TUTOR.endereco)
        .replace(/\{CIDADE_TUTOR\}/g, DADOS_TUTOR.cidade)
        .replace(/\{UF_TUTOR\}/g, DADOS_TUTOR.uf)
        .replace(/\{CEP\}/g, DADOS_TUTOR.cep)
        .replace(/\{FONE_TUTOR\}/g, DADOS_TUTOR.telefone)
        .replace(/\{EMAIL_TUTOR\}/g, DADOS_TUTOR.email)
        .replace(/\{COD_TUTOR\}/g, DADOS_TUTOR.codigo)
        .replace(/\{VET_LOGADO\}/g, VET_LOGADO)
        .replace(/\{DATA_EXTENSO\}/g, DATA_EXTENSO)
        .replace(/\{DATA_HOJE\}/g, DATA_HOJE)
        .replace(/\{CIDADE\}/g, CIDADE);
}

const modelosDocumentos = {
    atestado_vacina: `
            <p>Atesto para os devidos fins, que o animal abaixo identificado foi vacinado por mim nesta data, conforme informações abaixo:</p>
            <p><br></p>
            <p><strong>Identificação do animal:</strong></p>
            <p>Nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, {SEXO}, nascida em {NASCIMENTO}, pelagem cor "{PELAGEM}", de {PESO} Kg, Chip: {CHIP}.</p>
            <p><br></p>
            <p>Vacinação contra: .........................</p>
            <p>Nome comercial da vacina: .................</p>
            <p>Número da partida: .........................</p>
            <p>Fabricante: .........................</p>
            <p>Data de fabricação: ........................</p>
            <p>Data de validade: .........................</p>
            <p><br></p>
            <p><strong>Outras observações:</strong></p>
            <p><br></p>
            <p><strong>Identificação do(a) responsável pelo animal:</strong></p>
            <p>Nome: <strong>{NOME_TUTOR}</strong></p>
            <p>CPF: {CPF_TUTOR}</p>
            <p>Endereço Completo: Residente em {ENDERECO}, CEP: {CEP}</p>
            <p><br></p>
            <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
            <p><br></p>
            <p style="text-align: center;">______________________________________________</p>
            <p style="text-align: center;">Assinatura do(a) Médico(a) Veterinário(a)</p>
            <p style="text-align: center;"><strong><?= $usuario_logado ?></strong></p>
            <p><br></p>
            <p style="font-size: 12px; color: #666;">(documento a ser emitido em 2 vias: 1ª via: médico-veterinário; 2ª via: proprietário, tutor/responsável)</p>
        `,
       atestado_obito: `
    <p>Atesto para os devidos fins que o animal abaixo identificado veio a óbito na localidade ........................., às ........................., horas do dia ...................., sendo a provável causa mortis .........................</p>
    <p><br></p>
    <p><strong>Identificação do animal:</strong></p>
    <p>Nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, {SEXO}, nascida em {NASCIMENTO}, pelagem cor "{PELAGEM}", de {PESO} Kg.</p>
    <p><br></p>
    <p><strong>Outras informações complementares à provável causa mortis e informação de ter sido feita a notificação obrigatória quando for o caso:</strong></p>
    <p><br></p><p><br></p>
    <p><strong>Orientações para destinação do corpo animal (aspectos sanitários e ambientais):</strong></p>
    <p><br></p><p><br></p>
    <p><strong>Identificação do(a) responsável pelo animal:</strong></p>
    <p>Nome: <strong>{NOME_TUTOR}</strong></p>
    <p>CPF: {CPF_TUTOR}</p>
    <p>Endereço Completo: Residente em {ENDERECO}, CEP: {CEP}</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p>
    <p style="text-align: center;">______________________________________________</p>
    <p style="text-align: center;">Assinatura do(a) Médico(a) Veterinário(a)</p>
    <p style="text-align: center;"><strong><?= $usuario_logado ?></strong></p>
    <p><br></p>
    <p style="font-size: 12px; color: #666;">(documento a ser emitido em 2 vias: 1ª via: médico-veterinário; 2ª via: proprietário, tutor/responsável)</p>
`,
        atestado_saude: `
    <p>Atesto para os devidos fins que examinei o animal da espécie {ESPECIE}, raça {RACA}, {SEXO}, nascida em {NASCIMENTO}, nome <strong>{NOME_ANIMAL}</strong>, pelagem cor "{PELAGEM}", de {PESO} Kg, apresentado sob responsabilidade do(a) Sr(a). <strong>{NOME_TUTOR}</strong>, RG {RG_TUTOR}, CPF {CPF_TUTOR}, residentes na {ENDERECO}, Cidade: {CIDADE_TUTOR}, Estado: {UF_TUTOR}, CEP: {CEP}.</p>
    <p><br></p>
    <p>O animal acima identificado encontra-se em bom estado clínico geral, sem sinais de doença infecto contagiosa ativa ou miíase, estando apto a embarcar, em perfeitas condições de saúde e apto a realizar viagem aérea.</p>
    <p><br></p>
    <p><strong>OBSERVAÇÃO:</strong></p>
    <p><br></p><p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>/GO, <?= $data_hoje_extenso ?></p>
    <p><br></p><p><br></p>
    <p style="text-align: center;"><strong><?= $usuario_logado ?></strong></p>
    <p style="text-align: center;">Médico(a) Veterinário(a)</p>
`,
      atestado_vacina_historico: `
    <p>Atesto que o animal acima descrito foi vacinado nas datas indicadas, tendo sido aplicadas as seguintes vacinas:</p>
    <p><br></p>
    <p><strong>Vacinas aplicadas</strong></p>
    <p>Nenhuma vacina aplicada</p>
    <p><br></p>
    <p><strong>Vacinas programadas</strong></p>
    <p>Nenhuma vacina programada</p>
    <p><br></p><p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>/GO, <?= $data_hoje_extenso ?>.</p>
    <p><br></p>
    <p style="text-align: center;">----------------------------------------------</p>
    <p style="text-align: center;"><strong><?= $usuario_logado ?></strong></p>
    <p style="text-align: center;">Auxiliar</p>
`,
     guia_transito: `
    <p style="text-align: center;"><strong style="font-size: 18px;">GUIA DE TRÂNSITO / ATESTADO DE SAÚDE PARA TRANSPORTE</strong></p>
    <p><br></p>
    <p><strong>Identificação do animal:</strong> {NOME_ANIMAL}, espécie {ESPECIE}, raça {RACA}, {SEXO}, nascida em {NASCIMENTO}, pelagem cor "{PELAGEM}".</p>
    <p><strong>Responsável:</strong> {NOME_TUTOR} (CPF: {CPF_TUTOR})</p>
    <p><br></p>
    <p style="text-align: center;"><strong>VACINAÇÃO ANTI-RÁBICA</strong></p>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <tr style="background-color: #f2f2f2; text-align: center; font-size: 14px;">
            <td style="padding: 8px; width: 40%;"><strong>Nome da Vacina e Fabricante</strong></td>
            <td style="padding: 8px; width: 20%;"><strong>Número do lote</strong></td>
            <td style="padding: 8px; width: 20%;"><strong>Data da vacinação</strong></td>
            <td style="padding: 8px; width: 20%;"><strong>Válida até</strong></td>
        </tr>
        <tr>
            <td style="padding: 20px;">&nbsp;</td>
            <td style="padding: 20px;">&nbsp;</td>
            <td style="padding: 20px;">&nbsp;</td>
            <td style="padding: 20px;">&nbsp;</td>
        </tr>
    </table>
    <p style="font-size: 13px; margin-top: 5px;">A vacinação anti-rábica é exigida para cães e gatos acima de 90 dias de idade e é válida por um ano.</p>
    <p style="font-size: 14px;"><strong>Anexar o cartão de vacinação do animal</strong></p>
    <p><br></p>
    <p>Declaro que o animal acima identificado foi por mim examinado e estava clinicamente sadio, isento de ectoparasitas à inspeção clínica e apto a ser transportado.</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p>
    <p style="text-align: center;">______________________________________________</p>
    <p style="text-align: center;">Assinatura do(a) Médico(a) Veterinário(a)</p>
    <p style="text-align: center;"><strong><?= $usuario_logado ?></strong></p>
    <p><br></p>
    <p><strong>Este atestado é válido por 10 dias.</strong></p>
    <p style="font-size: 12px; color: #555;"><strong>Observação:</strong> Outros animais de companhia somente poderão ser transportados com a Guia de Trânsito Animal – GTA (Instrução Normativa n. 18 de 18/07/2006 do Ministério da Agricultura, Pecuária e Abastecimento, publicado no D.O.U. de 20/07/2006).</p>
`,
    receita_especial: `
    <p style="text-align: center;"><strong style="font-size: 18px;">RECEITUÁRIO DE CONTROLE ESPECIAL</strong></p>
    <p><br></p>
    <p><strong>DADOS DO EMITENTE:</strong></p>
    <p>Nome: <strong>{VET_LOGADO}</strong> &nbsp;&nbsp;&nbsp;&nbsp; CRMV: __________</p>
    <p>Endereço: Avenida Perimetral 2982, 62E, LOJA 12 - Setor Coimbra</p>
    <p>Cidade / Estado: Goiânia, GO</p>
    <p>Telefones: (62) 98563-4588 - (62) 3636-7999 - (62) 98606-9444</p>
    <p>Data de emissão: <?= date('d/m/Y') ?></p>
    <p><br></p>
    <p><strong>DADOS DO PROPRIETÁRIO E ANIMAL:</strong></p>
    <p>Nome do proprietário: <strong>{NOME_TUTOR}</strong></p>
    <p>CPF: {CPF_TUTOR}</p>
    <p>Endereço: {ENDERECO}</p>
    <p>Cidade/Estado: {CIDADE_TUTOR}, {UF_TUTOR}</p>
    <p>Nome do animal: <strong>{NOME_ANIMAL}</strong></p>
    <p>Espécie: {ESPECIE} &nbsp;&nbsp;&nbsp;&nbsp; Raça: {RACA}</p>
    <p>Sexo: {SEXO} &nbsp;&nbsp;&nbsp;&nbsp; Idade: {IDADE_ANIMAL}</p>
    <p><br></p>
    <p><strong>PRESCRIÇÃO:</strong></p>
    <p><br></p><p><br></p><p><br></p>
    <p style="text-align: center;">_________________________________<br><strong>{VET_LOGADO}</strong></p>
    <p><br></p>
    <p><hr></p>
    <p>Farmácia veterinária ( &nbsp; ) &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Farmácia Humana ( &nbsp; )</p>
    <table style="width: 100%; border: none;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <p><strong>IDENTIFICAÇÃO DO COMPRADOR:</strong></p>
                <p>Nome: __________________________________</p>
                <p>RG: ___________________ Órg. Emissor: ______</p>
                <p>End.: ___________________________________</p>
                <p>Cidade: ______________________ UF: _______</p>
                <p>Telefone: ________________________________</p>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <p><strong>IDENTIFICAÇÃO DO FORNECEDOR:</strong></p>
                <p><br></p><p><br></p><p><br></p>
                <p style="text-align: center;">_____________________________</p>
                <p style="text-align: center;">Assinatura do Farmacêutico DATA __/__/__</p>
            </td>
        </tr>
    </table>
    <p style="font-size: 11px; margin-top: 10px;">1ª via - Farmácia / 2ª via - Paciente</p>
`,
    solicitacao_exame: `
    <p style="text-align: center;"><strong style="font-size: 20px;">SOLICITAÇÃO DE EXAMES</strong></p>
    <p><br></p>
    <p><strong>PACIENTE:</strong> {NOME_ANIMAL} &nbsp;&nbsp;&nbsp;&nbsp; <strong>ESPÉCIE:</strong> {ESPECIE}</p>
    <p><strong>TUTOR:</strong> {NOME_TUTOR}</p>
    <p><hr></p>
    <p><br></p>
    <p>Para o animal acima descrito, solicito:</p>
    <p><br></p>
    <p>1. ................................................................................</p>
    <p>2. ................................................................................</p>
    <p>3. ................................................................................</p>
    <p><br></p><p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?> / GO, <?= $data_hoje_extenso ?></p>
    <p><br></p>
    <p style="text-align: center;">______________________________________________</p>
    <p style="text-align: center;"><strong><?= $usuario_logado ?></strong></p>
    <p style="text-align: center;">Médico(a) Veterinário(a)</p>
`,
    termo_autorizacao_exame: `
    <p style="text-align: center;"><strong style="font-size: 18px;">TERMO DE AUTORIZAÇÃO PARA EXAME</strong></p>
    <p><br></p>
    <p>Autorizo a realização do(s) exame(s) ................................................................................ no animal de nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, sexo {SEXO}, idade (real ou aproximada) {IDADE_ANIMAL}, pelagem {PELAGEM}, a ser realizado pelo(a) Médico(a) Veterinário(a) ........................................ CRMV-..........</p>
    <p><br></p>
    <p><strong>IDENTIFICAÇÃO DO RESPONSÁVEL PELO ANIMAL:</strong></p>
    <ul style="list-style-type: none; padding-left: 0;">
        <li><strong>Nome:</strong> {NOME_TUTOR}</li>
        <li><strong>RG:</strong> {RG_TUTOR}</li>
        <li><strong>CPF:</strong> {CPF_TUTOR}</li>
        <li><strong>Endereço:</strong> {ENDERECO}</li>
        <li><strong>Telefone:</strong> {FONE_TUTOR}</li>
        <li><strong>Email:</strong> {EMAIL_TUTOR}</li>
    </ul>
    <p><br></p>
    <p>Declaro ter sido esclarecido acerca dos possíveis riscos inerentes, durante ou após a realização do(s) citado(s) exame(s), estando o referido profissional isento de quaisquer responsabilidades decorrentes de tais riscos.</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p><p><br></p>
    <p style="text-align: center;">________________________________________</p>
    <p style="text-align: center;">Assinatura do responsável pelo animal</p>
`,
    termo_internacao_cirurgia: `
    <p style="text-align: center;"><strong style="font-size: 18px;">TERMO DE AUTORIZAÇÃO PARA INTERNAÇÃO E TRATAMENTO CLÍNICO CIRÚRGICO</strong></p>
    <p><br></p>
    <p>Autorizo a realização de internação e tratamento(s) necessário(s) ................................................................................ no animal de nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, sexo {SEXO}, idade (real ou aproximada) {IDADE_ANIMAL}, pelagem {PELAGEM}, a ser realizado pelo(a) Médico(a) Veterinário(a) ........................................ CRMV-..........</p>
    <p><br></p>
    <p><strong>IDENTIFICAÇÃO DO RESPONSÁVEL PELO ANIMAL:</strong></p>
    <ul style="list-style-type: none; padding-left: 0;">
        <li><strong>Nome:</strong> {NOME_TUTOR}</li>
        <li><strong>RG:</strong> {RG_TUTOR}</li>
        <li><strong>CPF:</strong> {CPF_TUTOR}</li>
        <li><strong>Endereço:</strong> {ENDERECO}</li>
        <li><strong>Telefone:</strong> {FONE_TUTOR}</li>
        <li><strong>Email:</strong> {EMAIL_TUTOR}</li>
    </ul>
    <p><br></p>
    <p>Declaro ter sido esclarecido acerca dos possíveis riscos inerentes à situação clínica do animal, bem como do(s) tratamento(s) proposto(s), estando o referido profissional isento de quaisquer responsabilidades decorrentes de tais riscos.</p>
    <p><br></p>
    <p><strong>OBSERVAÇÕES GERAIS (a serem fornecidas pelo proprietário/responsável):</strong> ....................................................................................................................................................................................................</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p><p><br></p>
    <p style="text-align: center;">________________________________________</p>
    <p style="text-align: center;">Assinatura do responsável pelo animal</p>
`,
    termo_procedimento_cirurgico: `
    <p style="text-align: center;"><strong style="font-size: 18px;">TERMO DE AUTORIZAÇÃO PARA PROCEDIMENTO CIRÚRGICO</strong></p>
    <p><br></p>
    <p>Autorizo a realização do(s) procedimento(s) cirurgíco(s) ................................................................................ no animal de nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, sexo {SEXO}, idade (real ou aproximada) {IDADE_ANIMAL}, pelagem {PELAGEM}, a ser realizado pelo(a) Médico(a) Veterinário(a) ........................................ CRMV-..........</p>
    <p><br></p>
    <p><strong>IDENTIFICAÇÃO DO RESPONSÁVEL PELO ANIMAL:</strong></p>
    <ul style="list-style-type: none; padding-left: 0;">
        <li><strong>Nome:</strong> {NOME_TUTOR}</li>
        <li><strong>RG:</strong> {RG_TUTOR}</li>
        <li><strong>CPF:</strong> {CPF_TUTOR}</li>
        <li><strong>Endereço:</strong> {ENDERECO}</li>
        <li><strong>Telefone:</strong> {FONE_TUTOR}</li>
        <li><strong>Email:</strong> {EMAIL_TUTOR}</li>
    </ul>
    <p><br></p>
    <p>Declaro ter sido esclarecido acerca dos possíveis riscos inerentes, durante ou após a realização do procedimento cirúrgico citado, estando o referido profissional isento de quaisquer responsabilidades decorrentes de tais riscos.</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p><p><br></p>
    <p style="text-align: center;">________________________________________</p>
    <p style="text-align: center;">Assinatura do responsável pelo animal</p>
`,
    termo_anestesico: `
    <p style="text-align: center;"><strong style="font-size: 18px;">TERMO DE AUTORIZAÇÃO PARA REALIZAÇÃO DE PROCEDIMENTOS ANESTÉSICOS</strong></p>
    <p><br></p>
    <p>Autorizo a realização do(s) procedimento(s) anestésico(s) necessário(s) ................................................................................ no animal de nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, sexo {SEXO}, idade (real ou aproximada) {IDADE_ANIMAL}, pelagem {PELAGEM}, a ser realizado pelo(a) Médico(a) Veterinário(a) ........................................ CRMV-..........</p>
    <p><br></p>
    <p><strong>IDENTIFICAÇÃO DO RESPONSÁVEL PELO ANIMAL:</strong></p>
    <ul style="list-style-type: none; padding-left: 0;">
        <li><strong>Nome:</strong> {NOME_TUTOR}</li>
        <li><strong>RG:</strong> {RG_TUTOR}</li>
        <li><strong>CPF:</strong> {CPF_TUTOR}</li>
        <li><strong>Endereço:</strong> {ENDERECO}</li>
        <li><strong>Telefone:</strong> {FONE_TUTOR}</li>
        <li><strong>Email:</strong> {EMAIL_TUTOR}</li>
    </ul>
    <p><br></p>
    <p>Declaro ter sido esclarecido acerca dos possíveis riscos, inerentes ao(s) procedimento(s) proposto(s), estando o referido profissional isento de quaisquer responsabilidades decorrentes de tais riscos.</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p><p><br></p>
    <p style="text-align: center;">________________________________________</p>
    <p style="text-align: center;">Assinatura do responsável pelo animal</p>
`,
    termo_eutanasia: `
    <p style="text-align: center;"><strong style="font-size: 18px;">TERMO DE CONSENTIMENTO PARA REALIZAÇÃO DE EUTANÁSIA</strong></p>
    <p><br></p>
    <p>Declaro estar ciente dos motivos que levam à necessidade de realização da eutanásia, reconheço que esta é a opção escolhida por mim para cessar definitivamente o sofrimento e, portanto, autorizo a realização da eutanásia do animal de nome <strong>{NOME_ANIMAL}</strong>, espécie {ESPECIE}, raça {RACA}, sexo {SEXO}, idade (real ou aproximada) {IDADE_ANIMAL}, pelagem {PELAGEM}, a ser realizado pelo(a) Médico(a) Veterinário(a) ........................................ CRMV-..........</p>
    <p><br></p>
    <p><strong>IDENTIFICAÇÃO DO RESPONSÁVEL PELO ANIMAL:</strong></p>
    <ul style="list-style-type: none; padding-left: 0;">
        <li><strong>Nome:</strong> {NOME_TUTOR}</li>
        <li><strong>RG:</strong> {RG_TUTOR}</li>
        <li><strong>CPF:</strong> {CPF_TUTOR}</li>
        <li><strong>Endereço:</strong> {ENDERECO}</li>
        <li><strong>Telefone:</strong> {FONE_TUTOR}</li>
        <li><strong>Email:</strong> {EMAIL_TUTOR}</li>
    </ul>
    <p><br></p>
    <p>Declaro, ainda, que fui devidamente esclarecido(a) do método que será utilizado, assim como de que este é um processo irreversível.</p>
    <p><br></p>
    <p style="text-align: right;"><?= $cidade_unidade ?>, <?= $data_hoje_extenso ?></p>
    <p><br></p><p><br></p>
    <p style="text-align: center;">________________________________________</p>
    <p style="text-align: center;">Assinatura do responsável pelo animal</p>
`,
    };

function carregarModeloDocumento(tipo) {
    if (modelosDocumentos[tipo]) {
        const textoComDados = substituirPlaceholders(modelosDocumentos[tipo]);
        quillDocumentos.clipboard.dangerouslyPasteHTML(textoComDados);
    }
}

function salvarDocumento() {
    const htmlContent = quillDocumentos.root.innerHTML;
    const tipoDocumento = document.getElementById('modelo_documento').value;
    
    if (quillDocumentos.getText().trim() === '') { 
        Swal.fire('Atenção', 'Documento vazio.', 'warning'); 
        return; 
    }
    
    if (!tipoDocumento) { 
        Swal.fire('Atenção', 'Selecione o tipo.', 'warning'); 
        return; 
    }
    
    let idEdicao = 0;
    const campoEdicao = document.querySelector('.secao-conteudo[data-secao="documentos"] input[name="id_registro_edicao"]');
    if (campoEdicao) idEdicao = parseInt(campoEdicao.value) || 0;
    
    Swal.fire({ 
        title: 'Salvando...', 
        allowOutsideClick: false, 
        didOpen: () => Swal.showLoading() 
    });
    
    $.post('consultas/processar_realizar_consulta.php', {
        salvar_documento: 1, 
        id_paciente: idPacienteAtual, 
        tipo_documento: tipoDocumento, 
        conteudo_documento: htmlContent,
        id_registro_edicao: idEdicao
    }, function(res) {
        if (res.sucesso) {
            Swal.fire({
                icon: 'success', 
                title: idEdicao > 0 ? 'Atualizado!' : 'Salvo!'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: 'error', 
                title: 'Erro', 
                text: res.erro
            });
        }
    }, 'json');
}
</script>