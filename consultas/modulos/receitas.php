<div class="secao-titulo"><i class="fas fa-prescription"></i> Emissão de Receita</div>

<form id="formReceita" method="POST" action="consultas/processar_realizar_consulta.php">
    <input type="hidden" name="salvar_receita" value="1">
    <input type="hidden" name="id_paciente" value="<?= $dados_paciente['id'] ?>">
    <input type="hidden" name="conteudo_receita" id="conteudo_receita">

    <div class="form-group">
        <label>Modelo de Receita</label>
        <div style="display: flex; gap: 10px;">
            <select id="select-modelo" onchange="aplicarModelo(this)" style="flex: 1;">
                <option value="">Escolha um modelo salvo...</option>
                <?php foreach($lista_modelos as $mod): ?>
                    <option value="<?= htmlspecialchars($mod['conteudo']) ?>"><?= htmlspecialchars($mod['titulo']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" onclick="abrirModalModelo()" title="Criar novo modelo" style="width: 48px; height: 48px; background: var(--primaria); color: #fff; border: none; border-radius: 8px; cursor: pointer;">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>

    <div class="form-group">
        <label>Prescrição</label>
        <div id="editor-receita"></div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-acao btn-salvar"><i class="fas fa-save"></i> Salvar Receita</button>
        <a href="consultas/realizar_consulta.php?id_paciente=<?= $dados_paciente['id'] ?>" class="btn-acao btn-cancelar"><i class="fas fa-times"></i> Cancelar</a>
    </div>
</form>

<script>
document.getElementById('formReceita').onsubmit = function(e) {
    var content = quillReceita.root.innerHTML;
    if (quillReceita.getText().trim() === '') {
        Swal.fire('Atenção', 'Escreva a prescrição.', 'warning');
        e.preventDefault(); return false;
    }
    document.getElementById('conteudo_receita').value = content;
    return true;
};
</script>
