<script>
// ============================================================================
// VARIÁVEIS GLOBAIS
// ============================================================================
var idPacienteAtual = <?= $dados_paciente['id'] ?? 0 ?>;
var quillAtendimento, quillReceita, quillDocumentos, quillExame;

$(document).ready(function() {
    // Select2
    $('#select_paciente').select2({ placeholder: "Pesquise...", width: '100%' });

    // Navegação por tabs
    $('.tab-btn').on('click', function() {
        const secao = $(this).data('secao');
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.secao-conteudo').removeClass('active');
        $(`.secao-conteudo[data-secao="${secao}"]`).addClass('active');
    });
});

// ============================================================================
// EDITORES QUILL
// ============================================================================
quillAtendimento = new Quill('#editor-atendimento', {
    theme: 'snow', placeholder: 'Anamnese, conduta...',
    modules: { toolbar: [[{'header': [1, 2, false]}], ['bold', 'italic', 'underline'], [{'list': 'ordered'}, {'list': 'bullet'}], ['clean']] }
});

quillReceita = new Quill('#editor-receita', {
    theme: 'snow', placeholder: 'Prescrição...',
    modules: { toolbar: [['bold', 'italic', 'underline'], [{'list': 'ordered'}, {'list': 'bullet'}], ['clean']] }
});

quillDocumentos = new Quill('#editor-documentos', {
    theme: 'snow', placeholder: 'Selecione um modelo de documento...',
    modules: { toolbar: [[{'header': [1, 2, false]}], ['bold', 'italic', 'underline'], [{'list': 'ordered'}, {'list': 'bullet'}], [{'align': []}], ['clean']] }
});

quillExame = new Quill('#editor-exame', {
    theme: 'snow', placeholder: 'Escreva as conclusões/laudo do exame...',
    modules: { toolbar: [[{'header': [1, 2, false]}], ['bold', 'italic', 'underline'], [{'list': 'ordered'}, {'list': 'bullet'}], ['clean']] }
});

// Expor globalmente
window.quillAtendimento = quillAtendimento;
window.quillReceita = quillReceita;
window.quillDocumentos = quillDocumentos;
window.quillExame = quillExame;

// ============================================================================
// FUNÇÕES AUXILIARES
// ============================================================================
function alternarBlocoExame(tipo) {
    document.querySelectorAll('.bloco-exame-tipo').forEach(div => div.style.display = 'none');
    if (tipo) {
        const target = document.getElementById('bloco-' + tipo);
        if (target) target.style.display = 'block';
    }
}

function aplicarModelo(select) { 
    if(select.value) quillReceita.root.innerHTML = select.value; 
}

function abrirModalModelo() { 
    document.getElementById('modalModelo').style.display = 'flex'; 
}

function fecharModalModelo() { 
    document.getElementById('modalModelo').style.display = 'none'; 
}

window.onclick = function(event) {
    let modal = document.getElementById('modalModelo');
    if (event.target == modal) fecharModalModelo();
}

// ============================================================================
// MENSAGENS DE SESSÃO
// ============================================================================
<?php if (isset($_SESSION['msg_sucesso'])): ?>
    Swal.fire({ icon: 'success', title: 'Sucesso!', text: '<?= addslashes($_SESSION['msg_sucesso']) ?>', confirmButtonColor: '#28a745' });
    <?php unset($_SESSION['msg_sucesso']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['msg_erro'])): ?>
    Swal.fire({ icon: 'error', title: 'Erro', text: '<?= addslashes($_SESSION['msg_erro']) ?>', confirmButtonColor: '#dc3545' });
    <?php unset($_SESSION['msg_erro']); ?>
<?php endif; ?>
</script>
