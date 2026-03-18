<?php
/**
 * MÓDULO: Diagnóstico Assistido por IA
 * Integrado ao Console de Atendimento
 */

// Carregar configuração da IA
require_once 'config_ia.php';

// Carregar histórico médico do paciente
$historico_medico_ia = [];
if ($id_paciente_selecionado) {
    try {
        // Atendimentos
        $stmt_atend = $pdo->prepare("SELECT tipo_atendimento, resumo, descricao, data_atendimento 
                                      FROM atendimentos WHERE id_paciente = ? 
                                      ORDER BY data_atendimento DESC LIMIT 5");
        $stmt_atend->execute([$id_paciente_selecionado]);
        $historico_medico_ia['atendimentos'] = $stmt_atend->fetchAll(PDO::FETCH_ASSOC);
        
        // Patologias
        $stmt_pato = $pdo->prepare("SELECT nome_doenca, data_registro 
                                    FROM patologias WHERE id_paciente = ? 
                                    ORDER BY data_registro DESC");
        $stmt_pato->execute([$id_paciente_selecionado]);
        $historico_medico_ia['patologias'] = $stmt_pato->fetchAll(PDO::FETCH_ASSOC);
        
        // Vacinas
        $stmt_vac = $pdo->prepare("SELECT resumo, data_atendimento 
                                   FROM atendimentos WHERE id_paciente = ? AND tipo_atendimento = 'Vacinação'
                                   ORDER BY data_atendimento DESC LIMIT 10");
        $stmt_vac->execute([$id_paciente_selecionado]);
        $historico_medico_ia['vacinas'] = $stmt_vac->fetchAll(PDO::FETCH_ASSOC);
        
        // Exames
        $stmt_exam = $pdo->prepare("SELECT tipo_exame, conclusoes_finais, data_exame 
                                    FROM exames WHERE id_paciente = ? 
                                    ORDER BY data_exame DESC LIMIT 5");
        $stmt_exam->execute([$id_paciente_selecionado]);
        $historico_medico_ia['exames'] = $stmt_exam->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erro ao carregar histórico IA: " . $e->getMessage());
    }
}

// Calcular idade
$idade_ia = '';
if ($dados_paciente && $dados_paciente['data_nascimento']) {
    $nasc = new DateTime($dados_paciente['data_nascimento']);
    $hoje = new DateTime();
    $diff = $hoje->diff($nasc);
    $idade_ia = $diff->y . " anos e " . $diff->m . " meses";
}

// Lista de sintomas categorizada
$sintomas_categorias = [
    'Gastrointestinal' => [
        'vomito' => 'Vômito',
        'vomito_sangue' => 'Vômito com sangue',
        'diarreia' => 'Diarréia',
        'diarreia_sangue' => 'Diarréia com sangue',
        'constipacao' => 'Constipação',
        'falta_apetite' => 'Falta de apetite',
        'sede_excessiva' => 'Sede excessiva',
        'perda_peso' => 'Perda de peso'
    ],
    'Respiratório' => [
        'tosse' => 'Tosse',
        'espirros' => 'Espirros frequentes',
        'secrecao_nasal' => 'Secreção nasal',
        'dificuldade_respirar' => 'Dificuldade para respirar',
        'respiracao_rapida' => 'Respiração rápida',
        'respiracao_ruidosa' => 'Respiração ruidosa/chiado'
    ],
    'Comportamental' => [
        'letargia' => 'Letargia/Apatia',
        'agitacao' => 'Agitação excessiva',
        'agressividade' => 'Agressividade incomum',
        'confusao' => 'Confusão/Desorientação',
        'convulsoes' => 'Convulsões',
        'tremores' => 'Tremores'
    ],
    'Pele e Pelos' => [
        'coceira' => 'Coceira intensa',
        'queda_pelo' => 'Queda de pelo',
        'feridas_pele' => 'Feridas na pele',
        'vermelhidao_pele' => 'Vermelhidão na pele',
        'inchacos' => 'Inchaços/Nódulos'
    ],
    'Outros' => [
        'febre' => 'Febre',
        'desidratacao' => 'Desidratação',
        'mucosas_palidas' => 'Mucosas pálidas',
        'ictericia' => 'Icterícia',
        'salivacao_excessiva' => 'Salivação excessiva'
    ]
];
?>

<style>
    .ia-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px; padding: 25px; color: #fff; margin-bottom: 25px;
        display: flex; align-items: center; gap: 15px;
    }
    .ia-header i { font-size: 40px; }
    .ia-header h3 { font-size: 20px; margin-bottom: 5px; }
    .ia-header p { opacity: 0.9; font-size: 13px; }
    
    .paciente-resumo-ia {
        background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px;
        border-left: 4px solid var(--primaria);
    }
    .info-grid-ia { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; }
    .info-item-ia { font-size: 13px; }
    .info-item-ia strong { display: block; color: #666; font-size: 11px; }
    
    .sintomas-categoria { margin-bottom: 25px; }
    .sintomas-categoria h4 { 
        font-size: 14px; color: var(--primaria); margin-bottom: 12px; 
        padding-bottom: 8px; border-bottom: 2px solid #f0f0f0; 
    }
    .sintomas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
    .sintoma-item {
        display: flex; align-items: center; gap: 10px; padding: 10px 12px;
        background: #f8f9fa; border-radius: 8px; cursor: pointer; transition: all 0.2s;
        border: 2px solid transparent;
    }
    .sintoma-item:hover { background: #e9ecef; }
    .sintoma-item.selected { background: #e8f5e9; border-color: var(--sucesso); }
    .sintoma-item input { display: none; }
    .sintoma-item .check-box {
        width: 22px; height: 22px; border: 2px solid #ccc; border-radius: 4px;
        display: flex; align-items: center; justify-content: center; transition: all 0.2s;
        color: transparent;
    }
    .sintoma-item.selected .check-box { background: var(--sucesso); border-color: var(--sucesso); color: #fff; }
    
    .btn-diagnosticar-ia {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff; border: none; padding: 18px 40px; border-radius: 10px;
        font-size: 16px; font-weight: 700; cursor: pointer;
        display: flex; align-items: center; gap: 12px; width: 100%;
        justify-content: center; transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    .btn-diagnosticar-ia:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5); }
    .btn-diagnosticar-ia:disabled { opacity: 0.6; cursor: not-allowed; }
    
    .loading-ia { display: none; text-align: center; padding: 40px; }
    .loading-ia.visible { display: block; }
    .loading-ia i { font-size: 50px; color: var(--primaria); animation: spin 1s linear infinite; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    
    .resultado-ia { display: none; margin-top: 25px; }
    .resultado-ia.visible { display: block; animation: fadeIn 0.5s ease; }
    .resultado-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff; padding: 20px; border-radius: 12px 12px 0 0;
    }
    .resultado-body { background: #fff; padding: 25px; border-radius: 0 0 12px 12px; }
    .resultado-body h4 { color: var(--primaria); margin: 20px 0 10px; }
    
    .tempo-sintomas { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
    .tempo-btn {
        padding: 10px 20px; border: 2px solid #ddd; border-radius: 8px;
        background: #fff; cursor: pointer; transition: all 0.2s; font-size: 13px;
    }
    .tempo-btn:hover { border-color: var(--primaria); }
    .tempo-btn.selected { background: var(--primaria); color: #fff; border-color: var(--primaria); }
    
    .btn-salvar-diagnostico {
        background: linear-gradient(135deg, var(--sucesso) 0%, #20c997 100%);
        color: #fff; border: none; padding: 14px 28px; border-radius: 8px;
        font-weight: 700; cursor: pointer; display: inline-flex;
        align-items: center; gap: 10px; font-size: 14px;
        transition: all 0.3s ease; text-transform: uppercase;
        letter-spacing: 0.5px; margin-bottom: 15px;
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }
    .btn-salvar-diagnostico:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4);
    }
    .btn-salvar-diagnostico i { font-size: 16px; }
</style>

<div class="ia-header">
    <i class="fas fa-brain"></i>
    <div>
        <h3>Diagnóstico Assistido por IA</h3>
        <p>Sistema inteligente de apoio ao diagnóstico veterinário • Powered by Google Gemini</p>
    </div>
</div>

<?php if (!iaConfigurada()): ?>
<div class="api-warning" style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin-bottom: 20px; color: #721c24;">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>API não configurada!</strong> 
    Configure sua chave da API do Google Gemini no arquivo <code>config_ia.php</code>. 
    <a href="https://makersuite.google.com/app/apikey" target="_blank">Obter chave gratuita</a>
</div>
<?php endif; ?>

<!-- Resumo do Paciente -->
<div class="paciente-resumo-ia">
    <div style="font-weight: 700; margin-bottom: 10px;">
        <i class="fas <?= strtolower($dados_paciente['especie']) == 'felina' ? 'fa-cat' : 'fa-dog' ?>"></i>
        <?= htmlspecialchars($dados_paciente['nome_paciente']) ?>
    </div>
    <div class="info-grid-ia">
        <div class="info-item-ia"><strong>Espécie</strong><?= htmlspecialchars($dados_paciente['especie']) ?></div>
        <div class="info-item-ia"><strong>Raça</strong><?= htmlspecialchars($dados_paciente['raca'] ?? 'SRD') ?></div>
        <div class="info-item-ia"><strong>Idade</strong><?= $idade_ia ?: 'Não informada' ?></div>
        <div class="info-item-ia"><strong>Peso</strong><?= htmlspecialchars($dados_paciente['peso'] ?? '-') ?> kg</div>
        <div class="info-item-ia"><strong>Sexo</strong><?= htmlspecialchars($dados_paciente['sexo'] ?? '-') ?></div>
    </div>
</div>

<form id="formDiagnosticoIA">
    <input type="hidden" name="id_paciente" value="<?= $dados_paciente['id'] ?>">
    
    <!-- Sintomas -->
    <div style="margin-bottom: 25px;">
        <h4 style="font-size: 16px; font-weight: 700; margin-bottom: 15px;">
            <i class="fas fa-clipboard-list"></i> Sintomas Apresentados
        </h4>
        
        <?php foreach($sintomas_categorias as $categoria => $sintomas): ?>
        <div class="sintomas-categoria">
            <h4><?= $categoria ?></h4>
            <div class="sintomas-grid">
                <?php foreach($sintomas as $key => $label): ?>
                <label class="sintoma-item" data-sintoma="<?= $key ?>">
                    <input type="checkbox" name="sintomas[]" value="<?= $key ?>">
                    <span class="check-box"><i class="fas fa-check"></i></span>
                    <span><?= $label ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Informações Complementares -->
    <div class="form-row">
        <div class="form-group">
            <label>Há quanto tempo os sintomas começaram?</label>
            <div class="tempo-sintomas">
                <label class="tempo-btn" onclick="selecionarTempoIA(this)">
                    <input type="radio" name="tempo_sintomas" value="hoje" style="display:none;">
                    Hoje
                </label>
                <label class="tempo-btn" onclick="selecionarTempoIA(this)">
                    <input type="radio" name="tempo_sintomas" value="1-3 dias" style="display:none;">
                    1-3 dias
                </label>
                <label class="tempo-btn" onclick="selecionarTempoIA(this)">
                    <input type="radio" name="tempo_sintomas" value="4-7 dias" style="display:none;">
                    4-7 dias
                </label>
                <label class="tempo-btn" onclick="selecionarTempoIA(this)">
                    <input type="radio" name="tempo_sintomas" value="mais de 2 semanas" style="display:none;">
                    +2 semanas
                </label>
            </div>
        </div>
        <div class="form-group">
            <label>Alimentação</label>
            <select name="alimentacao">
                <option value="">Selecione...</option>
                <option value="Normal">Normal</option>
                <option value="Comendo menos">Comendo menos</option>
                <option value="Quase não come">Quase não come</option>
                <option value="Não come nada">Não come nada</option>
            </select>
        </div>
    </div>
    
    <div class="form-group">
        <label>Descrição detalhada dos sintomas</label>
        <textarea name="descricao_sintomas" placeholder="Descreva com detalhes o que o animal está apresentando..." rows="5"></textarea>
    </div>
    
    <!-- Botão Analisar -->
    <button type="submit" class="btn-diagnosticar-ia" <?= !iaConfigurada() ? 'disabled' : '' ?>>
        <i class="fas fa-robot"></i>
        Analisar com Inteligência Artificial
    </button>
</form>

<!-- Loading -->
<div class="loading-ia" id="loadingDiagnosticoIA">
    <i class="fas fa-spinner"></i>
    <p>Analisando sintomas e histórico médico...<br>Aguarde enquanto a IA processa as informações.</p>
</div>

<!-- Resultado -->
<div class="resultado-ia" id="resultadoDiagnosticoIA">
    <div class="resultado-header">
        <h3><i class="fas fa-brain"></i> Análise Diagnóstica</h3>
    </div>
    <div class="resultado-body" id="conteudoResultadoIA">
        <!-- Será preenchido via JavaScript -->
    </div>
    <div style="padding: 0 25px 25px;">
        <button type="button" class="btn-salvar-diagnostico" id="btnSalvarDiagnosticoIA" onclick="salvarDiagnosticoIA()" style="display: none;">
            <i class="fas fa-save"></i> Salvar no Prontuário
        </button>
        
        <div class="disclaimer">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Aviso:</strong> Este diagnóstico é uma sugestão baseada em IA e serve apenas como apoio. 
            O diagnóstico definitivo deve ser feito por exame clínico presencial.
        </div>
    </div>
</div>

<script>
var dadosPacienteIA = <?= json_encode($dados_paciente ?? null) ?>;
var historicoMedicoIA = <?= json_encode($historico_medico_ia ?? []) ?>;
var ultimoResultadoIA = null;

$(document).ready(function() {
    // Clique nos sintomas
    $(document).on('click', '.sintoma-item', function(e) {
        e.preventDefault();
        $(this).toggleClass('selected');
        var checkbox = $(this).find('input[type="checkbox"]');
        checkbox.prop('checked', $(this).hasClass('selected'));
    });
    
    // Submit do formulário
    $('#formDiagnosticoIA').on('submit', function(e) {
        e.preventDefault();
        realizarDiagnosticoIA();
    });
});

function selecionarTempoIA(el) {
    document.querySelectorAll('.tempo-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input').checked = true;
}

function realizarDiagnosticoIA() {
    const sintomasSelecionados = [];
    document.querySelectorAll('input[name="sintomas[]"]:checked').forEach(cb => {
        sintomasSelecionados.push(cb.value);
    });
    
    if (sintomasSelecionados.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Marque pelo menos um sintoma.' });
        return;
    }

    const formData = {
        id_paciente: dadosPacienteIA.id,
        paciente: dadosPacienteIA,
        historico: historicoMedicoIA,
        sintomas: sintomasSelecionados,
        tempo_sintomas: $('input[name="tempo_sintomas"]:checked').val() || 'não informado',
        alimentacao: $('select[name="alimentacao"]').val() || 'não informado',
        descricao: $('textarea[name="descricao_sintomas"]').val()
    };

    console.log('[IA] Enviando para análise...', formData);

    $('#loadingDiagnosticoIA').addClass('visible');
    $('#resultadoDiagnosticoIA').removeClass('visible');

    $.ajax({
        url: 'processar_diagnostico.php',
        method: 'POST',
        data: formData,
        dataType: 'json',
        timeout: 90000,
        success: function(resposta) {
            if (resposta.sucesso) {
                ultimoResultadoIA = resposta;
                exibirResultadoIA(resposta.diagnostico);
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: resposta.erro });
            }
        },
        error: function(xhr, status, error) {
            console.error('[IA] Erro:', status, error);
            Swal.fire({ icon: 'error', title: 'Falha', text: 'Erro de comunicação com o servidor.' });
        },
        complete: function() {
            $('#loadingDiagnosticoIA').removeClass('visible');
        }
    });
}

function exibirResultadoIA(texto) {
    let html = texto
        .replace(/🔍\s*DIAGNÓSTICOS PROVÁVEIS:/gi, '<h4><i class="fas fa-search-plus"></i> Diagnósticos Prováveis</h4>')
        .replace(/📋\s*EXAMES RECOMENDADOS:/gi, '<h4><i class="fas fa-vial"></i> Exames Recomendados</h4>')
        .replace(/💊\s*CONDUTA SUGERIDA:/gi, '<h4><i class="fas fa-pills"></i> Conduta Sugerida</h4>')
        .replace(/⚠️\s*NÍVEL DE URGÊNCIA:/gi, '<h4><i class="fas fa-exclamation-triangle"></i> Nível de Urgência</h4>')
        .replace(/\n\n/g, '</p><p>')
        .replace(/\n/g, '<br>')
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    html = '<p>' + html + '</p>';
    
    $('#conteudoResultadoIA').html(html);
    $('#resultadoDiagnosticoIA').addClass('visible');
    $('#btnSalvarDiagnosticoIA').show(); // MOSTRAR BOTÃO
    
    $('html, body').animate({
        scrollTop: $('#resultadoDiagnosticoIA').offset().top - 100
    }, 500);
}

function salvarDiagnosticoIA() {
    if (!ultimoResultadoIA) {
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Nenhum diagnóstico para salvar.' });
        return;
    }
    
    Swal.fire({
        title: 'Salvar Diagnóstico no Prontuário?',
        html: 'O diagnóstico por IA será registrado no histórico médico do paciente e ficará disponível para consulta futura.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-save"></i> Sim, Salvar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d'
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loading
            Swal.fire({
                title: 'Salvando...',
                html: 'Registrando diagnóstico no prontuário',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Preparar dados para salvar
            const sintomasSelecionados = [];
            document.querySelectorAll('input[name="sintomas[]"]:checked').forEach(cb => {
                const label = cb.closest('.sintoma-item').querySelector('span:last-child').textContent;
                sintomasSelecionados.push(label);
            });
            
            $.ajax({
                url: 'consultas/processar_diagnostico.php',
                method: 'POST',
                data: {
                    salvar_diagnostico: 1,
                    id_paciente: dadosPacienteIA.id,
                    diagnostico: ultimoResultadoIA.diagnostico,
                    sintomas: sintomasSelecionados.join(', '),
                    tempo_sintomas: $('input[name="tempo_sintomas"]:checked').val() || 'não informado',
                    alimentacao: $('select[name="alimentacao"]').val() || 'não informado',
                    descricao_adicional: $('textarea[name="descricao_sintomas"]').val()
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp.sucesso) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Diagnóstico Salvo!',
                            html: 'O diagnóstico foi registrado com sucesso no prontuário do paciente.',
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            // Esconder botão após salvar
                            $('#btnSalvarDiagnosticoIA').hide();
                            // Recarregar página para atualizar histórico
                            location.reload();
                        });
                    } else {
                        Swal.fire({ 
                            icon: 'error', 
                            title: 'Erro ao Salvar', 
                            text: resp.erro || 'Não foi possível salvar o diagnóstico.' 
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[IA] Erro ao salvar:', status, error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro de Comunicação',
                        text: 'Não foi possível salvar o diagnóstico. Tente novamente.'
                    });
                }
            });
        }
    });
}
</script>