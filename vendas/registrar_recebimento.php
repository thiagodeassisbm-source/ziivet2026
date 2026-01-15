<?php
/**
 * =========================================================================================
 * ZIIPVET - MÓDULO DE REGISTRO DE RECEBIMENTO
 * ARQUIVO: app/vendas/registrar_recebimento.php
 * VERSÃO: 1.0.0
 * DESCRIÇÃO: Gerencia o modal de finalização e registro de pagamentos com bandeiras e parcelas
 * =========================================================================================
 */

// Este arquivo deve ser incluído no vendas.php
// Não executar diretamente

if (!defined('VENDAS_MODULE_LOADED')) {
    die('Acesso não autorizado. Este módulo deve ser incluído pelo vendas.php');
}
?>

<!-- ========================================
     MODAL DE FINALIZAÇÃO DE VENDA/RECEBIMENTO
     ======================================== -->
<div class="modal-overlay" id="modalFinalizar">
    <div class="modal-finalizar">
        <div class="modal-header-fin">
            <h3><i class="fas fa-dollar-sign"></i> Finalizar Atendimento</h3>
            <button class="btn-close-modal" onclick="fecharModalFinalizar()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body-fin">
            <!-- INFORMAÇÕES DA VENDA -->
            <div class="info-box-os">
                <h4 id="modal_os_numero">Venda PDV</h4>
                <p id="modal_cliente_info">Cliente: --</p>
                <p id="modal_veiculo_info">Animal: --</p>
            </div>

            <!-- TOTAL ORIGINAL -->
            <div class="total-display">
                <span>Total:</span>
                <span class="valor" id="modal_total_original">R$ 0,00</span>
            </div>

            <!-- DESCONTO E ACRÉSCIMO -->
            <div class="form-row-modal">
                <div class="form-group-modal">
                    <label>Desconto (R$)</label>
                    <input type="text" id="modal_desconto" value="0,00" onkeyup="calcularTotalPagar()">
                </div>
                <div class="form-group-modal">
                    <label>Acréscimo (R$)</label>
                    <input type="text" id="modal_acrescimo" value="0,00" onkeyup="calcularTotalPagar()">
                </div>
            </div>

            <!-- TOTAL A PAGAR -->
            <div class="total-pagar-box">
                <span>Total a Pagar:</span>
                <span class="valor" id="modal_total_pagar">R$ 0,00</span>
            </div>

            <!-- FORMA DE PAGAMENTO -->
            <div class="form-group-modal full">
                <label>Forma de Pagamento *</label>
                <select id="modal_forma_pagamento" required onchange="exibirParcelas()">
                    <option value="">Selecione...</option>
                </select>
            </div>

            <!-- PARCELAS (aparece apenas para crédito) -->
            <div class="form-group-modal full" id="div_parcelas" style="display:none;">
                <label>Parcelas *</label>
                <select id="modal_parcelas" class="form-control" onchange="exibirTaxa()">
                    <option value="">Selecione a quantidade de parcelas...</option>
                </select>
            </div>

            <!-- EXIBIÇÃO DA TAXA -->
            <div id="info_taxa" style="display:none; margin-bottom: 15px;">
                <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 12px; border-radius: 10px; border-left: 4px solid #f39c12;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 13px; color: #856404; font-weight: 600;">
                            <i class="fas fa-percentage"></i> Taxa aplicada:
                        </span>
                        <span style="font-size: 16px; font-weight: 700; color: #f39c12;" id="txt_taxa">0%</span>
                    </div>
                    <div style="border-top: 1px solid rgba(0,0,0,0.1); padding-top: 8px; display: none;" id="calculo_taxa">
                        <div style="font-size: 12px; color: #856404; margin-bottom: 4px;">
                            <strong>Valor bruto:</strong> <span id="valor_bruto_display">R$ 0,00</span>
                        </div>
                        <div style="font-size: 12px; color: #dc3545; margin-bottom: 4px;">
                            <strong>(-) Taxa operadora:</strong> <span id="valor_taxa_display">R$ 0,00</span>
                        </div>
                        <div style="font-size: 13px; color: #28a745; font-weight: 700;">
                            <strong>Valor líquido a receber:</strong> <span id="valor_liquido_display">R$ 0,00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CAIXA ATIVO -->
            <div class="form-group-modal full">
                <label>Caixa Ativo *</label>
                <select id="modal_caixa_ativo" required>
                    <option value="">Selecione...</option>
                </select>
            </div>

            <!-- VALOR RECEBIDO E TROCO -->
            <div class="form-row-modal">
                <div class="form-group-modal">
                    <label>Valor Recebido</label>
                    <input type="text" id="modal_valor_recebido" value="0,00" onkeyup="calcularTroco()">
                </div>
                <div class="form-group-modal">
                    <div class="troco-display">
                        <span>Troco:</span>
                        <span class="valor-troco" id="modal_troco">R$ 0,00</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- FOOTER COM BOTÕES -->
        <div class="modal-footer-fin">
            <button class="btn-modal btn-modal-cancelar" onclick="fecharModalFinalizar()">
                Cancelar
            </button>
            <button class="btn-modal btn-modal-salvar" onclick="finalizarVendaComPagamento()">
                <i class="fas fa-check"></i> Finalizar e Salvar
            </button>
        </div>
    </div>
</div>

<!-- ========================================
     ESTILOS CSS DO MODAL
     ======================================== -->
<style>
/* Modal de Finalização */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    backdrop-filter: blur(4px);
    overflow-y: auto;
    padding: 20px 0;
}

.modal-overlay.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-finalizar {
    background: #fff;
    border-radius: 16px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease;
    margin: auto;
}

@media (max-width: 768px) {
    .modal-finalizar {
        width: 95%;
        max-height: 95vh;
    }
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header-fin {
    background: #1e40af;
    color: #fff;
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 16px 16px 0 0;
    position: sticky;
    top: 0;
    z-index: 10;
}

.modal-header-fin h3 {
    font-size: 20px;
    font-weight: 700;
    font-family: 'Exo', sans-serif;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

@media (max-width: 768px) {
    .modal-header-fin h3 {
        font-size: 18px;
    }
}

.btn-close-modal {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: #fff;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 16px;
}

.btn-close-modal:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(90deg);
}

.modal-body-fin {
    padding: 20px;
}

@media (max-width: 768px) {
    .modal-body-fin {
        padding: 15px;
    }
}

.info-box-os {
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    padding: 14px;
    border-radius: 12px;
    margin-bottom: 18px;
    border-left: 4px solid #28a745;
}

.info-box-os h4 {
    font-size: 16px;
    font-weight: 700;
    color: #2e7d32;
    font-family: 'Exo', sans-serif;
    margin-bottom: 6px;
}

.info-box-os p {
    font-size: 13px;
    color: #388e3c;
    margin: 3px 0;
}

.total-display {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 0;
    border-bottom: 2px solid #f0f0f0;
    margin-bottom: 18px;
}

.total-display span {
    font-size: 15px;
    font-weight: 600;
    color: #6c757d;
}

.total-display .valor {
    font-size: 24px;
    font-weight: 700;
    color: #28a745;
    font-family: 'Exo', sans-serif;
}

@media (max-width: 768px) {
    .total-display .valor {
        font-size: 20px;
    }
}

.form-row-modal {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 14px;
}

@media (max-width: 768px) {
    .form-row-modal {
        grid-template-columns: 1fr;
        gap: 12px;
    }
}

.form-group-modal {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group-modal.full {
    grid-column: span 2;
}

@media (max-width: 768px) {
    .form-group-modal.full {
        grid-column: span 1;
    }
}

.form-group-modal label {
    font-size: 11px;
    font-weight: 700;
    color: #1e40af;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-family: 'Exo', sans-serif;
}

.form-group-modal input,
.form-group-modal select {
    height: 42px;
    padding: 0 12px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-group-modal input:focus,
.form-group-modal select:focus {
    border-color: #1e40af;
    box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
    outline: none;
}

.total-pagar-box {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 18px;
    text-align: right;
}

.total-pagar-box span {
    display: block;
    font-size: 13px;
    color: #1e40af;
    margin-bottom: 4px;
    font-weight: 600;
}

.total-pagar-box .valor {
    font-size: 30px;
    font-weight: 700;
    color: #1e40af;
    font-family: 'Exo', sans-serif;
}

@media (max-width: 768px) {
    .total-pagar-box .valor {
        font-size: 26px;
    }
}

.troco-display {
    text-align: right;
    font-size: 14px;
    color: #6c757d;
    margin-top: 10px;
}

.troco-display .valor-troco {
    font-size: 24px;
    font-weight: 700;
    color: #28a745;
    display: block;
    margin-top: 4px;
    font-family: 'Exo', sans-serif;
}

@media (max-width: 768px) {
    .troco-display .valor-troco {
        font-size: 20px;
    }
}

.modal-footer-fin {
    padding: 16px 20px;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    border-radius: 0 0 16px 16px;
    position: sticky;
    bottom: 0;
    z-index: 10;
}

@media (max-width: 768px) {
    .modal-footer-fin {
        flex-direction: column;
        padding: 12px 15px;
    }
}

.btn-modal {
    flex: 1;
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    font-family: 'Exo', sans-serif;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-modal-cancelar {
    background: #e0e0e0;
    color: #6c757d;
}

.btn-modal-cancelar:hover {
    background: #d0d0d0;
}

.btn-modal-salvar {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.btn-modal-salvar:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4);
}
</style>

<!-- ========================================
     JAVASCRIPT DO MODAL DE RECEBIMENTO
     ======================================== -->
<script>
// ✅ VARIÁVEL GLOBAL COM DADOS DAS FORMAS DE PAGAMENTO
const formasPagamentoData = <?= json_encode($formas_pagamento) ?>;
const caixasAtivosData = <?= json_encode($caixas_ativos) ?>;

/**
 * Abre o modal de finalização e popula os dados
 */
function abrirModalFinalizar() {
    // Verificar se há caixas ativos
    if (!caixasAtivosData || caixasAtivosData.length === 0) {
        Swal.fire({
            title: 'Atenção - Caixa Necessário',
            html: `
                <div style="text-align: center; padding: 20px 0;">
                    <i class="fas fa-cash-register" style="font-size: 64px; color: #f39c12; margin-bottom: 20px;"></i>
                    <p style="font-size: 16px; color: #555; margin-bottom: 20px;">
                        Para registrar o recebimento desta venda, é necessário ter um <strong>caixa aberto</strong>.
                    </p>
                    <p style="font-size: 14px; color: #888;">
                        Clique no botão abaixo para abrir um caixa agora.
                    </p>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#1e40af',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-cash-register"></i> Abrir Caixa Agora',
            cancelButtonText: 'Cancelar',
            customClass: {
                popup: 'swal-wide',
                confirmButton: 'swal-btn-large'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'abrir_caixa.php';
            }
        });
        return;
    }
    
    // Calcular total geral dos itens
    const totalGeral = itensVenda.reduce((acc, item) => acc + item.total, 0);
    const clienteNome = $('#sel_cliente option:selected').text() || 'Consumidor Final';
    const animalNome = $('#sel_animal option:selected').text() || 'Não informado';

    // Preencher informações da venda
    $('#modal_os_numero').text('Venda PDV');
    $('#modal_cliente_info').text('Cliente: ' + clienteNome);
    $('#modal_veiculo_info').text('Animal: ' + animalNome);
    $('#modal_total_original').text('R$ ' + totalGeral.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
    $('#modal_total_pagar').text('R$ ' + totalGeral.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
    
    // Resetar campos
    $('#modal_desconto').val('0,00');
    $('#modal_acrescimo').val('0,00');
    $('#modal_valor_recebido').val(totalGeral.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
    $('#modal_troco').text('R$ 0,00');

    // ✅ POPULAR FORMAS DE PAGAMENTO COM BANDEIRAS
    $('#modal_forma_pagamento').html('<option value="">Selecione...</option>');
    formasPagamentoData.forEach((fp, index) => {
        $('#modal_forma_pagamento').append(`<option value="${index}" data-nome="${fp.nome_forma}">${fp.nome_forma}</option>`);
    });

    // ✅ POPULAR CAIXAS ATIVOS
    $('#modal_caixa_ativo').html('<option value="">Selecione...</option>');
    caixasAtivosData.forEach(cx => {
        $('#modal_caixa_ativo').append(`<option value="${cx.id}">Caixa #${cx.id} - ${cx.usuario_nome}</option>`);
    });

    // Ocultar campos de parcelas e taxa inicialmente
    $('#div_parcelas').hide();
    $('#info_taxa').hide();

    // Exibir modal
    $('#modalFinalizar').addClass('show');
}

/**
 * Fecha o modal de finalização
 */
function fecharModalFinalizar() {
    $('#modalFinalizar').removeClass('show');
}

/**
 * Exibe o campo de parcelas quando uma forma de pagamento é selecionada
 */
function exibirParcelas() {
    const formaIndex = $('#modal_forma_pagamento').val();
    
    // Limpar seleção de parcelas e ocultar taxa
    $('#modal_parcelas').html('<option value="">Selecione a quantidade de parcelas...</option>');
    $('#div_parcelas').hide();
    $('#info_taxa').hide();
    
    if (!formaIndex) return;
    
    const formaSelecionada = formasPagamentoData[formaIndex];
    
    // Se for crédito, mostrar parcelas
    if (formaSelecionada.tipo_bandeira === 'Crédito') {
        $('#div_parcelas').show();
        
        // Popular opções de parcelas
        const maxParcelas = formaSelecionada.max_parcelas || 1;
        const parcelas = formaSelecionada.parcelas || {};
        
        for (let i = 1; i <= maxParcelas; i++) {
            let taxaKey = i === 1 ? 'avista' : `p${i}`;
            let taxa = parcelas[taxaKey] || 'N/A';
            let label = i === 1 ? 'À vista' : `${i}x`;
            
            $('#modal_parcelas').append(`<option value="${i}" data-taxa="${taxa}">${label} - Taxa: ${taxa}</option>`);
        }
    } else if (formaSelecionada.tipo_bandeira === 'Débito') {
        // Para débito, exibir a taxa diretamente
        const taxaDebito = formaSelecionada.parcelas.debito || 'N/A';
        $('#txt_taxa').text(taxaDebito);
        $('#info_taxa').show();
        
        // ✅ CALCULAR E MOSTRAR VALOR LÍQUIDO PARA DÉBITO
        const totalPagar = parseFloat($('#modal_total_pagar').text().replace('R$', '').replace(/\./g, '').replace(',', '.'));
        const percentualTaxa = parseFloat(taxaDebito.replace('%', '').replace(',', '.'));
        
        if (percentualTaxa > 0) {
            const valorTaxa = (totalPagar * percentualTaxa) / 100;
            const valorLiquido = totalPagar - valorTaxa;
            
            $('#valor_bruto_display').text('R$ ' + totalPagar.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
            $('#valor_taxa_display').text('R$ ' + valorTaxa.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
            $('#valor_liquido_display').text('R$ ' + valorLiquido.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
            $('#calculo_taxa').show();
        } else {
            $('#calculo_taxa').hide();
        }
    }
}

/**
 * Exibe a taxa quando uma parcela é selecionada
 */
function exibirTaxa() {
    const parcelaSelecionada = $('#modal_parcelas option:selected');
    const taxa = parcelaSelecionada.data('taxa');
    
    if (taxa && taxa !== 'N/A') {
        $('#txt_taxa').text(taxa);
        $('#info_taxa').show();
        
        // ✅ CALCULAR E MOSTRAR VALOR LÍQUIDO
        const totalPagar = parseFloat($('#modal_total_pagar').text().replace('R$', '').replace(/\./g, '').replace(',', '.'));
        
        // Extrair porcentagem (ex: "4%" -> 4)
        const percentualTaxa = parseFloat(taxa.replace('%', '').replace(',', '.'));
        
        if (percentualTaxa > 0) {
            const valorTaxa = (totalPagar * percentualTaxa) / 100;
            const valorLiquido = totalPagar - valorTaxa;
            
            $('#valor_bruto_display').text('R$ ' + totalPagar.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
            $('#valor_taxa_display').text('R$ ' + valorTaxa.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
            $('#valor_liquido_display').text('R$ ' + valorLiquido.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
            $('#calculo_taxa').show();
        } else {
            $('#calculo_taxa').hide();
        }
    } else {
        $('#info_taxa').hide();
    }
}

/**
 * Calcula o total a pagar com desconto e acréscimo
 */
function calcularTotalPagar() {
    const totalOriginal = itensVenda.reduce((acc, item) => acc + item.total, 0);
    const desconto = parseFloat($('#modal_desconto').val().replace('.','').replace(',','.')) || 0;
    const acrescimo = parseFloat($('#modal_acrescimo').val().replace('.','').replace(',','.')) || 0;
    
    const totalPagar = totalOriginal - desconto + acrescimo;
    $('#modal_total_pagar').text('R$ ' + totalPagar.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
    
    $('#modal_valor_recebido').val(totalPagar.toLocaleString('pt-BR', {minimumFractionDigits: 2}));
    calcularTroco();
}

/**
 * Calcula o troco
 */
function calcularTroco() {
    const totalPagar = parseFloat($('#modal_total_pagar').text().replace('R$', '').replace('.','').replace(',','.'));
    const valorRecebido = parseFloat($('#modal_valor_recebido').val().replace('.','').replace(',','.')) || 0;
    
    const troco = valorRecebido - totalPagar;
    $('#modal_troco').text('R$ ' + Math.max(0, troco).toLocaleString('pt-BR', {minimumFractionDigits: 2}));
}

/**
 * Finaliza a venda com pagamento
 */
async function finalizarVendaComPagamento() {
    const formaIndex = $('#modal_forma_pagamento').val();
    const caixaAtivo = $('#modal_caixa_ativo').val();

    // Validações
    if(!formaIndex) {
        Swal.fire({
            title: 'Atenção',
            text: 'Selecione a forma de pagamento',
            icon: 'warning',
            confirmButtonColor: '#1e40af'
        });
        return;
    }

    if(!caixaAtivo) {
        Swal.fire({
            title: 'Atenção',
            text: 'Selecione o caixa ativo',
            icon: 'warning',
            confirmButtonColor: '#1e40af'
        });
        return;
    }

    const formaSelecionada = formasPagamentoData[formaIndex];
    const nomeFormaPagamento = formaSelecionada.nome_forma;
    const formaId = formaSelecionada.id_forma_base;

    // ✅ CAPTURAR DADOS DE PARCELAMENTO
    let qtdParcelas = 1;
    let taxaAplicada = '0%';
    
    if (formaSelecionada.tipo_bandeira === 'Crédito') {
        const parcelaSelect = $('#modal_parcelas').val();
        if (!parcelaSelect) {
            Swal.fire({
                title: 'Atenção',
                text: 'Selecione a quantidade de parcelas',
                icon: 'warning',
                confirmButtonColor: '#1e40af'
            });
            return;
        }
        qtdParcelas = parseInt(parcelaSelect);
        taxaAplicada = $('#modal_parcelas option:selected').data('taxa') || '0%';
    } else if (formaSelecionada.tipo_bandeira === 'Débito') {
        taxaAplicada = formaSelecionada.parcelas.debito || '0%';
    }

    // Calcular valores
    const totalOriginal = itensVenda.reduce((acc, item) => acc + item.total, 0);
    const desconto = parseFloat($('#modal_desconto').val().replace('.','').replace(',','.')) || 0;
    const acrescimo = parseFloat($('#modal_acrescimo').val().replace('.','').replace(',','.')) || 0;
    const totalPagar = totalOriginal - desconto + acrescimo;

    // Montar dados para envio
    const dados = {
        acao_btn: 'receber',
        id_cliente: $('#sel_cliente').val(),
        id_paciente: $('#sel_animal').val(),
        data: $('#data_venda').val(),
        tipo: $('#tipo_movimento').val(),
        tipo_venda: $('#tipo_venda_select').val(),
        obs: $('#obs_venda').val(),
        itens: itensVenda,
        total_geral: totalPagar,
        desconto: desconto,
        acrescimo: acrescimo,
        forma_pagamento: formaId,
        nome_forma_pagamento: nomeFormaPagamento, // ✅ NOME DA BANDEIRA
        qtd_parcelas: qtdParcelas, // ✅ QUANTIDADE DE PARCELAS
        taxa_aplicada: taxaAplicada, // ✅ TAXA APLICADA
        caixa_ativo: caixaAtivo
    };

    // Preparar FormData para envio
    const formData = new FormData();
    formData.append('acao', 'salvar_venda');
    formData.append('dados_venda', JSON.stringify(dados));
    
    // ✅ INCLUIR TOKEN CSRF
    let csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
    if (!csrfToken) {
        csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    }
    
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }

    try {
        const res = await fetch('vendas.php', { method: 'POST', body: formData });
        const resposta = await res.json();
        
        fecharModalFinalizar();
        
        if(resposta.status === 'success') {
            Swal.fire({
                title: 'Sucesso!',
                text: resposta.message,
                icon: 'success',
                confirmButtonColor: '#1e40af',
                confirmButtonText: 'OK',
                allowOutsideClick: false
            }).then(() => location.reload());
        } else {
            Swal.fire({
                title: 'Erro',
                text: resposta.message,
                icon: 'error',
                confirmButtonColor: '#1e40af'
            });
        }
    } catch(e) {
        fecharModalFinalizar();
        Swal.fire({
            title: 'Erro',
            text: 'Erro de conexão ao processar o pagamento',
            icon: 'error',
            confirmButtonColor: '#1e40af'
        });
    }
}
</script>