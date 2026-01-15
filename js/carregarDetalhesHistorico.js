/**
 * ZIIPVET - CARREGADOR DE DETALHES DO HISTÓRICO
 * NOTA: A função carregarVacina() foi movida para carregarVacina_ISOLADO.js
 */

/**
 * ZIIPVET - CARREGADOR DE DETALHES DO HISTÓRICO
 */

$(document).ready(function() {
    
    // Evento de clique nos itens do histórico
    $(document).on('click', '.historico-item-clicavel', function() {
        const tipo = $(this).data('tipo');
        const idRegistro = $(this).data('id-registro');
        const idPaciente = $(this).data('id-paciente');
        
        switch(tipo) {
            case 'atendimento':
                carregarAtendimento(idRegistro, idPaciente);
                break;
            case 'vacina':
                carregarVacina(idRegistro, idPaciente);
                break;
            case 'patologia':
                carregarPatologia(idRegistro, idPaciente);
                break;
            case 'exame':
                carregarExame(idRegistro, idPaciente);
                break;
            case 'receita':
                carregarReceita(idRegistro, idPaciente);
                break;
            case 'documento':
                carregarDocumento(idRegistro, idPaciente);
                break;
            case 'diagnostico-ia':
                carregarDiagnosticoIA(idRegistro, idPaciente);
                break;
        }
    });
    
    // LIMPAR FORMULÁRIOS AO TROCAR DE ABA
    $(document).on('click', '.tab-btn', function() {
        limparFormularios();
    });
});

function limparFormularios() {
    $('#formAtendimento')[0]?.reset();
    $('#formPatologia')[0]?.reset();
    $('#formExames')[0]?.reset();
    $('#formReceita')[0]?.reset();
    
    if (window.quillAtendimento) window.quillAtendimento.setText('');
    if (window.quillExame) window.quillExame.setText('');
    if (window.quillReceita) window.quillReceita.setText('');
    if (window.quillDocumentos) window.quillDocumentos.setText('');
    
    $('input[name="id_registro_edicao"]').remove();
    
    $('#formAtendimento button[type="submit"]').removeClass('btn-edicao').addClass('btn-salvar')
        .html('<i class="fas fa-save"></i> Salvar Atendimento');
    $('#formPatologia button[type="submit"]').removeClass('btn-edicao').addClass('btn-salvar')
        .html('<i class="fas fa-save"></i> Salvar');
    $('#formExames button[type="submit"]').removeClass('btn-edicao').addClass('btn-salvar')
        .html('<i class="fas fa-save"></i> Salvar Exame');
    $('#formReceita button[type="submit"]').removeClass('btn-edicao').addClass('btn-salvar')
        .html('<i class="fas fa-save"></i> Salvar Receita');
    
    $('#select-modelo').val('');
    $('.bloco-exame-tipo').hide();
}

function carregarAtendimento(idRegistro, idPaciente) {
    $.ajax({
        url: 'consultas/buscar_registro.php',
        method: 'GET',
        data: { tipo: 'atendimento', id: idRegistro },
        dataType: 'json',
        success: function(dados) {
            if (dados.sucesso) {
                $('.tab-btn[data-secao="atendimento"]').click();
                
                setTimeout(() => {
                    $('#formAtendimento select[name="tipo_atendimento"]').val(dados.tipo_atendimento);
                    $('#formAtendimento input[name="peso"]').val(dados.peso);
                    $('#formAtendimento input[name="data_retorno"]').val(dados.data_retorno);
                    $('#formAtendimento input[name="resumo"]').val(dados.resumo);
                    
                    if (window.quillAtendimento && dados.descricao) {
                        window.quillAtendimento.root.innerHTML = dados.descricao;
                    }
                    
                    if (!$('#formAtendimento input[name="id_registro_edicao"]').length) {
                        $('#formAtendimento').prepend(`<input type="hidden" name="id_registro_edicao" value="${idRegistro}">`);
                    }
                    
                    $('#formAtendimento button[type="submit"]').removeClass('btn-salvar').addClass('btn-edicao')
                        .html('<i class="fas fa-edit"></i> Atualizar Atendimento');
                    
                    Swal.fire({
                        icon: 'info',
                        title: 'Atendimento Carregado',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }, 300);
            }
        }
    });
}

function carregarPatologia(idRegistro, idPaciente) {
    $.ajax({
        url: 'consultas/buscar_registro.php',
        method: 'GET',
        data: { tipo: 'patologia', id: idRegistro },
        dataType: 'json',
        success: function(dados) {
            if (dados.sucesso) {
                $('.tab-btn[data-secao="patologia"]').click();
                
                setTimeout(() => {
                    $('#formPatologia select[name="patologia_nome"]').val(dados.nome_doenca);
                    
                    let dataFormatada = dados.data_registro;
                    if (dataFormatada && dataFormatada.includes(' ')) {
                        dataFormatada = dataFormatada.split(' ')[0];
                    }
                    $('#formPatologia input[name="data_registro"]').val(dataFormatada);
                    $('#formPatologia textarea[name="protocolo_descricao"]').val(dados.protocolo);
                    
                    if (!$('#formPatologia input[name="id_registro_edicao"]').length) {
                        $('#formPatologia').prepend(`<input type="hidden" name="id_registro_edicao" value="${idRegistro}">`);
                    }
                    
                    $('#formPatologia button[type="submit"]').removeClass('btn-salvar').addClass('btn-edicao')
                        .html('<i class="fas fa-edit"></i> Atualizar Patologia');
                    
                    Swal.fire({
                        icon: 'info',
                        title: 'Patologia Carregada',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }, 300);
            }
        }
    });
}

function carregarExame(idRegistro, idPaciente) {
    $.ajax({
        url: 'consultas/buscar_registro.php',
        method: 'GET',
        data: { tipo: 'exame', id: idRegistro },
        dataType: 'json',
        success: function(dados) {
            if (dados.sucesso) {
                $('.tab-btn[data-secao="exame"]').click();
                
                setTimeout(() => {
                    $('#formExames select[name="tipo_exame"]').val(dados.tipo_exame).trigger('change');
                    
                    if (window.quillExame && dados.conclusoes_finais) {
                        window.quillExame.root.innerHTML = dados.conclusoes_finais;
                    }
                    
                    if (dados.resultados) {
                        let resultados;
                        try {
                            resultados = typeof dados.resultados === 'string' 
                                ? JSON.parse(dados.resultados) 
                                : dados.resultados;
                        } catch (e) {
                            resultados = {};
                        }
                        
                        for (let campo in resultados) {
                            const valor = resultados[campo];
                            const input = $(`#formExames input[name="res[${campo}]"], #formExames textarea[name="res[${campo}]"]`);
                            if (input.length) {
                                input.val(valor);
                            }
                        }
                    }
                    
                    if (!$('#formExames input[name="id_registro_edicao"]').length) {
                        $('#formExames').prepend(`<input type="hidden" name="id_registro_edicao" value="${idRegistro}">`);
                    }
                    
                    $('#formExames button[type="submit"]').removeClass('btn-salvar').addClass('btn-edicao')
                        .html('<i class="fas fa-edit"></i> Atualizar Exame');
                    
                    Swal.fire({
                        icon: 'info',
                        title: 'Exame Carregado',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }, 500);
            }
        }
    });
}

function carregarReceita(idRegistro, idPaciente) {
    $.ajax({
        url: 'consultas/buscar_registro.php',
        method: 'GET',
        data: { tipo: 'receita', id: idRegistro },
        dataType: 'json',
        success: function(dados) {
            if (dados.sucesso) {
                $('.tab-btn[data-secao="receita"]').click();
                
                setTimeout(() => {
                    if (window.quillReceita && dados.conteudo) {
                        window.quillReceita.root.innerHTML = dados.conteudo;
                    }
                    
                    const selectModelo = $('#select-modelo');
                    
                    function normalizarTexto(texto) {
                        return texto.replace(/<p>/gi, '').replace(/<\/p>/gi, '').replace(/\\r\\n/g, '\n').replace(/\r\n/g, '\n').replace(/\n+/g, '\n').trim();
                    }
                    
                    const conteudoNormalizado = normalizarTexto(dados.conteudo);
                    let modeloEncontrado = false;
                    
                    selectModelo.find('option').each(function() {
                        const valorModelo = $(this).val();
                        if (valorModelo && valorModelo.trim().length > 0) {
                            const modeloNormalizado = normalizarTexto(valorModelo);
                            if (modeloNormalizado === conteudoNormalizado) {
                                selectModelo.val(valorModelo);
                                modeloEncontrado = true;
                                return false;
                            }
                        }
                    });
                    
                    if (!modeloEncontrado) {
                        const conteudoTexto = $('<div>').html(dados.conteudo).text().trim().toLowerCase();
                        selectModelo.find('option').each(function() {
                            const valorModelo = $(this).val();
                            if (valorModelo) {
                                const modeloTexto = $('<div>').html(valorModelo).text().trim().toLowerCase();
                                if (modeloTexto.length > 20 && conteudoTexto.length > 20) {
                                    const similarity = calcularSimilaridade(conteudoTexto, modeloTexto);
                                    if (similarity > 0.98) {
                                        selectModelo.val(valorModelo);
                                        modeloEncontrado = true;
                                        return false;
                                    }
                                }
                            }
                        });
                    }
                    
                    if (!$('#formReceita input[name="id_registro_edicao"]').length) {
                        $('#formReceita').prepend(`<input type="hidden" name="id_registro_edicao" value="${idRegistro}">`);
                    }
                    
                    $('#formReceita button[type="submit"]').removeClass('btn-salvar').addClass('btn-edicao')
                        .html('<i class="fas fa-edit"></i> Atualizar Receita');
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Receita Carregada',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }, 500);
            }
        }
    });
}

function carregarDocumento(idRegistro, idPaciente) {
    $.ajax({
        url: 'consultas/buscar_registro.php',
        method: 'GET',
        data: { tipo: 'documento', id: idRegistro },
        dataType: 'json',
        success: function(dados) {
            if (dados.sucesso) {
                $('.tab-btn[data-secao="documentos"]').click();
                
                setTimeout(() => {
                    // 1. Selecionar o tipo e DISPARAR o evento change
                    $('#modelo_documento').val(dados.tipo_documento).trigger('change');
                    
                    // 2. AGUARDAR o modelo carregar, depois sobrescrever com conteúdo salvo
                    setTimeout(() => {
                        if (window.quillDocumentos && dados.conteudo) {
                            window.quillDocumentos.root.innerHTML = dados.conteudo;
                        }
                        
                        // 3. Campo de edição
                        if (!$('.secao-conteudo[data-secao="documentos"] input[name="id_registro_edicao"]').length) {
                            $('.secao-conteudo[data-secao="documentos"]').prepend(`<input type="hidden" name="id_registro_edicao" value="${idRegistro}">`);
                        }
                        
                        Swal.fire({
                            icon: 'info',
                            title: 'Documento Carregado',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }, 500);
                }, 300);
            }
        }
    });
}

function carregarDiagnosticoIA(idRegistro, idPaciente) {
    $.ajax({
        url: 'consultas/buscar_registro.php',
        method: 'GET',
        data: { tipo: 'diagnostico-ia', id: idRegistro },
        dataType: 'json',
        success: function(dados) {
            if (dados.sucesso) {
                $('.tab-btn[data-secao="diagnostico-ia"]').click();
                
                setTimeout(() => {
                    $('#conteudoResultadoIA').html(dados.descricao);
                    $('#resultadoDiagnosticoIA').addClass('visible');
                    $('#btnSalvarDiagnosticoIA').hide();
                    
                    Swal.fire({
                        icon: 'info',
                        title: 'Diagnóstico IA',
                        text: 'Diagnóstico anterior carregado.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    $('html, body').animate({
                        scrollTop: $('#resultadoDiagnosticoIA').offset().top - 100
                    }, 500);
                }, 300);
            }
        }
    });
}

function calcularSimilaridade(str1, str2) {
    const longer = str1.length > str2.length ? str1 : str2;
    const shorter = str1.length > str2.length ? str2 : str1;
    if (longer.length === 0) return 1.0;
    const editDistance = levenshtein(longer, shorter);
    return (longer.length - editDistance) / parseFloat(longer.length);
}

function levenshtein(str1, str2) {
    const matrix = [];
    for (let i = 0; i <= str2.length; i++) {
        matrix[i] = [i];
    }
    for (let j = 0; j <= str1.length; j++) {
        matrix[0][j] = j;
    }
    for (let i = 1; i <= str2.length; i++) {
        for (let j = 1; j <= str1.length; j++) {
            if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
                matrix[i][j] = matrix[i - 1][j - 1];
            } else {
                matrix[i][j] = Math.min(
                    matrix[i - 1][j - 1] + 1,
                    matrix[i][j - 1] + 1,
                    matrix[i - 1][j] + 1
                );
            }
        }
    }
    return matrix[str2.length][str1.length];
}