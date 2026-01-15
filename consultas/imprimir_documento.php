<?php
/**
 * ZIIPVET - MOTOR DE IMPRESSÃO DE DOCUMENTOS
 * Formata laudos, atestados e termos para papel A4 (2 Vias)
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. VERIFICAÇÃO DE SEGURANÇA E DADOS
if (!isset($_GET['id'])) {
    die("ID do documento não fornecido.");
}

$id_doc = (int)$_GET['id'];

try {
    // Busca o documento e os dados completos do pet e tutor
    $sql = "SELECT a.*, p.nome_paciente, p.especie, p.raca, p.sexo, p.data_nascimento, p.pelagem, p.chip,
                   c.nome as nome_tutor, c.cpf_cnpj, c.endereco, c.numero, c.bairro, c.cidade, c.estado
            FROM atendimentos a
            INNER JOIN pacientes p ON a.id_paciente = p.id
            INNER JOIN clientes c ON p.id_cliente = c.id
            WHERE a.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_doc]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc) {
        die("Documento não encontrado no sistema.");
    }

    $usuario_logado = $_SESSION['usuario_nome'] ?? 'Médico(a) Veterinário(a)';

} catch (PDOException $e) {
    die("Erro ao processar impressão: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Imprimir - <?= htmlspecialchars($doc['resumo']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #fff; color: #000; font-size: 14px; line-height: 1.5; }
        
        .container-impressao { width: 210mm; margin: 0 auto; padding: 20px; }
        
        /* Estilo da Via */
        .via-doc { padding: 40px; border: 1px solid #eee; position: relative; min-height: 480px; }
        .cabecalho { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1c329f; padding-bottom: 10px; }
        .cabecalho h1 { font-size: 20px; text-transform: uppercase; margin-bottom: 5px; }
        
        .corpo-texto { text-align: justify; margin-bottom: 30px; min-height: 200px; }
        .corpo-texto p { margin-bottom: 10px; }

        .assinatura-area { margin-top: 50px; text-align: center; }
        .linha-assinatura { border-top: 1px solid #000; width: 300px; margin: 0 auto 5px; }
        .nome-vet { font-weight: 700; text-transform: uppercase; font-size: 12px; }

        .identificacao-via { position: absolute; top: 10px; right: 10px; font-size: 9px; color: #999; font-weight: bold; text-transform: uppercase; }

        /* Linha de Corte */
        .linha-corte { border-top: 1px dashed #ccc; margin: 40px 0; position: relative; text-align: center; }
        .linha-corte i { position: absolute; top: -10px; left: 50%; background: #fff; padding: 0 10px; color: #ccc; }

        .btn-imprimir-fixo { position: fixed; bottom: 20px; right: 20px; background: #1c329f; color: #fff; border: none; padding: 15px 25px; border-radius: 50px; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.2); font-weight: bold; display: flex; align-items: center; gap: 10px; }

        @media print {
            .btn-imprimir-fixo { display: none; }
            .via-doc { border: none; padding: 20px 0; }
            .linha-corte { margin: 20px 0; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <button class="btn-imprimir-fixo" onclick="window.print()">
        <i class="fas fa-print"></i> IMPRIMIR AGORA
    </button>

    <div class="container-impressao">
        
        <?php 
        // Gerar 2 Vias (Médico e Cliente)
        $vias = ["1ª VIA: MÉDICO-VETERINÁRIO", "2ª VIA: PROPRIETÁRIO/TUTOR"];
        foreach ($vias as $index => $titulo_via): 
        ?>
            <div class="via-doc">
                <div class="identificacao-via"><?= $titulo_via ?></div>
                
                <div class="cabecalho">
                    <h1><?= str_replace('Documento - ', '', $doc['tipo_atendimento']) ?></h1>
                    <p style="font-size: 12px; color: #666;">ZiipVet - Sistema de Gestão Veterinária</p>
                </div>

                <div class="corpo-texto">
                    <?= $doc['descricao'] ?>
                </div>

                <div class="assinatura-area">
                    <div class="linha-assinatura"></div>
                    <span class="nome-vet"><?= $usuario_logado ?></span><br>
                    <small>Médico(a) Veterinário(a)</small>
                </div>
            </div>

            <?php if ($index == 0): ?>
                <div class="linha-corte">
                    <i class="fas fa-cut"></i>
                </div>
            <?php endif; ?>

        <?php endforeach; ?>

    </div>

</body>
</html>