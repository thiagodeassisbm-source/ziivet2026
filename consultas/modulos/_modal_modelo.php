<div id="modalModelo" class="modal">
    <div class="modal-content">
        <form method="POST" action="consultas/processar_realizar_consulta.php">
            <input type="hidden" name="salvar_modelo" value="1">
            <input type="hidden" name="id_paciente" value="<?= $dados_paciente['id'] ?? '' ?>">
            <h4 style="margin-bottom: 25px; color: var(--primaria); font-size: 20px; font-weight: 700;">
                <i class="fas fa-file-medical"></i> Novo Modelo de Receita
            </h4>
            <div class="form-group">
                <label>Título do Modelo</label>
                <input type="text" name="novo_titulo" required placeholder="Digite o nome do modelo...">
            </div>
            <div class="form-group">
                <label>Conteúdo da Prescrição</label>
                <textarea name="novo_conteudo" rows="8" required placeholder="Escreva o texto..."></textarea>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 30px; justify-content: flex-end;">
                <button type="button" class="btn-acao btn-cancelar" onclick="fecharModalModelo()"><i class="fas fa-times"></i> Cancelar</button>
                <button type="submit" class="btn-acao btn-salvar"><i class="fas fa-save"></i> Salvar Modelo</button>
            </div>
        </form>
    </div>
</div>
