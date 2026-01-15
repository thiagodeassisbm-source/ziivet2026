<?php
/**
 * ZIIPVET - Menu Lateral (SEM CSS EMBUTIDO)
 * Versão: 2.0.0 - CSS Centralizado
 * CSS está em: css/menu.css
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    
    <div class="sidebar-header">
        <div class="logo-icon">
            <i class="fas fa-paw"></i>
        </div>
        <div class="logo-text">
            <strong>ZIIPVET</strong>
            <span class="version">v2.2.0</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        
        <a href="dashboard_principal.php" class="nav-item <?= $currentPage == 'dashboard_principal.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-home"></i></div>
            <span class="link-text">Início</span>
        </a>

        <a href="dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-th-large"></i></div>
            <span class="link-text">Dashboard</span>
        </a>

        <a href="<?= URL_BASE ?>listar_clientes.php" class="nav-item <?= $currentPage == 'listar_clientes.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-user"></i></div>
            <span class="link-text">Clientes</span>
        </a>

        <a href="<?= URL_BASE ?>listar_pacientes.php" class="nav-item <?= $currentPage == 'listar_pacientes.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-paw"></i></div>
            <span class="link-text">Pacientes</span>
        </a>

        <div class="nav-group">
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)" class="nav-item has-submenu <?= in_array($currentPage, ['listar_atendimentos.php', 'atendimento.php', 'listar_patologias.php', 'patologia.php', 'realizar_consulta.php', 'listar_exames.php', 'listar_documentos.php', 'listar_vacinas.php', 'receitas.php', 'historico.php', 'listar_diagnosticos.php']) ? 'active-parent' : '' ?>">
                <div class="icon-container"><i class="fas fa-stethoscope"></i></div>
                <span class="link-text">Consultas</span>
                <i class="fas fa-chevron-right arrow-submenu"></i>
            </a>
            <div class="submenu" style="<?= in_array($currentPage, ['listar_atendimentos.php', 'atendimento.php', 'listar_patologias.php', 'patologia.php', 'realizar_consulta.php', 'listar_exames.php', 'listar_documentos.php', 'listar_vacinas.php', 'receitas.php', 'historico.php', 'listar_diagnosticos.php']) ? 'display:block' : '' ?>">
                <a href="consultas/realizar_consulta.php" class="<?= $currentPage == 'realizar_consulta.php' ? 'active' : '' ?>">Realizar Consulta</a>
                <a href="consultas/listar_atendimentos.php" class="<?= $currentPage == 'listar_atendimentos.php' ? 'active' : '' ?>">Atendimento</a>
                <a href="consultas/listar_patologias.php" class="<?= $currentPage == 'listar_patologias.php' ? 'active' : '' ?>">Patologia</a>
                <a href="consultas/listar_exames.php" class="<?= $currentPage == 'listar_exames.php' ? 'active' : '' ?>">Exames</a>
                <a href="consultas/listar_documentos.php" class="<?= $currentPage == 'listar_documentos.php' ? 'active' : '' ?>">Documentos Modelo</a>
                <a href="consultas/listar_vacinas.php" class="<?= $currentPage == 'listar_vacinas.php' ? 'active' : '' ?>">Vacinas</a>
                <a href="consultas/receitas.php" class="<?= $currentPage == 'receitas.php' ? 'active' : '' ?>">Receitas</a>
                <a href="consultas/historico.php" class="<?= $currentPage == 'historico.php' ? 'active' : '' ?>">Histórico Geral</a>
                <a href="consultas/listar_diagnosticos.php" class="<?= $currentPage == 'listar_diagnosticos.php' ? 'active' : '' ?>">Diagnóstico IA</a>
            </div>
        </div>

        <a href="<?= URL_BASE ?>listar_agendas.php" class="nav-item <?= $currentPage == 'listar_agendas.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-calendar-alt"></i></div>
            <span class="link-text">Agenda</span>
        </a>

        <div class="nav-group">
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)" class="nav-item has-submenu <?= in_array($currentPage, ['listar_produtos.php', 'produtos.php', 'listar_compras.php', 'listar_fornecedores.php', 'estoque.php']) ? 'active-parent' : '' ?>">
                <div class="icon-container"><i class="fas fa-box-open"></i></div>
                <span class="link-text">Produtos e Serviços</span>
                <i class="fas fa-chevron-right arrow-submenu"></i>
            </a>
            <div class="submenu" style="<?= in_array($currentPage, ['listar_produtos.php', 'produtos.php', 'listar_compras.php', 'listar_fornecedores.php', 'estoque.php']) ? 'display:block' : '' ?>">
                <a href="<?= URL_BASE ?>listar_produtos.php" class="<?= $currentPage == 'listar_produtos.php' ? 'active' : '' ?>">Cadastrar</a>
                <a href="<?= URL_BASE ?>listar_compras.php" class="<?= $currentPage == 'listar_compras.php' ? 'active' : '' ?>">Compras</a>
                <a href="<?= URL_BASE ?>listar_fornecedores.php" class="<?= $currentPage == 'listar_fornecedores.php' ? 'active' : '' ?>">Fornecedores</a>
                <a href="<?= URL_BASE ?>estoque.php" class="<?= $currentPage == 'estoque.php' ? 'active' : '' ?>">Estoque</a>
            </div>
        </div>

        <div class="nav-group">
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)" class="nav-item has-submenu <?= in_array($currentPage, ['vendas.php', 'saldo-clientes.php', 'produtividade.php']) ? 'active-parent' : '' ?>">
                <div class="icon-container"><i class="fas fa-tag"></i></div>
                <span class="link-text">Vendas</span>
                <i class="fas fa-chevron-right arrow-submenu"></i>
            </a>
            <div class="submenu" style="<?= in_array($currentPage, ['vendas.php', 'saldo-clientes.php', 'produtividade.php']) ? 'display:block' : '' ?>">
                <a href="<?= URL_BASE ?>vendas.php" class="<?= $currentPage == 'vendas.php' ? 'active' : '' ?>">Ponto de Venda</a>
                <a href="<?= URL_BASE ?>saldo-clientes.php" class="<?= $currentPage == 'saldo-clientes.php' ? 'active' : '' ?>">Saldo do Cliente</a>
                 <a href="<?= URL_BASE ?>vendas/formas-recebimento.php" class="<?= $currentPage == 'vendas/formas-recebimento.php' ? 'active' : '' ?>">Forma de Recebimentos</a>
                <a href="<?= URL_BASE ?>produtividade.php" class="<?= $currentPage == 'produtividade.php' ? 'active' : '' ?>">Produtividade</a>
            </div>
        </div>

        <div class="nav-group">
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)" class="nav-item has-submenu <?= in_array($currentPage, ['lancamentos.php', 'movimentacao_caixa.php', 'abrir_caixa.php', 'listar_contas.php', 'contas.php', 'listar_contas_financeiras.php', 'contas_financeiras.php']) ? 'active-parent' : '' ?>">
                <div class="icon-container"><i class="fas fa-file-invoice-dollar"></i></div>
                <span class="link-text">Financeiro</span>
                <i class="fas fa-chevron-right arrow-submenu"></i>
            </a>
            <div class="submenu" style="<?= in_array($currentPage, ['lancamentos.php', 'movimentacao_caixa.php', 'abrir_caixa.php', 'listar_contas.php', 'contas.php', 'listar_contas_financeiras.php', 'contas_financeiras.php']) ? 'display:block' : '' ?>">
                <a href="<?= URL_BASE ?>lancamentos.php" class="<?= in_array($currentPage, ['lancamentos.php', 'abrir_caixa.php']) ? 'active' : '' ?>">Lançamentos</a>
                <a href="<?= URL_BASE ?>vendas/movimentacao_caixa.php" class="<?= in_array($currentPage, ['movimentacao_caixa.php', 'abrir_caixa.php']) ? 'active' : '' ?>">Movimentação Caixa</a>
                <a href="<?= URL_BASE ?>listar_contas.php" class="<?= ($currentPage == 'listar_contas.php' || $currentPage == 'contas.php') ? 'active' : '' ?>">Pagar Contas</a>
                <a href="<?= URL_BASE ?>listar_contas_financeiras.php" class="<?= ($currentPage == 'listar_contas_financeiras.php' || $currentPage == 'contas_financeiras.php') ? 'active' : '' ?>">Contas e Cartões</a>
            </div>
        </div>

        <a href="<?= URL_BASE ?>internacao.php" class="nav-item <?= $currentPage == 'internacao.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-star-of-life"></i></div>
            <span class="link-text">Internação</span>
        </a>

        <a href="banho_tosa.php" class="nav-item <?= $currentPage == 'banho_tosa.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-cut"></i></div>
            <span class="link-text">Banho e Tosa</span>
        </a>

        <div class="nav-group">
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)" class="nav-item has-submenu <?= in_array($currentPage, ['configuracoes.php', 'listar_usuarios.php', 'usuarios.php', 'configuracao-clinica.php', 'minha_empresa.php', 'gerenciar_numeros_autorizados.php']) ? 'active-parent' : '' ?>">
                <div class="icon-container"><i class="fas fa-cog"></i></div>
                <span class="link-text">Configurações</span>
                <i class="fas fa-chevron-right arrow-submenu"></i>
            </a>
            <div class="submenu" style="<?= in_array($currentPage, ['configuracoes.php', 'listar_usuarios.php', 'usuarios.php', 'configuracao-clinica.php', 'minha_empresa.php', 'gerenciar_numeros_autorizados.php']) ? 'display:block' : '' ?>">
                <a href="<?= URL_BASE ?>listar_usuarios.php" class="<?= ($currentPage == 'listar_usuarios.php' || $currentPage == 'usuarios.php') ? 'active' : '' ?>">Usuários</a>
                
                <div class="nav-subgroup">
                    <a href="javascript:void(0)" onclick="toggleSubmenu(this)" class="nav-item has-submenu <?= $currentPage == 'configuracao-clinica.php' ? 'active-inner' : '' ?>" style="font-size: 13px; padding-left: 10px;">
                        <i class="fas fa-file-invoice" style="margin-right: 8px; font-size: 12px;"></i> 
                        <span class="link-text">Nota Fiscal</span>
                        <i class="fas fa-chevron-right arrow-submenu" style="font-size: 10px;"></i>
                    </a>
                    <div class="submenu sub-level-2" style="<?= $currentPage == 'configuracao-clinica.php' ? 'display:block' : '' ?>">
                        <a href="<?= URL_BASE ?>nota-fiscal/configuracao-clinica.php" class="<?= $currentPage == 'configuracao-clinica.php' ? 'active' : '' ?>">Dados da Empresa</a>
                    </div>
                </div>

                <a href="<?= URL_BASE ?>app/minha_empresa.php" class="<?= $currentPage == 'minha_empresa.php' ? 'active' : '' ?>">Minha Empresa</a>
                <a href="<?= URL_BASE ?>gerenciar_numeros_autorizados.php" class="<?= $currentPage == 'gerenciar_numeros_autorizados.php' ? 'active' : '' ?>">Gerenciar Números Whatsapp</a>
            </div>
        </div>

    </nav>

    <div class="sidebar-footer">
        <a href="<?= URL_BASE ?>sair.php" class="btn-logout">
            <div class="icon-container"><i class="fas fa-sign-out-alt"></i></div>
            <span class="link-text">Sair do Sistema</span>
        </a>
    </div>

</div>

<script>
function toggleSubmenu(element) {
    const submenu = element.nextElementSibling;
    const arrow = element.querySelector('.arrow-submenu');
    
    if (submenu && submenu.classList.contains('submenu')) {
        if (submenu.style.display === 'block') {
            submenu.style.display = 'none';
            if(arrow) arrow.style.transform = 'rotate(0deg)';
            element.classList.remove('active-parent');
        } else {
            submenu.style.display = 'block';
            if(arrow) arrow.style.transform = 'rotate(90deg)';
            element.classList.add('active-parent');
        }
    }
}
</script>