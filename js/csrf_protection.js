/**
 * ZIIPVET - Proteção CSRF Global para jQuery AJAX
 * 
 * Este script intercepta todas as requisições AJAX do jQuery e adiciona
 * o cabeçalho X-CSRF-Token automaticamente, lendo o valor da meta tag 'csrf-token'.
 */

$(document).ready(function () {
    // Configuração global para jQuery AJAX
    $.ajaxSetup({
        beforeSend: function (xhr, settings) {
            // Apenas para métodos que podem alterar estado no servidor
            if (settings.type === 'POST' || settings.type === 'PUT' || settings.type === 'DELETE' || settings.type === 'PATCH') {
                const token = $('meta[name="csrf-token"]').attr('content');
                if (token) {
                    xhr.setRequestHeader('X-CSRF-Token', token);
                }
            }
        }
    });

    // Alternativa usando ajaxSend para garantir captura de todas as chamadas
    $(document).ajaxSend(function (event, xhr, settings) {
        if (settings.type === 'POST' || settings.type === 'PUT' || settings.type === 'DELETE' || settings.type === 'PATCH') {
            const token = $('meta[name="csrf-token"]').attr('content');
            if (token) {
                xhr.setRequestHeader('X-CSRF-Token', token);
            }
        }
    });
});
