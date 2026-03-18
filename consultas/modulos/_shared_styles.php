<style>
.faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 !important; }
    :root { 
        --fundo: #ecf0f5; --primaria: #1c329f; --sucesso: #28a745; --info: #17a2b8;
        --warning: #ffc107; --danger: #dc3545; --borda: #d2d6de;
        --sidebar-collapsed: 75px; --sidebar-expanded: 260px; --header-height: 60px;
        --transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        --ia-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { 
        font-family: 'Open Sans', sans-serif; background-color: var(--fundo); 
        font-size: 15px; color: #333; line-height: 1.6; overflow-x: hidden;
    }
    
    aside.sidebar-container { 
        position: fixed; left: 0; top: 0; height: 100vh; width: 260px; 
        z-index: 1000; background: #fff; 
        box-shadow: 2px 0 5px rgba(0,0,0,0.05); 
    }
    header.top-header { 
        position: fixed; top: 0; left: 260px; right: 0; 
        height: var(--header-height); z-index: 900; margin: 0 !important;
    }
    main.main-content { 
        margin-left: 260px; 
        padding: calc(var(--header-height) + 30px) 25px 40px; 
    }
    
    .console-grid { display: grid; grid-template-columns: 1fr 380px; gap: 25px; align-items: start; }
    @media (max-width: 1200px) { .console-grid { grid-template-columns: 1fr; } .sidebar-historico { display: none; } }
    
    .select-paciente-container { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
    .form-group { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
    label { font-size: 13px; font-weight: 600; color: #2c3e50; letter-spacing: 0.3px; }
    
    select, input, textarea { 
        padding: 12px 16px; border: 1px solid var(--borda); border-radius: 8px; 
        font-size: 16px; outline: none; background: #fff; font-family: 'Open Sans', sans-serif; 
        transition: all 0.3s ease; width: 100%;
    }
    select:focus, input:focus, textarea:focus { border-color: var(--primaria); box-shadow: 0 0 0 3px rgba(28, 50, 159, 0.1); }
    textarea { min-height: 120px; resize: vertical; }
    
    .select2-container--default .select2-selection--single { 
        height: 50px; display: flex; align-items: center; border: 1px solid var(--borda); border-radius: 8px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 50px; font-size: 15px; }
    
    .card-paciente {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px; padding: 30px; color: #fff;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3); margin-bottom: 30px;
        display: grid; grid-template-columns: 120px 1fr; gap: 25px; align-items: center;
    }
    .card-paciente.canino { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .card-paciente.felino { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
    .paciente-avatar {
        width: 120px; height: 120px; background: rgba(255,255,255,0.2); border-radius: 50%;
        display: flex; align-items: center; justify-content: center; font-size: 60px;
        backdrop-filter: blur(10px); border: 4px solid rgba(255,255,255,0.3);
    }
    .paciente-info h2 { font-size: 28px; font-weight: 800; margin-bottom: 8px; text-shadow: 2px 2px 4px rgba(0,0,0,0.1); }
    .paciente-info .subtitulo { font-size: 16px; opacity: 0.9; margin-bottom: 15px; }
    .paciente-detalhes { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 15px; }
    .detalhe-item { display: flex; align-items: center; gap: 8px; font-size: 14px; }
    .detalhe-item i { font-size: 16px; opacity: 0.8; }
    
    .tabs-navegacao { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
    .tab-btn {
        padding: 14px 24px; border: 2px solid #e9ecef; background: #fff; border-radius: 10px;
        cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s ease;
        display: flex; align-items: center; gap: 10px; color: #666;
    }
    .tab-btn:hover { border-color: var(--primaria); background: #f8f9ff; transform: translateY(-2px); }
    .tab-btn.active { background: var(--primaria); color: #fff; border-color: var(--primaria); box-shadow: 0 4px 12px rgba(28, 50, 159, 0.3); }
    .tab-btn i { font-size: 18px; }
    
    /* TAB ESPECIAL IA */
    .tab-ia {
        background: var(--ia-gradient) !important;
        color: #fff !important;
        border: none !important;
    }
    .tab-ia:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5) !important;
    }
    .tab-ia.active {
        box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6) !important;
    }
    
    .secao-conteudo { background: #fff; padding: 35px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: none; }
    .secao-conteudo.active { display: block; animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .secao-titulo { font-size: 22px; font-weight: 700; color: #2c3e50; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; display: flex; align-items: center; gap: 12px; }
    
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
    
    #editor-atendimento, #editor-receita, #editor-documentos, #editor-exame { height: 300px; border-radius: 0 0 8px 8px !important; }
    .ql-toolbar { border-radius: 8px 8px 0 0 !important; background: #f8f9fa; }
    .ql-editor { font-size: 16px !important; font-family: 'Open Sans', sans-serif !important; line-height: 1.6 !important; }
    .ql-editor.ql-blank::before { font-size: 16px !important; font-family: 'Open Sans', sans-serif !important; }
    
    .form-actions { display: flex; gap: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f0f0f0; }
    .btn-acao {
        padding: 14px 28px; border-radius: 8px; font-weight: 700; font-size: 14px;
        cursor: pointer; border: none; display: flex; align-items: center; gap: 10px;
        transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px; text-decoration: none;
    }
    .btn-salvar { background: linear-gradient(135deg, var(--sucesso) 0%, #20c997 100%); color: #fff; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3); }
    .btn-salvar:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4); }
    .btn-edicao {
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%) !important;
        color: #fff !important;
        box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4) !important;
        animation: pulseWarning 2s infinite;
    }
    @keyframes pulseWarning {
        0%, 100% { box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4); }
        50% { box-shadow: 0 6px 20px rgba(255, 152, 0, 0.6); }
    }
    .btn-cancelar { background: #f4f4f4; color: #666; border: 1px solid #ddd; }
    
    .sidebar-historico { position: sticky; top: calc(var(--header-height) + 30px); }
    .card-historico { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 25px; }
    .historico-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; font-weight: 700; font-size: 16px; display: flex; align-items: center; gap: 10px; }
    .historico-lista { max-height: 400px; overflow-y: auto; padding: 20px; }
    .historico-lista::-webkit-scrollbar { width: 8px; }
    .historico-lista::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
    .historico-lista::-webkit-scrollbar-thumb { background: #888; border-radius: 10px; }
    
    .historico-item-clicavel {
        cursor: pointer !important;
        transition: all 0.3s ease !important;
        border-radius: 8px !important;
        margin: 0 -20px !important;
        padding: 15px 20px !important;
    }
    .historico-item-clicavel:hover {
        background: #f8f9fa !important;
        transform: translateX(4px);
    }
    
    .req { color: var(--danger); }
    .bloco-exame-tipo { display: none; margin: 25px 0; animation: fadeIn 0.3s ease-out; }
    
    .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; backdrop-filter: blur(3px); }
    .modal-content { background: #fff; padding: 40px; border-radius: 12px; width: 600px; max-width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
</style>
