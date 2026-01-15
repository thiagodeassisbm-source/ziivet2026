<?php
/**
 * ========================================================================
 * ZIIPVET - CONFIGURAÇÃO DA INTELIGÊNCIA ARTIFICIAL
 * ========================================================================
 * 
 * INSTRUÇÕES PARA OBTER A API KEY DO GOOGLE GEMINI (GRÁTIS):
 * 
 * 1. Acesse: https://makersuite.google.com/app/apikey
 * 2. Faça login com sua conta Google
 * 3. Clique em "Create API Key"
 * 4. Copie a chave gerada e cole abaixo
 * 
 * LIMITES DO PLANO GRATUITO:
 * - 60 requisições por minuto
 * - 1.500 requisições por dia
 * - Ideal para clínicas de pequeno/médio porte
 * 
 * ========================================================================
 */

// ============================================================================
// CONFIGURAÇÃO DA API - ALTERE APENAS A LINHA ABAIXO
// ============================================================================
define('GEMINI_API_KEY', 'AIzaSyCmodjOfeNBQ9U5Wlw0mm7aET3EDo2dV7w');

// ============================================================================
// CONFIGURAÇÕES AVANÇADAS (não precisa alterar)
// ============================================================================
// Use o alias 'gemini-1.5-flash' sem o 'models/' no meio da URL base
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent');
define('GEMINI_TIMEOUT', 60); // Timeout em segundos
define('IA_HABILITADA', true); // Desative para testes sem consumir API

// ============================================================================
// PROMPT BASE DO SISTEMA VETERINÁRIO
// ============================================================================
define('PROMPT_SISTEMA', '
Você é um assistente veterinário especializado em diagnóstico clínico de animais domésticos (cães e gatos).
Seu papel é auxiliar o médico veterinário fornecendo possíveis diagnósticos baseados nos sintomas apresentados.

REGRAS IMPORTANTES:
1. Sempre liste os diagnósticos em ordem de probabilidade (mais provável primeiro)
2. Para cada diagnóstico, explique brevemente o motivo
3. Sugira exames complementares quando apropriado
4. Indique se há urgência no atendimento
5. Nunca substitua a avaliação presencial do veterinário
6. Considere a espécie, raça, idade e peso do animal
7. Considere o histórico médico quando fornecido
8. Use linguagem técnica mas compreensível
9. Seja objetivo e direto nas respostas

FORMATO DE RESPOSTA:
Use o seguinte formato estruturado:

🔍 DIAGNÓSTICOS PROVÁVEIS:
[Liste os diagnósticos com probabilidade]

📋 EXAMES RECOMENDADOS:
[Liste os exames sugeridos]

💊 CONDUTA SUGERIDA:
[Orientações iniciais]

⚠️ NÍVEL DE URGÊNCIA:
[Baixo/Médio/Alto/Emergência]

📝 OBSERVAÇÕES:
[Considerações adicionais]
');

// ============================================================================
// FUNÇÃO PARA VERIFICAR SE A API ESTÁ CONFIGURADA
// ============================================================================
function iaConfigurada() {
    return IA_HABILITADA && GEMINI_API_KEY !== 'SUA_API_KEY_AQUI' && !empty(GEMINI_API_KEY);
}
?>