<?php
/**
 * ZIIPVET - Menu Lateral Standardizado
 * Versão: 3.0.0 - Design Premium & Bulletproof
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = dirname($_SERVER['PHP_SELF']);
$isInConsultas = (strpos($_SERVER['REQUEST_URI'], '/consultas/') !== false);
?>

<div class="sidebar">
    
    <div class="sidebar-header">
        <div class="logo-icon">
            <i class="fas fa-paw"></i>
        </div>
        <div class="logo-text">
            <strong>ZIIPVET</strong>
            <span class="version">PLATFORM v3.0</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        
        <!-- INÍCIO / DASHBOARD -->
        <a href="<?= URL_BASE ?>dashboard_principal.php" class="nav-item <?= $currentPage == 'dashboard_principal.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-home"></i></div>
            <span class="link-text">Início</span>
        </a>

        <a href="<?= URL_BASE ?>dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-chart-pie"></i></div>
            <span class="link-text">Dashboard</span>
        </a>

        <!-- CADASTROS BASE -->
        <a href="<?= URL_BASE ?>listar_clientes.php" class="nav-item <?= $currentPage == 'listar_clientes.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-user-friends"></i></div>
            <span class="link-text">Clientes</span>
        </a>

        <a href="<?= URL_BASE ?>listar_pacientes.php" class="nav-item <?= $currentPage == 'listar_pacientes.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-dog"></i></div>
            <span class="link-text">Pacientes</span>
        </a>

        <!-- OPERACIONAL / CONSULTAS -->
        <div class="nav-group">
            <?php 
                $consultas_pages = ['realizar_consulta.php', 'listar_diagnosticos.php'];
                $isConsultasActive = in_array($currentPage, $consultas_pages) || $isInConsultas;
            ?>
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)" class="nav-item has-submenu <?= $isConsultasActive ? 'active-parent' : '' ?>">
                <div class="icon-container"><i class="fas fa-stethoscope"></i></div>
                <span class="link-text">Consultas</span>
                <i class="fas fa-chevron-right arrow-submenu"></i>
            </a>
            <div class="submenu" style="<?= $isConsultasActive ? 'display:block' : '' ?>">
                <a href="<?= URL_BASE ?>consultas/realizar_consulta.php" class="<?= $currentPage == 'realizar_consulta.php' ? 'active' : '' ?>">Realizar Consulta</a>
                <a href="<?= URL_BASE ?>consultas/listar_diagnosticos.php" class="<?= $currentPage == 'listar_diagnosticos.php' ? 'active' : '' ?>">Diagnóstico IA</a>
            </div>
        </div>

        <a href="<?= URL_BASE ?>listar_agendas.php" class="nav-item <?= $currentPage == 'listar_agendas.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-calendar-check"></i></div>
            <span class="link-text">Agenda</span>
        </a>

        <!-- ESTOQUE E PRODUTOS -->
        <div class="nav-group">
            <?php 
                $produtos_pages = ['listar_produtos.php', 'produtos.php', 'listar_compras.php', 'listar_fornecedores.php', 'estoque.php'];
                $isProdutosActive = in_array($currentPage, $produtos_pages);
            ?>
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)" class="nav-item has-submenu <?= $isProdutosActive ? 'active-parent' : '' ?>">
                <div class="icon-container"><i class="fas fa-boxes"></i></div>
                <span class="link-text">Produtos e Estoque</span>
                <i class="fas fa-chevron-right arrow-submenu"></i>
            </a>
            <div class="submenu" style="<?= $isProdutosActive ? 'display:block' : '' ?>">
                <a href="<?= URL_BASE ?>listar_produtos.php" class="<?= ($currentPage == 'listar_produtos.php' || $currentPage == 'produtos.php') ? 'active' : '' ?>">Catálogo</a>
                <a href="<?= URL_BASE ?>listar_compras.php" class="<?= $currentPage == 'listar_compras.php' ? 'active' : '' ?>">Entradas / Compras</a>
                <a href="<?= URL_BASE ?>listar_fornecedores.php" class="<?= $currentPage == 'listar_fornecedores.php' ? 'active' : '' ?>">Fornecedores</a>
                <a href="<?= URL_BASE ?>estoque.php" class="<?= $currentPage == 'estoque.php' ? 'active' : '' ?>">Posição de Estoque</a>
            </div>
        </div>

        <!-- COMERCIAL / VENDAS -->
        <div class="nav-group">
            <?php 
                $vendas_pages = ['vendas.php', 'saldo-clientes.php', 'produtividade.php', 'formas-recebimento.php'];
                $isVendasActive = in_array($currentPage, $vendas_pages);
            ?>
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)" class="nav-item has-submenu <?= $isVendasActive ? 'active-parent' : '' ?>">
                <div class="icon-container"><i class="fas fa-cash-register"></i></div>
                <span class="link-text">Vendas & PDV</span>
                <i class="fas fa-chevron-right arrow-submenu"></i>
            </a>
            <div class="submenu" style="<?= $isVendasActive ? 'display:block' : '' ?>">
                <a href="<?= URL_BASE ?>vendas.php" class="<?= $currentPage == 'vendas.php' ? 'active' : '' ?>">Ponto de Venda</a>
                <a href="<?= URL_BASE ?>saldo-clientes.php" class="<?= $currentPage == 'saldo-clientes.php' ? 'active' : '' ?>">Créditos Clientes</a>
                <a href="<?= URL_BASE ?>vendas/formas-recebimento.php" class="<?= $currentPage == 'formas-recebimento.php' ? 'active' : '' ?>">Formas de Pagto</a>
                <a href="<?= URL_BASE ?>produtividade.php" class="<?= $currentPage == 'produtividade.php' ? 'active' : '' ?>">Produtividade</a>
            </div>
        </div>

        <!-- FINANCEIRO -->
        <div class="nav-group">
            <?php 
                $financeiro_pages = ['lancamentos.php', 'movimentacao_caixa.php', 'abrir_caixa.php', 'listar_contas.php', 'contas.php', 'listar_contas_financeiras.php', 'contas_financeiras.php'];
                $isFinanceiroActive = in_array($currentPage, $financeiro_pages);
            ?>
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)" class="nav-item has-submenu <?= $isFinanceiroActive ? 'active-parent' : '' ?>">
                <div class="icon-container"><i class="fas fa-file-invoice-dollar"></i></div>
                <span class="link-text">Financeiro</span>
                <i class="fas fa-chevron-right arrow-submenu"></i>
            </a>
            <div class="submenu" style="<?= $isFinanceiroActive ? 'display:block' : '' ?>">
                <a href="<?= URL_BASE ?>lancamentos.php" class="<?= in_array($currentPage, ['lancamentos.php', 'abrir_caixa.php']) ? 'active' : '' ?>">Fluxo de Caixa</a>
                <a href="<?= URL_BASE ?>vendas/movimentacao_caixa.php" class="<?= in_array($currentPage, ['movimentacao_caixa.php']) ? 'active' : '' ?>">Movimentação PDV</a>
                <a href="<?= URL_BASE ?>listar_contas.php" class="<?= ($currentPage == 'listar_contas.php' || $currentPage == 'contas.php') ? 'active' : '' ?>">Contas a Pagar</a>
                <a href="<?= URL_BASE ?>listar_contas_financeiras.php" class="<?= ($currentPage == 'listar_contas_financeiras.php' || $currentPage == 'contas_financeiras.php') ? 'active' : '' ?>">Bancos e Cartões</a>
            </div>
        </div>

        <a href="<?= URL_BASE ?>internacao.php" class="nav-item <?= $currentPage == 'internacao.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-clinic-medical"></i></div>
            <span class="link-text">Internação</span>
        </a>

        <a href="<?= URL_BASE ?>banho_tosa.php" class="nav-item <?= $currentPage == 'banho_tosa.php' ? 'active' : '' ?>">
            <div class="icon-container"><i class="fas fa-cut"></i></div>
            <span class="link-text">Pet Shop / Estética</span>
        </a>

        <!-- CONFIGURAÇÕES -->
        <div class="nav-group">
            <?php 
                $config_pages = ['configuracoes.php', 'listar_usuarios.php', 'usuarios.php', 'configuracao-clinica.php', 'minha_empresa.php', 'gerenciar_numeros_autorizados.php'];
                $isConfigActive = in_array($currentPage, $config_pages);
            ?>
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)" class="nav-item has-submenu <?= $isConfigActive ? 'active-parent' : '' ?>">
                <div class="icon-container"><i class="fas fa-user-cog"></i></div>
                <span class="link-text">Configurações</span>
                <i class="fas fa-chevron-right arrow-submenu"></i>
            </a>
            <div class="submenu" style="<?= $isConfigActive ? 'display:block' : '' ?>">
                <a href="<?= URL_BASE ?>listar_usuarios.php" class="<?= ($currentPage == 'listar_usuarios.php' || $currentPage == 'usuarios.php') ? 'active' : '' ?>">Equipe e Permissões</a>
                <a href="<?= URL_BASE ?>app/minha_empresa.php" class="<?= $currentPage == 'minha_empresa.php' ? 'active' : '' ?>">Dados do Perfil</a>
                <a href="<?= URL_BASE ?>nota-fiscal/configuracao-clinica.php" class="<?= $currentPage == 'configuracao-clinica.php' ? 'active' : '' ?>">Nota Fiscal (NFe)</a>
                <a href="<?= URL_BASE ?>gerenciar_numeros_autorizados.php" class="<?= $currentPage == 'gerenciar_numeros_autorizados.php' ? 'active' : '' ?>">WhatsApp API</a>
            </div>
        </div>

    </nav>

    <div class="sidebar-footer">
        <a href="<?= URL_BASE ?>sair.php" class="btn-logout">
            <div class="icon-container"><i class="fas fa-power-off"></i></div>
            <span class="link-text">Sair da Plataforma</span>
        </a>
    </div>

</div>

<script>
/**
 * Alterna a visibilidade dos submenus com animação suave via JS
 */
function toggleSubmenu(element) {
    const submenu = element.nextElementSibling;
    const isVisible = submenu.style.display === 'block';
    
    // Fechar outros submenus abertos (Opcional, mas mantém limpo)
    // document.querySelectorAll('.submenu').forEach(s => s.style.display = 'none');
    // document.querySelectorAll('.has-submenu').forEach(i => i.classList.remove('active-parent'));

    if (isVisible) {
        submenu.style.display = 'none';
        element.classList.remove('active-parent');
    } else {
        submenu.style.display = 'block';
        element.classList.add('active-parent');
    }
}
</script>
