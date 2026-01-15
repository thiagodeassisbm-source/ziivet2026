<?php
/**
 * ZIIPVET - PROCESSAMENTO DE DOCUMENTOS
 * Salva o HTML gerado pelo editor no banco de dados.
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $id_paciente    = isset($_POST['id_paciente']) ? (int)$_POST['id_paciente'] : 0;
        $tipo_documento = isset($_POST['tipo_documento']) ? $_POST['tipo_documento'] : '';
        $conteudo_html  = isset($_POST['conteudo_html']) ? $_POST['conteudo_html'] : '';
        $usuario        = $_SESSION['usuario_nome'] ?? 'Veterinário';

        if ($id_paciente === 0 || empty($tipo_documento) || empty($conteudo_html)) {
            echo json_encode(['status' => 'error', 'message' => 'Dados incompletos para salvar.']);
            exit;
        }

        $sql = "INSERT INTO documentos_emitidos (id_paciente, tipo_documento, conteudo_html, usuario_emissor, data_emissao) 
                VALUES (:id_p, :tipo, :conteudo, :usuario, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_p'     => $id_paciente,
            ':tipo'     => $tipo_documento,
            ':conteudo' => $conteudo_html,
            ':usuario'  => $usuario
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Documento gravado com sucesso no histórico!']);

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método inválido.']);
}