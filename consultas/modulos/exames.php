<div class="secao-titulo"><i class="fas fa-microscope"></i> Registro de Exame Laboratorial</div>

<form id="formExames" method="POST" action="consultas/processar_realizar_consulta.php" enctype="multipart/form-data">
    <input type="hidden" name="salvar_exame" value="1">
    <input type="hidden" name="id_paciente" value="<?= $dados_paciente['id'] ?>">
    <input type="hidden" name="conclusoes" id="conclusoes_exame">

    <div class="form-row" style="grid-template-columns: 2fr 1fr;">
        <div class="form-group">
            <label>Tipo de Exame <span class="req">*</span></label>
            <select name="tipo_exame" id="tipo_exame_select" onchange="alternarBlocoExame(this.value)" required>
                <option value="">Selecione o tipo...</option>
                <option value="bioquimico">Bioquímico</option>
                <option value="hemograma">Hemograma Completo</option>
                <option value="urina">Exame de Urina (Urinálise)</option>
                <option value="parasitologico">Parasitológico de Fezes</option>
                <option value="radiografia">Radiografia</option>
                <option value="ultrassonografia">Ultrassonografia</option>
                <option value="outros">Outros</option>
            </select>
        </div>
        <div class="form-group">
            <label>Data e Hora do Exame</label>
            <input type="datetime-local" name="res[data_geral]" value="<?= date('Y-m-d\TH:i') ?>">
        </div>
    </div>

    <!-- BIOQUÍMICO -->
    <div id="bloco-bioquimico" class="bloco-exame-tipo">
        <div style="font-weight: 700; margin: 20px 0 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Resultados - Bioquímico</div>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div class="form-group" style="margin-bottom: 0;"><label>Ureia (mg/dL)</label><input type="text" name="res[ureia]" placeholder="21,4 - 59,92"></div>
            <div class="form-group" style="margin-bottom: 0;"><label>Creatinina (mg/dL)</label><input type="text" name="res[creatinina]" placeholder="0,5 - 1,5"></div>
            <div class="form-group" style="margin-bottom: 0;"><label>ALT (U/l)</label><input type="text" name="res[alt]" placeholder="10 - 88"></div>
            <div class="form-group" style="margin-bottom: 0;"><label>AST (U/l)</label><input type="text" name="res[ast]" placeholder="23 - 66"></div>
            <div class="form-group" style="margin-bottom: 0;"><label>Glicose (mg/dL)</label><input type="text" name="res[glicose]" placeholder="74 - 143"></div>
            <div class="form-group" style="margin-bottom: 0;"><label>Colesterol (mg/dL)</label><input type="text" name="res[colesterol]" placeholder="135 - 270"></div>
        </div>
    </div>

    <!-- HEMOGRAMA -->
    <div id="bloco-hemograma" class="bloco-exame-tipo">
        <div style="font-weight: 700; margin: 20px 0 10px;">Eritrograma</div>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div class="form-group" style="margin-bottom: 0;"><label>Hemácias (milhões/µL)</label><input type="text" name="res[hemacias]" placeholder="5,5 - 8,5"></div>
            <div class="form-group" style="margin-bottom: 0;"><label>Hemoglobina (g/dL)</label><input type="text" name="res[hemoglobina]" placeholder="12.0 - 18.0"></div>
            <div class="form-group" style="margin-bottom: 0;"><label>Hematócrito (%)</label><input type="text" name="res[hematocrito]" placeholder="37 - 55"></div>
            <div class="form-group" style="margin-bottom: 0;"><label>Plaquetas (mil/µL)</label><input type="text" name="res[plaquetas]" placeholder="166 - 575"></div>
        </div>
        <div style="font-weight: 700; margin: 20px 0 10px;">Leucograma</div>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div class="form-group" style="margin-bottom: 0;"><label>Leucócitos (mil/µL)</label><input type="text" name="res[leucocitos]" placeholder="6.0 - 17.0"></div>
            <div class="form-group" style="margin-bottom: 0;"><label>Linfócitos (%)</label><input type="text" name="res[linfocitos]" placeholder="12 - 30"></div>
        </div>
    </div>

    <!-- URINA -->
    <div id="bloco-urina" class="bloco-exame-tipo">
        <div style="font-weight: 700; margin: 20px 0 15px;">Exame Físico</div>
        <div class="form-row">
            <div class="form-group"><label>Cor</label><input type="text" name="res[urina_cor]" placeholder="Amarelo claro"></div>
            <div class="form-group"><label>Aspecto</label><input type="text" name="res[urina_aspecto]" placeholder="Límpido"></div>
            <div class="form-group"><label>Densidade</label><input type="text" name="res[urina_densidade]" placeholder="1.015 - 1.045"></div>
        </div>
    </div>

    <!-- OUTROS TIPOS (simplificados) -->
    <div id="bloco-parasitologico" class="bloco-exame-tipo">
        <div class="form-group"><label>Resultado / Parasitas</label><textarea name="res[parasito_resultado]" rows="6"></textarea></div>
    </div>
    <div id="bloco-radiografia" class="bloco-exame-tipo">
        <div class="form-row">
            <div class="form-group"><label>Região</label><input type="text" name="res[radio_regiao]" placeholder="Tórax / Abdome"></div>
            <div class="form-group"><label>Projeções</label><input type="text" name="res[radio_projecoes]" placeholder="LL / VD"></div>
        </div>
    </div>
    <div id="bloco-ultrassonografia" class="bloco-exame-tipo">
        <div class="form-group"><label>Região</label><input type="text" name="res[ultra_regiao]" placeholder="Abdominal"></div>
    </div>
    <div id="bloco-outros" class="bloco-exame-tipo">
        <div class="form-group"><label>Descrição</label><input type="text" name="res[tipo_outros]" placeholder="Especifique"></div>
    </div>

    <!-- CONCLUSÕES -->
    <div style="font-size: 18px; font-weight: 700; margin: 35px 0 15px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
        <i class="fas fa-pen-nib"></i> Conclusões / Laudo <span class="req">*</span>
    </div>
    <div id="editor-exame"></div>

    <div style="margin-top: 30px;">
        <label style="font-size: 16px; font-weight: 600; margin-bottom: 10px; display: block;">Anexar Arquivos</label>
        <input type="file" name="anexos_exame[]" multiple style="padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-acao btn-salvar"><i class="fas fa-save"></i> Salvar Exame</button>
        <a href="consultas/realizar_consulta.php?id_paciente=<?= $dados_paciente['id'] ?>" class="btn-acao btn-cancelar"><i class="fas fa-times"></i> Cancelar</a>
    </div>
</form>

<script>
document.getElementById('formExames').onsubmit = function(e) {
    console.log('[EXAMES] Validando...');
    var htmlContent = quillExame.root.innerHTML;
    var textContent = quillExame.getText().trim();
    
    if (textContent.length === 0) {
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: 'Campo Obrigatório', text: 'As conclusões/laudo são obrigatórias.' });
        return false;
    }
    
    document.getElementById('conclusoes_exame').value = htmlContent;
    console.log('[EXAMES] Conclusões preenchidas!');
    return true;
};
</script>
