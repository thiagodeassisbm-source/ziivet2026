<?php
/**
 * API - TATA ASSISTANT (VERSÃO PROFISSIONAL 4.0)
 * Gerencia histórico, finalização e base de conhecimento completa
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Inicializa histórico se não existir
if (!isset($_SESSION['tata_history'])) {
    $_SESSION['tata_history'] = [];
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'message';
$message = $input['message'] ?? '';

// --- AÇÃO: FINALIZAR CHAT ---
if ($action === 'finish') {
    $_SESSION['tata_last_history'] = $_SESSION['tata_history'];
    $_SESSION['tata_history'] = [];
    $_SESSION['tata_contexto'] = null;
    echo json_encode(['status' => 'finished']);
    exit;
}

// --- AÇÃO: RECUPERAR HISTÓRICO ---
if ($action === 'get_history') {
    echo json_encode(['history' => $_SESSION['tata_history'], 'has_previous' => !empty($_SESSION['tata_last_history'])]);
    exit;
}

// --- AÇÃO: RESTAURAR CHAT ANTERIOR ---
if ($action === 'restore_previous') {
    $_SESSION['tata_history'] = $_SESSION['tata_last_history'] ?? [];
    $_SESSION['tata_last_history'] = [];
    echo json_encode(['history' => $_SESSION['tata_history']]);
    exit;
}

// --- AÇÃO: MENSAGEM ---
if (empty($message)) {
    echo json_encode(['reply' => 'Olá! Como posso ajudar você hoje?']);
    exit;
}

// Registro no histórico (Usuário)
$_SESSION['tata_history'][] = ['side' => 'user', 'text' => $message, 'time' => date('H:i')];

$message_lower = mb_strtolower(trim($message));
$reply = "";

// --- CÉREBRO DA TATA (BASE DE CONHECIMENTO) ---

// 1. SAUDAÇÕES
if (preg_match('/\b(bom dia|boa tarde|boa noite|ola|oi|oie|hello|olá)\b/u', $message_lower, $matches)) {
    $saudacao = ucfirst($matches[0]);
    $reply = "$saudacao! Tudo bem com você? 😊 Como está sendo seu dia na clínica?";
} 
// 2. TUDO BEM?
elseif (preg_match('/\b(tudo bem|tudo bom|como vai|como voce esta)\b/u', $message_lower)) {
    $reply = "Tudo ótimo comigo! Estou aqui prontinha para te ajudar a navegar no ZiipVet. O que você quer fazer agora?";
}
// 3. PACIENTES / ANIMAIS / CADASTRO PET
elseif (strpos($message_lower, 'paciente') !== false || strpos($message_lower, 'animal') !== false || strpos($message_lower, 'pet') !== false || strpos($message_lower, 'cadastrar pet') !== false) {
    $reply = "Para **cadastrar um paciente (animal)**, existem dois caminhos: \n\n" .
             "1. Vá em **Pacientes** no menu lateral e clique no botão **'+ Novo Paciente'**. Lá você vincula o animal a um tutor e preenche os dados clínicos.\n" .
             "2. Ou, dentro do cadastro de um **Cliente**, clique na aba 'Animais' para adicionar um pet diretamente para aquele tutor. \n\n" .
             "Dica: Não esqueça de preencher a raça e a espécie para que o **Diagnóstico IA** funcione de forma mais precisa depois! 😉";
}
// 4. CLIENTES / TUTORES
elseif (strpos($message_lower, 'cliente') !== false || strpos($message_lower, 'tutor') !== false) {
    $reply = "Os **Clientes (Tutores)** podem ser gerenciados no menu **Clientes**. Você pode cadastrar novos, buscar por CPF ou até ver quem está com débitos pendentes. \n\n" .
             "Se você estiver vindo de outro sistema, eu posso te ajudar a **Importar os Clientes** via arquivo CSV! Quer saber como fazer essa importação?";
}
// 5. VENDAS / FINANCEIRO / PDV
elseif (strpos($message_lower, 'venda') !== false || strpos($message_lower, 'pdv') !== false || strpos($message_lower, 'vender') !== false || strpos($message_lower, 'pagamento') !== false) {
    $reply = "Realizar vendas no ZiipVet é muito prático! 💰 Basta acessar o menu **Vendas & PDV**. \n\n" .
             "Lá você seleciona o cliente (ou faz uma venda rápida), adiciona os produtos e escolhe a forma de pagamento. O sistema já baixa o estoque e lança no seu financeiro automaticamente. \n\n" .
             "Alguma dúvida sobre como fechar o caixa ou emitir nota fiscal?";
}
// 6. IMPORTAÇÃO DE DADOS
elseif (strpos($message_lower, 'importar') !== false || strpos($message_lower, 'csv') !== false || strpos($message_lower, 'xml') !== false) {
    if (strpos($message_lower, 'xml') !== false) {
        $reply = "O arquivo **XML** é usado para dar entrada em mercadorias! Vá em **Compras** e importe o XML da nota do seu fornecedor. Isso atualiza seu estoque e seu 'Contas a Pagar' em segundos.";
    } else {
        $reply = "Para importar o cadastro de Clientes e Animais, usamos arquivos **CSV**. Vá no menu lateral em **'Importar Dados'** e siga o passo a passo. Lembre-se: PDF não funciona direto, você deve converter para Excel/CSV antes!";
    }
}
// 7. DIAGNÓSTICO IA
elseif (strpos($message_lower, 'ia') !== false || strpos($message_lower, 'diagnóstico') !== false || strpos($message_lower, 'ajuda médica') !== false) {
    $reply = "O **Diagnóstico por IA** está disponível dentro do Console de Chat da Consulta. 🤖 Ao descrever os sintomas do animal, clique no botão de IA e eu farei uma análise baseada em literatura veterinária para te ajudar a fechar o caso clínico.";
}
// 8. QUEM É A TATA?
elseif (strpos($message_lower, 'quem é') !== false || strpos($message_lower, 'voce') !== false || strpos($message_lower, 'você') !== false) {
    $reply = "Eu sou a **Tata**, sua assistente inteligente! Fui treinada para conhecer cada função do ZiipVet. Minha missão é fazer com que você gaste menos tempo no sistema e mais tempo cuidando dos pets. ❤️";
}
// 9. AGRADECIMENTOS
elseif (preg_match('/\b(obrigado|obrigada|valeu|vlw|obg|thanks|excelente|perfeito)\b/u', $message_lower)) {
    $reply = "Disponha sempre! 😊 É um prazer ajudar você e sua clínica. Se precisar de mais qualquer coisa, estarei aqui de prontidão.";
}
// 10. FALLBACK
else {
    $reply = "Ainda estou processando essa dúvida... 🤔 No momento, posso te orientar sobre **'como cadastrar pacientes'**, **'vendas no PDV'**, **'importação de dados'** ou **'como usar a IA'**. Sobre qual desses assuntos você quer falar?";
}

// Registro no histórico (Tata)
$_SESSION['tata_history'][] = ['side' => 'bot', 'text' => $reply, 'time' => date('H:i')];

echo json_encode(['reply' => $reply]);
