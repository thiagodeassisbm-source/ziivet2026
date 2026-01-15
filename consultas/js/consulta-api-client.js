/**
 * ZIIPVET - API Client para Consultas
 * Centraliza todas as chamadas AJAX relacionadas a consultas
 */

const ConsultaAPI = {
    /**
     * Busca dados completos do animal
     */
    getDadosAnimal: function (idPaciente, callback) {
        $.getJSON('../api/api_consulta.php', {
            ajax_dados_animal: 1,
            id_paciente: idPaciente
        }, callback).fail(function (jqXHR) {
            console.error('Erro ao buscar dados do animal:', jqXHR);
            if (typeof callback === 'function') {
                callback({ erro: true, message: 'Erro ao buscar dados' });
            }
        });
    },

    /**
     * Busca histórico de vacinas e lembretes
     */
    getHistoricoVacinas: function (idPaciente, callback) {
        $.getJSON('../api/api_consulta.php', {
            ajax_historico: 1,
            id_paciente: idPaciente
        }, callback).fail(function (jqXHR) {
            console.error('Erro ao buscar histórico:', jqXHR);
            if (typeof callback === 'function') {
                callback({ status: 'error', msg: 'Erro ao buscar histórico' });
            }
        });
    },

    /**
     * Busca histórico de peso (retorna HTML)
     */
    getHistoricoPeso: function (idPaciente, callback) {
        $.get('../api/api_consulta.php', {
            ajax_peso: 1,
            id_paciente: idPaciente
        }, callback).fail(function (jqXHR) {
            console.error('Erro ao buscar peso:', jqXHR);
            if (typeof callback === 'function') {
                callback('<div style="color:red;">Erro ao carregar histórico de peso</div>');
            }
        });
    }
};

// Exportar para uso global
window.ConsultaAPI = ConsultaAPI;
