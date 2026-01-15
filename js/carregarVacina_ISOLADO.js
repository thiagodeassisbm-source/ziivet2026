/**
 * VERSÃO FINAL CORRIGIDA
 */

console.log('[VACINA] ✅ Carregado!');

function carregarVacina(idRegistro, idPaciente) {
    console.log('[VACINA] === CARREGANDO ID:', idRegistro, '===');
    
    $.ajax({
        url: 'consultas/buscar_vacina_isolado.php',
        method: 'GET',
        data: { id: idRegistro },
        dataType: 'json',
        success: function(d) {
            console.log('[VACINA] Resposta:', d);
            
            if (!d.sucesso) {
                Swal.fire('Erro', d.erro, 'error');
                return;
            }
            
            // Trocar aba
            $('.tab-btn[data-secao="vacina"]').click();
            
            setTimeout(() => {
                
                // 1. NOME
                $('#formVacina select[name="vacina_nome"]').val(d.vacina_nome).trigger('change');
                
                // 2. PERFIL
                let perfil = 'Filhote';
                if (d.vacina_dose) {
                    const dl = d.vacina_dose.toLowerCase();
                    if (dl.includes('reforço') || dl.includes('reforco')) perfil = 'Adulto Já Vacinado';
                    else if (dl.includes('1ª') || dl.includes('2ª')) perfil = 'Adulto Nunca Vacinado';
                }
                $('#tipo_paciente_vac').val(perfil);
                
                // 3. AJUSTAR
                if (typeof ajustarFormularioVacina === 'function') ajustarFormularioVacina();
                
                setTimeout(() => {
                    
                    // 4. DOSE
                    const $dose = $('#formVacina select[name="vacina_dose"]');
                    $dose.val(d.vacina_dose);
                    console.log('[VACINA] Dose:', d.vacina_dose, '→', $dose.val());
                    
                    // Se não encontrou, tentar match parcial
                    if (!$dose.val() && d.vacina_dose) {
                        let encontrou = false;
                        $dose.find('option').each(function() {
                            if ($(this).val() && $(this).val().includes(d.vacina_dose)) {
                                $dose.val($(this).val());
                                encontrou = true;
                                console.log('[VACINA] Dose encontrada por match:', $(this).val());
                                return false;
                            }
                        });
                        if (!encontrou) {
                            console.warn('[VACINA] Dose não encontrada no dropdown!');
                        }
                    }
                    
                    // 5. DATA
                    $('#formVacina input[name="data_aplicacao"]').val(d.data_aplicacao);
                    
                    // 6. LOTE
                    $('#formVacina input[name="vacina_lote"]').val(d.vacina_lote || '');
                    console.log('[VACINA] Lote:', d.vacina_lote || '(vazio)');
                    
                    // 7. PROTOCOLO  
                    $('#formVacina textarea[name="protocolo_texto"]').val(d.protocolo_texto || '');
                    
                    // 8. EDIÇÃO
                    $('input[name="id_registro_edicao"]').remove();
                    $('#formVacina').prepend(`<input type="hidden" name="id_registro_edicao" value="${idRegistro}">`);
                    
                    // 9. BOTÃO
                    $('#formVacina button[type="submit"]')
                        .removeClass('btn-salvar').addClass('btn-edicao')
                        .html('<i class="fas fa-edit"></i> Atualizar Vacina');
                    
                    console.log('[VACINA] === CONCLUÍDO ===');
                    
                }, 1000);
                
            }, 500);
        },
        error: function(xhr, st, err) {
            console.error('[VACINA] Erro:', err, xhr.responseText);
            Swal.fire('Erro', 'Falha ao carregar', 'error');
        }
    });
}