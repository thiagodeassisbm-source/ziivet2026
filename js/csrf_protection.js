/**
 * ZIIPVET - Proteção CSRF Global
 * VERSION: 2026-03-16-v7-FINAL-FIX
 * 
 * Este script intercepta as requisições AJAX (jQuery) e Fetch API
 * para adicionar o cabeçalho X-CSRF-Token automaticamente.
 */

(function() {
    // 1. Interceptação para Fetch API (Independente de biblioteca)
    const originalFetch = window.fetch;
    window.fetch = function (url, options = {}) {
        const method = (options.method || 'GET').toUpperCase();
        
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
            // Busca o token na meta tag usando vanilla JS
            const meta = document.querySelector('meta[name="csrf-token"]');
            const token = meta ? meta.getAttribute('content') : null;

            if (token) {
                options.headers = options.headers || {};

                if (options.headers instanceof Headers) {
                    if (!options.headers.has('X-CSRF-Token')) {
                        options.headers.append('X-CSRF-Token', token);
                    }
                } else {
                    options.headers['X-CSRF-Token'] = token;
                }
            }
        }
        return originalFetch(url, options);
    };

    // 2. Interceptação para jQuery AJAX (Apenas se jQuery estiver presente)
    const initJQueryCsrf = function() {
        if (typeof jQuery !== 'undefined') {
            jQuery.ajaxSetup({
                beforeSend: function (xhr, settings) {
                    if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(settings.type.toUpperCase())) {
                        const token = jQuery('meta[name="csrf-token"]').attr('content');
                        if (token) {
                            xhr.setRequestHeader('X-CSRF-Token', token);
                        }
                    }
                }
            });
        }
    };

    // Tenta inicializar jQuery imediatamente ou quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initJQueryCsrf);
    } else {
        initJQueryCsrf();
    }
})();
