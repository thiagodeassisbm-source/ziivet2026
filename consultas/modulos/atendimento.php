<div class="secao-titulo"><i class="fas fa-stethoscope"></i> Novo Atendimento</div>

<form id="formAtendimento" method="POST" action="processar_realizar_consulta.php" enctype="multipart/form-data">
    <input type="hidden" name="salvar_atendimento" value="1">
    <input type="hidden" name="id_paciente" value="<?= $dados_paciente['id'] ?>">
    <input type="hidden" name="descricao" id="descricao_atendimento">

    <div class="form-row" style="grid-template-columns: 2fr 1fr 1fr;">
        <div class="form-group">
            <label>Tipo de Atendimento <span class="req">*</span></label>
            <select name="tipo_atendimento" required>
                <option value="">Selecione...</option>
                <option value="Consulta">Consulta</option>
                <option value="Retorno">Retorno</option>
                <option value="Emergência">Emergência</option>
                <option value="Cirurgia">Cirurgia</option>
                <option value="Internação">Internação</option>
                <option value="Procedimento">Procedimento</option>
            </select>
        </div>
        <div class="form-group">
            <label>Peso (kg)</label>
            <input type="text" name="peso" placeholder="0.000">
        </div>
        <div class="form-group">
            <label>Data Retorno</label>
            <input type="date" name="data_retorno">
        </div>
    </div>

    <div class="form-group">
        <label>Resumo / Queixa Principal</label>
        <input type="text" name="resumo" placeholder="Ex: Vômitos frequentes">
    </div>

    <div class="form-group">
        <label>Descrição Clínica <span class="req">*</span></label>
        <div id="editor-atendimento"></div>
    </div>

    <div class="form-group">
        <label>Anexos</label>
        <input type="file" name="anexo">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-acao btn-salvar"><i class="fas fa-save"></i> Salvar</button>
        <a href="realizar_consulta.php?id_paciente=<?= $dados_paciente['id'] ?>" class="btn-acao btn-cancelar"><i class="fas fa-times"></i> Cancelar</a>
    </div>
</form>

<script>
// Handler do formulário
document.getElementById('formAtendimento').onsubmit = function(e) {
    var content = quillAtendimento.root.innerHTML;
    if (quillAtendimento.getText().trim() === '') {
        Swal.fire('Atenção', 'Preencha a descrição clínica.', 'warning');
        e.preventDefault(); return false;
    }
    document.getElementById('descricao_atendimento').value = content;
    return true;
};
</script>
