<?php
/**
 * ZIIPVET - PROCESSAMENTO DE EXAMES LABORATORIAIS
 * Versão atualizada para gravar na tabela específica 'exames'
 */

require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_paciente'])) {
    try {
        $id_paciente = $_POST['id_paciente'];
        $tipo_exame  = $_POST['tipo_exame'];
        $conclusoes  = $_POST['conclusoes']; // Conteúdo do editor Quill
        $usuario     = $_SESSION['usuario_nome'] ?? 'Veterinário';
        
        // 1. Processar Resultados Detalhados (Array 'res')
        // Geramos uma tabela HTML formatada para salvar na coluna resultados_detalhados
        $html_resultados = "<table border='1' style='width:100%; border-collapse: collapse;'>";
        $html_resultados .= "<tr style='background:#f4f4f4;'><th>Parâmetro</th><th>Resultado</th></tr>";
        
        $laboratorio_detectado = "";
        $data_exame_detectada = date('Y-m-d');

        if (isset($_POST['res']) && is_array($_POST['res'])) {
            foreach ($_POST['res'] as $chave => $valor) {
                if (!empty($valor)) {
                    // Captura o laboratório e a data se estiverem no array res
                    if (strpos($chave, 'lab_') !== false) $laboratorio_detectado = $valor;
                    if (strpos($chave, 'data_') !== false) $data_exame_detectada = $valor;

                    $label = ucwords(str_replace(['_', 'res['], ' ', $chave));
                    $html_resultados .= "<tr><td style='padding:5px;'>{$label}</td><td style='padding:5px;'>{$valor}</td></tr>";
                }
            }
        }
        $html_resultados .= "</table>";

        // 2. Gestão de Múltiplos Anexos
        $arquivos_salvos = [];
        if (!empty($_FILES['anexos_exame']['name'][0])) {
            $diretorio = '../uploads/exames/';
            if (!is_dir($diretorio)) mkdir($diretorio, 0777, true);

            foreach ($_FILES['anexos_exame']['tmp_name'] as $key => $tmp_name) {
                $extensao = pathinfo($_FILES['anexos_exame']['name'][$key], PATHINFO_EXTENSION);
                $novo_nome = md5(uniqid()) . "." . $extensao;
                
                if (move_uploaded_file($tmp_name, $diretorio . $novo_nome)) {
                    $arquivos_salvos[] = 'uploads/exames/' . $novo_nome;
                }
            }
        }
        $anexos_string = implode(',', $arquivos_salvos);

        // 3. Inserção na tabela 'exames'
        $sql = "INSERT INTO exames (
                    id_paciente, 
                    tipo_exame, 
                    laboratorio, 
                    data_exame, 
                    resultados_detalhados, 
                    conclusoes_finais, 
                    anexos, 
                    usuario_responsavel,
                    assinado_digitalmente
                ) VALUES (
                    :paciente, 
                    :tipo, 
                    :lab, 
                    :dt_exame, 
                    :res_detalhe, 
                    :concl, 
                    :anexos, 
                    :usuario,
                    :assinado
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':paciente'    => $id_paciente,
            ':tipo'        => $tipo_exame,
            ':lab'         => $laboratorio_detectado,
            ':dt_exame'    => $data_exame_detectada,
            ':res_detalhe' => $html_resultados,
            ':concl'       => $conclusoes,
            ':anexos'      => $anexos_string,
            ':usuario'     => $usuario,
            ':assinado'    => isset($_POST['assinar_digital']) ? 1 : 0
        ]);

        $_SESSION['msg_sucesso'] = "Exame registado com sucesso!";
        header("Location: listar_exames.php?status=sucesso");
        exit;

    } catch (PDOException $e) {
        error_log("Erro ao processar exame: " . $e->getMessage());
        die("Erro crítico ao salvar o exame: " . $e->getMessage());
    }
}