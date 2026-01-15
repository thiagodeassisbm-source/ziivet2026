function toggleMenu() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    
    // Para Desktop: Colapsar/Expandir
    if (window.innerWidth > 768) {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
    } else {
        // Para Mobile: Abrir/Fechar lateralmente
        sidebar.classList.toggle('mobile-open');
    }
}