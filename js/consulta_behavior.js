/**
 * ZIIPVET - CONSULTA BEHAVIOR
 * Arquivo: js/consulta_behavior.js
 * Lógica de comportamento da página de consultas
 */

$(document).ready(function () {
    // Inicializar Select2
    $('#select_cliente').select2({
        placeholder: 'Digite para pesquisar o cliente...',
        allowClear: true,
        width: '100%'
    });

    // Ao selecionar cliente
    $('#select_cliente').on('change', function () {
        const selectedOption = $(this).find('option:selected');
        const pacientesInfo = selectedOption.data('pacientes');

        if (pacientesInfo) {
            $('#cards_pacientes').empty();

            pacientesInfo.ids.forEach((id, index) => {
                const nome = pacientesInfo.nomes[index];
                const especie = pacientesInfo.especies[index] || 'Pet';

                const icone = especie.toLowerCase().includes('felino') || especie.toLowerCase().includes('gato')
                    ? 'fa-cat'
                    : 'fa-dog';

                const card = $(`
                    <div class="card-paciente-select" data-id="${id}">
                        <div class="card-paciente-icon">
                            <i class="fas ${icone}"></i>
                        </div>
                        <div class="card-paciente-info">
                            <div class="card-paciente-nome">${nome}</div>
                            <div class="card-paciente-especie">
                                <i class="fas fa-paw" style="font-size: 11px;"></i>
                                ${especie}
                            </div>
                        </div>
                        <div class="card-paciente-check">
                            <i class="fas fa-check" style="display: none;"></i>
                        </div>
                    </div>
                `);

                card.on('click', function () {
                    // Redirecionar para a consulta do paciente
                    window.location.href = 'realizar_consulta.php?id_paciente=' + $(this).data('id');
                });

                $('#cards_pacientes').append(card);
            });

            $('#grupo_pacientes').slideDown(300);
        } else {
            $('#grupo_pacientes').slideUp(300);
        }
    });
});
