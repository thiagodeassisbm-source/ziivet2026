<div class="secao-titulo"><i class="fas fa-syringe"></i> Registro de Imunização</div>

<form id="formVacina" method="POST" action="consultas/processar_realizar_consulta.php">
    <input type="hidden" name="salvar_vacina" value="1">
    <input type="hidden" name="id_paciente" value="<?= $dados_paciente['id'] ?>">
    <input type="hidden" id="adulto_2doses" name="adulto_2doses" value="">

    <div class="form-row">
        <div class="form-group">
            <label>Vacina Selecionada <span class="req">*</span></label>
            <select id="sel_vacina" name="vacina_nome" required onchange="ajustarFormularioVacina()">
                <option value="">Escolha o imunizante...</option>
                <option value="Antirrábica">Antirrábica</option>
                <option value="V8 (Anual)">V8 (Anual)</option>
                <option value="V10 (Anual)">V10 (Anual)</option>
                <option value="Quádrupla Felina (V4)">Quádrupla Felina (V4)</option>
                <option value="Gripe Canina">Gripe Canina</option>
                <option value="Giárdia">Giárdia</option>
                <option value="Leish-Tec">Leish-Tec</option>
                <option value="Covenia">Covenia (Antibiótico)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Perfil do Paciente <span class="req">*</span></label>
            <select id="tipo_paciente_vac" onchange="ajustarFormularioVacina()">
                <option value="Filhote">Filhote / Primovacinação</option>
                <option value="Adulto Nunca Vacinado">Adulto (Sem histórico)</option>
                <option value="Adulto Já Vacinado">Adulto (Já vacinado)</option>
            </select>
        </div>
    </div>

    <div class="form-row" style="grid-template-columns: 1fr 1fr 0.8fr;">
        <div class="form-group">
            <label>Dose Aplicada <span class="req">*</span></label>
            <select name="vacina_dose" id="vacina_dose" required></select>
        </div>
        <div class="form-group">
            <label>Data da Aplicação</label>
            <input type="date" name="data_aplicacao" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
            <label>Lote / Fabricante</label>
            <input type="text" name="vacina_lote" placeholder="Ex: Zoetis L123">
        </div>
    </div>

    <div class="form-group">
        <label>Protocolo Aplicado</label>
        <textarea id="txt_protocolo" name="protocolo_texto" rows="10"></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-acao btn-salvar"><i class="fas fa-save"></i> Salvar Vacina</button>
        <a href="consultas/realizar_consulta.php?id_paciente=<?= $dados_paciente['id'] ?>" class="btn-acao btn-cancelar"><i class="fas fa-times"></i> Cancelar</a>
    </div>
</form>

<script>
const configVacinas = {
    "Antirrábica": {
        "Filhote": { doses: ["Dose Única", "Reforço Anual"], protocolo: "Filhotes: dose única aos 3-4 meses.\nReforço: Anual." },
        "Adulto Nunca Vacinado": { doses: ["Dose Única", "Reforço Anual"], protocolo: "1 dose.\nReforço anual." },
        "Adulto Já Vacinado": { doses: ["Reforço Anual"], protocolo: "1 dose anual." }
    },
    "V8 (Anual)": {
        "Filhote": { doses: ["1ª Dose", "2ª Dose", "3ª Dose", "Reforço Anual"], protocolo: "3 doses (21-30 dias de intervalo)" },
        "Adulto Nunca Vacinado": { doses: ["1ª Dose", "2ª Dose", "Reforço Anual"], protocolo: "2 doses (21 dias)" },
        "Adulto Já Vacinado": { doses: ["Reforço Anual"], protocolo: "1 dose anual" }
    }
};

function ajustarFormularioVacina() {
    const vacina = $('#sel_vacina').val();
    const tipoPac = $('#tipo_paciente_vac').val();
    const doseSelect = $('#vacina_dose');
    const txtArea = $('#txt_protocolo');
    
    doseSelect.empty();
    if (!vacina) { txtArea.val(''); return; }

    let data = configVacinas[vacina];
    if (!data) { 
        doseSelect.append('<option value="Dose Única">Dose Única</option>');
        txtArea.val('Protocolo padrão');
        return; 
    }
    
    if (data[tipoPac]) data = data[tipoPac];
    if (data.doses) data.doses.forEach(d => doseSelect.append(`<option value="${d}">${d}</option>`));
    if (data.protocolo) txtArea.val(data.protocolo);
}
</script>
