<?php
/**
 * ZIIPVET - GESTÃO DE AGENDA UNIFICADA
 * ARQUIVO: agenda.php
 * VERSÃO: 8.0.0 - DESIGN MODERNO
 */
require_once 'auth.php';
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$msg_feedback = "";
$status_feedback = "";

// FERIADOS NACIONAIS
$feriados = [
    '2025-01-01' => 'Confraternização Universal', '2025-02-14' => 'Carnaval', '2025-04-18' => 'Sexta-feira Santa',
    '2025-04-21' => 'Tiradentes', '2025-05-01' => 'Dia do Trabalho', '2025-06-19' => 'Corpus Christi',
    '2025-09-07' => 'Independência do Brasil', '2025-10-12' => 'Nossa Senhora Aparecida', '2025-11-02' => 'Finados',
    '2025-11-15' => 'Proclamação da República', '2025-11-20' => 'Consciência Negra', '2025-12-25' => 'Natal',
    '2026-01-01' => 'Confraternização Universal', '2026-02-16' => 'Carnaval', '2026-04-03' => 'Sexta-feira Santa',
    '2026-04-21' => 'Tiradentes', '2026-05-01' => 'Dia do Trabalho', '2026-06-04' => 'Corpus Christi',
    '2026-09-07' => 'Independência do Brasil', '2026-10-12' => 'Nossa Senhora Aparecida', '2026-11-02' => 'Finados',
    '2026-11-15' => 'Proclamação da República', '2026-11-20' => 'Consciência Negra', '2026-12-25' => 'Natal'
];

$dias_semana_pt = [
    'Monday' => 'segunda-feira', 'Tuesday' => 'terça-feira', 'Wednesday' => 'quarta-feira',
    'Thursday' => 'quinta-feira', 'Friday' => 'sexta-feira', 'Saturday' => 'sábado', 'Sunday' => 'domingo'
];

$modo_visualizacao = $_GET['modo'] ?? 'semana';

/**
 * PROCESSAMENTO DE AÇÕES
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    
    if ($_POST['acao'] === 'salvar_agenda') {
        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO agendas (id_paciente, id_servico, data_agendamento, horario, observacoes, usuario_registro, recorrente, status, checklist_data) 
                    VALUES (:pac, :ser, :dat, :hor, :obs, :usu, :rec, 'Agendado', :check)";
            
            $stmt = $pdo->prepare($sql);
            
            $extras = isset($_POST['extras']) ? json_encode($_POST['extras']) : null;
            
            $stmt->execute([
                ':pac' => $_POST['id_paciente'],
                ':ser' => $_POST['id_servico'],
                ':dat' => $_POST['data_agendamento'],
                ':hor' => $_POST['horario'],
                ':obs' => $_POST['observacoes'],
                ':usu' => $_SESSION['usuario_nome'] ?? 'Veterinário',
                ':rec' => isset($_POST['recorrente']) ? 1 : 0,
                ':check' => $extras
            ]);

            // Recorrência
            if (isset($_POST['recorrente']) && $_POST['recorrente']) {
                for ($i = 1; $i <= 4; $i++) {
                    $nova_data = date('Y-m-d', strtotime($_POST['data_agendamento'] . " +$i week"));
                    
                    $check = $pdo->prepare("SELECT id FROM agendas WHERE data_agendamento = ? AND horario = ? AND status <> 'Cancelado'");
                    $check->execute([$nova_data, $_POST['horario']]);
                    
                    if (!$check->fetch()) {
                        $stmt->execute([
                            ':pac' => $_POST['id_paciente'],
                            ':ser' => $_POST['id_servico'],
                            ':dat' => $nova_data,
                            ':hor' => $_POST['horario'],
                            ':obs' => "[RECORRÊNCIA] " . $_POST['observacoes'],
                            ':usu' => $_SESSION['usuario_nome'] ?? 'Veterinário',
                            ':rec' => 1,
                            ':check' => $extras
                        ]);
                    }
                }
            }

            $pdo->commit();
            $msg_feedback = "Agendamento salvo com sucesso!";
            $status_feedback = "success";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $msg_feedback = $e->getCode() == 23000 ? "Horário já ocupado!" : "Erro: " . $e->getMessage();
            $status_feedback = "error";
        }
    }
    
    elseif ($_POST['acao'] === 'atualizar_agenda' && isset($_POST['id_agenda'])) {
        try {
            $pdo->beginTransaction();

            $extras = isset($_POST['extras']) ? json_encode($_POST['extras']) : null;

            $sql = "UPDATE agendas SET 
                    id_paciente = :pac, 
                    id_servico = :ser, 
                    data_agendamento = :dat, 
                    horario = :hor, 
                    observacoes = :obs,
                    usuario_registro = :usu,
                    checklist_data = :check
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':pac' => $_POST['id_paciente'],
                ':ser' => $_POST['id_servico'],
                ':dat' => $_POST['data_agendamento'],
                ':hor' => $_POST['horario'],
                ':obs' => $_POST['observacoes'],
                ':usu' => $_SESSION['usuario_nome'] ?? 'Veterinário',
                ':check' => $extras,
                ':id'  => (int)$_POST['id_agenda']
            ]);

            $pdo->commit();
            $msg_feedback = "Agendamento atualizado com sucesso!";
            $status_feedback = "success";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $msg_feedback = "Erro ao atualizar: " . $e->getMessage();
            $status_feedback = "error";
        }
    }
    
    elseif ($_POST['acao'] === 'finalizar_agenda' && isset($_POST['id_agenda'])) {
        try {
            $sql = "UPDATE agendas SET status = 'Finalizado', horario_fim = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([(int)$_POST['id_agenda']]);
            
            $msg_feedback = "Atendimento finalizado com sucesso!";
            $status_feedback = "success";
        } catch (PDOException $e) {
            $msg_feedback = "Erro ao finalizar: " . $e->getMessage();
            $status_feedback = "error";
        }
    }
}

// NAVEGAÇÃO CONFORME MODO
$data_base = isset($_GET['data']) ? new DateTime($_GET['data']) : new DateTime();

if ($modo_visualizacao == 'semana') {
    if ($data_base->format('N') != 1) {
        $data_base->modify('last monday');
    }
    $inicio_semana = clone $data_base;
    $fim_semana = clone $data_base;
    $fim_semana->modify('+6 days');
    
    $anterior = clone $inicio_semana;
    $anterior->modify('-7 days');
    $proxima = clone $inicio_semana;
    $proxima->modify('+7 days');
    
} elseif ($modo_visualizacao == 'dia') {
    $inicio_semana = clone $data_base;
    $fim_semana = clone $data_base;
    
    $anterior = clone $data_base;
    $anterior->modify('-1 day');
    $proxima = clone $data_base;
    $proxima->modify('+1 day');
    
} else {
    $inicio_semana = new DateTime($data_base->format('Y-m-01'));
    $fim_semana = new DateTime($data_base->format('Y-m-t'));
    
    $anterior = clone $inicio_semana;
    $anterior->modify('-1 month');
    $proxima = clone $inicio_semana;
    $proxima->modify('+1 month');
}

// VERIFICAR FERIADO
$feriado_semana = null;
for ($i = 0; $i < 7; $i++) {
    $data_check = clone $inicio_semana;
    $data_check->modify("+$i days");
    $data_str = $data_check->format('Y-m-d');
    if (isset($feriados[$data_str])) {
        $feriado_semana = [
            'data' => $data_check->format('d/m/Y'),
            'dia_semana' => $data_check->format('l'),
            'nome' => $feriados[$data_str]
        ];
        break;
    }
}

// BUSCAR DADOS
try {
    $sql = "SELECT a.*, p.nome_paciente, p.especie, c.nome as nome_cliente, s.nome as nome_servico 
            FROM agendas a
            INNER JOIN pacientes p ON a.id_paciente = p.id
            INNER JOIN clientes c ON p.id_cliente = c.id
            INNER JOIN produtos s ON a.id_servico = s.id
            WHERE a.data_agendamento BETWEEN :inicio AND :fim
            AND a.status <> 'Cancelado'
            ORDER BY a.horario ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':inicio' => $inicio_semana->format('Y-m-d'),
        ':fim' => $fim_semana->format('Y-m-d')
    ]);
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $agenda_organizada = [];
    foreach ($agendamentos as $ag) {
        $agenda_organizada[$ag['data_agendamento']][] = $ag;
    }

    $sql_clientes = "SELECT c.id as id_cliente, c.nome as nome_cliente, 
                     GROUP_CONCAT(p.id ORDER BY p.nome_paciente SEPARATOR '|') as ids_pacientes,
                     GROUP_CONCAT(p.nome_paciente ORDER BY p.nome_paciente SEPARATOR '|') as nomes_pacientes,
                     GROUP_CONCAT(p.especie ORDER BY p.nome_paciente SEPARATOR '|') as especies_pacientes
                     FROM clientes c
                     INNER JOIN pacientes p ON c.id = p.id_cliente
                     WHERE p.status = 'ATIVO'
                     GROUP BY c.id, c.nome
                     ORDER BY c.nome ASC";
    
    $lista_clientes = $pdo->query($sql_clientes)->fetchAll(PDO::FETCH_ASSOC);

    $sql_pacientes = "SELECT p.id, p.nome_paciente, p.especie, p.id_cliente, c.nome as nome_cliente
                      FROM pacientes p
                      INNER JOIN clientes c ON p.id_cliente = c.id
                      WHERE p.status = 'ATIVO'
                      ORDER BY p.nome_paciente ASC";
    $todos_pacientes = $pdo->query($sql_pacientes)->fetchAll(PDO::FETCH_ASSOC);

    $lista_servicos = $pdo->query("SELECT id, nome, preco_venda FROM produtos WHERE tipo = 'Servico' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erro de Banco: " . $e->getMessage());
}

setlocale(LC_TIME, 'pt_BR.UTF-8', 'pt_BR', 'portuguese');

$titulo_pagina = "Agenda de Atendimentos";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <!-- CSS CENTRALIZADO -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/formularios.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        /* PAINEL LATERAL MODERNO */
        .painel-lateral {
            position: fixed;
            right: -650px;
            top: 0;
            width: 650px;
            height: 100vh;
            background: white;
            box-shadow: -5px 0 25px rgba(0,0,0,0.15);
            z-index: 9999;
            transition: right 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow-y: auto;
        }

        .painel-lateral.aberto { right: 0; }

        .painel-header {
            background: linear-gradient(135deg, #b92426, #dc3545);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .painel-header h3 {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Exo', sans-serif;
        }

        .btn-fechar-painel {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }

        .btn-fechar-painel:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }

        /* ABAS DO PAINEL */
        .abas-painel {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }

        .aba-btn {
            flex: 1;
            padding: 15px 20px;
            background: transparent;
            border: none;
            color: #666;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: 'Exo', sans-serif;
        }

        .aba-btn:hover {
            background: #fff;
            color: #333;
        }

        .aba-btn.ativa {
            background: #fff;
            color: #b92426;
            border-bottom-color: #b92426;
        }

        .aba-conteudo {
            display: none;
            padding: 30px;
        }

        .aba-conteudo.ativa {
            display: block;
        }

        .painel-body {
            padding: 0;
        }

        .overlay-painel {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9998;
            display: none;
            backdrop-filter: blur(3px);
        }

        .overlay-painel.ativo { display: block; }

        /* CHECKLISTS */
        .checklist-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: 0.2s;
        }

        .checklist-item:hover {
            background: #e9ecef;
        }

        .checklist-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checklist-item label {
            flex: 1;
            cursor: pointer;
            font-size: 14px;
            color: #333;
            font-family: 'Exo', sans-serif;
        }

        /* CARDS DE PACIENTES CLICÁVEIS */
        .card-paciente {
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-paciente:hover {
            border-color: #b92426;
            background: #fff5f5;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(185, 36, 38, 0.15);
        }

        .card-paciente.selecionado {
            border-color: #b92426;
            background: linear-gradient(135deg, #fff0f0, #ffe8e8);
            box-shadow: 0 4px 15px rgba(185, 36, 38, 0.2);
        }

        .card-paciente-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #b92426, #dc3545);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .card-paciente-info {
            flex: 1;
        }

        .card-paciente-nome {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 3px;
            font-family: 'Exo', sans-serif;
        }

        .card-paciente-especie {
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
            font-family: 'Exo', sans-serif;
        }

        .card-paciente-check {
            width: 24px;
            height: 24px;
            border: 2px solid #ccc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
        }

        .card-paciente.selecionado .card-paciente-check {
            background: #b92426;
            border-color: #b92426;
            color: white;
        }

        /* Filtros */
        .filtros-visualizacao {
            display: flex;
            justify-content: center;
            gap: 0;
            background: #fff;
            border-radius: 10px;
            padding: 6px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            width: fit-content;
            margin: 0 auto 20px;
        }

        .tab-filtro {
            padding: 10px 24px;
            background: transparent;
            border: none;
            color: #666;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            border-radius: 7px;
            text-decoration: none;
            display: inline-block;
            font-family: 'Exo', sans-serif;
        }

        .tab-filtro:hover { background: #f5f5f5; color: #333; }
        .tab-filtro.ativo { background: #b92426; color: white; box-shadow: 0 3px 10px rgba(185, 36, 38, 0.3); }

        .alerta-feriado {
            background: linear-gradient(135deg, #b92426 0%, #dc3545 100%);
            color: white;
            padding: 16px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(185, 36, 38, 0.2);
        }

        .alerta-feriado i { font-size: 24px; }
        .alerta-feriado .texto-feriado strong { font-size: 15px; font-family: 'Exo', sans-serif; }

        .agenda-nav-box { 
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 20px;
            background: #fff; 
            padding: 20px 30px; 
            border-radius: 12px; 
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
            border-left: 5px solid #b92426;
        }

        .agenda-nav-left { justify-self: start; }
        .agenda-nav-center { justify-self: center; }
        .agenda-nav-right { justify-self: end; }

        .periodo-box {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .periodo-box span {
            font-size: 15px;
            font-weight: 700;
            color: #555;
            letter-spacing: 0.5px;
            white-space: nowrap;
            font-family: 'Exo', sans-serif;
        }

        .btn-ui { 
            padding: 10px 18px; 
            border-radius: 6px; 
            text-decoration: none; 
            font-weight: 700; 
            font-size: 14px; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            text-transform: uppercase; 
            transition: 0.2s; 
            border: none; 
            cursor: pointer; 
            color: #fff;
            font-family: 'Exo', sans-serif;
        }
        .btn-prev-next { background: #17a2b8; }
        .btn-prev-next:hover { background: #138496; }
        .btn-hoje { background: #6c757d; }
        .btn-hoje:hover { background: #5a6268; }
        .btn-novo { background: #28a745; }
        .btn-novo:hover { background: #218838; }
        .btn-ui:hover { transform: translateY(-1px); }

        .semana-grid { 
            display: grid; grid-template-columns: repeat(7, 1fr); gap: 12px; 
            align-items: start; width: 100%; min-width: 1200px;
        }

        .dia-grid {
            max-width: 800px;
            margin: 0 auto;
        }

        .dia-lista-item {
            background: #fff;
            border-left: 4px solid #17a2b8;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            cursor: pointer;
            transition: 0.2s;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 20px;
            align-items: center;
        }

        .dia-lista-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left-color: #b92426;
        }

        .dia-lista-horario {
            background: linear-gradient(135deg, #b92426, #dc3545);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            min-width: 100px;
        }

        .dia-lista-horario-hora {
            font-size: 24px;
            font-weight: 700;
            display: block;
            font-family: 'Exo', sans-serif;
        }

        .dia-lista-horario-label {
            font-size: 11px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-family: 'Exo', sans-serif;
        }

        .dia-lista-info {
            flex: 1;
        }

        .dia-lista-paciente {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
            font-family: 'Exo', sans-serif;
        }

        .dia-lista-servico {
            font-size: 14px;
            color: #666;
            margin-bottom: 3px;
            font-family: 'Exo', sans-serif;
        }

        .dia-lista-tutor {
            font-size: 13px;
            color: #999;
            font-family: 'Exo', sans-serif;
        }

        .dia-lista-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
        }

        .status-agendado {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-finalizado {
            background: #e8f5e9;
            color: #388e3c;
        }

        .mes-calendario {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .mes-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .mes-nome {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            text-transform: capitalize;
            font-family: 'Exo', sans-serif;
        }

        .calendario-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }

        .calendario-dia-semana {
            text-align: center;
            padding: 10px;
            font-weight: 700;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
        }

        .calendario-dia {
            aspect-ratio: 1;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 10px;
            cursor: pointer;
            transition: 0.3s;
            position: relative;
            background: white;
            min-height: 100px;
        }

        .calendario-dia:hover {
            border-color: #b92426;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(185, 36, 38, 0.15);
        }

        .calendario-dia.outro-mes {
            opacity: 0.3;
            pointer-events: none;
        }

        .calendario-dia.hoje {
            border-color: #b92426;
            background: #fff9f9;
        }

        .calendario-dia-numero {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
            font-family: 'Exo', sans-serif;
        }

        .calendario-dia.hoje .calendario-dia-numero {
            color: #b92426;
        }

        .calendario-eventos {
            display: flex;
            flex-direction: column;
            gap: 3px;
            margin-top: 5px;
        }

        .calendario-evento-dot {
            width: 100%;
            height: 4px;
            background: #17a2b8;
            border-radius: 2px;
        }

        .calendario-evento-count {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #b92426;
            color: white;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            font-family: 'Exo', sans-serif;
        }

        .dia-card { 
            background: #fff; 
            border-radius: 10px; 
            min-height: 75vh; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.04); 
            border-top: 4px solid #e0e0e0;
            position: relative;
        }
        .dia-card.hoje { border-top-color: #b92426; background: #fff9f9; }
        .dia-card.feriado { border-top-color: #ff9800; }

        .dia-header { 
            padding: 15px 10px; 
            text-align: center; 
            border-bottom: 1px solid #f4f4f4; 
        }
        .dia-header h4 { 
            font-size: 13px; 
            color: #999; 
            text-transform: uppercase; 
            font-weight: 700; 
            margin-bottom: 4px;
            font-family: 'Exo', sans-serif;
        }
        .dia-header span { 
            font-size: 20px; 
            font-weight: 800; 
            color: #333;
            font-family: 'Exo', sans-serif;
        }

        .badge-feriado-card {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #ff9800;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            font-family: 'Exo', sans-serif;
        }

        .ag-item {
            background: #fff; 
            border: 1px solid #eee; 
            border-left: 4px solid #17a2b8;
            margin: 10px; 
            padding: 12px; 
            border-radius: 6px; 
            cursor: pointer; 
            transition: 0.2s;
            position: relative;
        }
        .ag-item:hover { 
            transform: scale(1.03); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
            border-color: #17a2b8; 
        }
        .ag-item.finalizado { 
            border-left-color: #28a745; 
            opacity: 0.7; 
        }
        
        .ag-time { 
            font-size: 12px; 
            font-weight: 800; 
            color: #b92426; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
            margin-bottom: 6px;
            font-family: 'Exo', sans-serif;
        }
        .ag-paciente { 
            font-size: 15px; 
            font-weight: 700; 
            color: #222; 
            display: block; 
            line-height: 1.2;
            font-family: 'Exo', sans-serif;
        }
        .ag-servico { 
            font-size: 12px; 
            color: #666; 
            margin-top: 5px; 
            display: block; 
            font-style: italic;
            font-family: 'Exo', sans-serif;
        }

        .recorrencia-box {
            background: #f8f9fb;
            padding: 16px;
            border-radius: 10px;
            border: 1px solid #edf0f2;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .switch input { opacity: 0; width: 0; height: 0; }

        .slider {
            position: absolute; 
            cursor: pointer;
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute; 
            content: "";
            height: 18px; 
            width: 18px;
            left: 4px; 
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider { background-color: #b92426; }
        input:checked + .slider:before { transform: translateX(24px); }

        .btn-submit {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 16px 30px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            text-transform: uppercase;
            width: 100%;
            margin-top: 20px;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-family: 'Exo', sans-serif;
        }

        .btn-submit:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.2);
        }

        .btn-atualizar { background: #17a2b8; }
        .btn-atualizar:hover { background: #138496; box-shadow: 0 5px 15px rgba(23, 162, 184, 0.2); }

        .info-display {
            background: #e3f2fd;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 14px;
            color: #1976d2;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #adb5bd;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state p {
            font-size: 18px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
        }
    </style>
</head>
<body>

    <?php if ($msg_feedback): ?>
        <script>
            $(document).ready(function() {
                Swal.fire({
                    title: '<?= $status_feedback == "success" ? "Sucesso" : "Atenção" ?>',
                    text: '<?= $msg_feedback ?>',
                    icon: '<?= $status_feedback ?>',
                    confirmButtonColor: '#b92426'
                }).then(() => {
                    window.location.href = 'agenda.php';
                });
            });
        </script>
    <?php endif; ?>

    <div class="overlay-painel" onclick="fecharPainel()"></div>

    <!-- PAINEL LATERAL -->
    <div class="painel-lateral" id="painelLateral">
        <div class="painel-header">
            <h3 id="painelTitulo">
                <i class="fas fa-calendar-plus"></i>
                <span>Novo Agendamento</span>
            </h3>
            <button class="btn-fechar-painel" onclick="fecharPainel()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- ABAS -->
        <div class="abas-painel">
            <button class="aba-btn ativa" onclick="trocarAba('agendamento')">
                <i class="fas fa-calendar-alt"></i> Agendamento
            </button>
            <button class="aba-btn" onclick="trocarAba('extras')">
                <i class="fas fa-star"></i> Extras
            </button>
        </div>

        <div class="painel-body">
            <form method="POST" id="formAgenda">
                <input type="hidden" name="acao" id="formAcao" value="salvar_agenda">
                <input type="hidden" name="id_agenda" id="formIdAgenda">
                <input type="hidden" name="id_paciente" id="hiddenIdPaciente">

                <!-- ABA AGENDAMENTO -->
                <div class="aba-conteudo ativa" id="abaAgendamento">
                    <div class="form-group">
                        <label>Cliente / Animal *</label>
                        <select id="selectCliente" required>
                            <option value="">-- Selecione o cliente --</option>
                            <?php foreach ($lista_clientes as $c): ?>
                                <option value="<?= $c['id_cliente'] ?>" 
                                        data-pacientes='<?= json_encode([
                                            'ids' => explode('|', $c['ids_pacientes']),
                                            'nomes' => explode('|', $c['nomes_pacientes']),
                                            'especies' => explode('|', $c['especies_pacientes'])
                                        ]) ?>'>
                                    <?php 
                                    $nomes_pacientes = explode('|', $c['nomes_pacientes']);
                                    echo htmlspecialchars($c['nome_cliente']) . ' - ' . implode(', ', $nomes_pacientes);
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="grupoPacientes" style="display: none;">
                        <label>Selecione o Animal *</label>
                        <div id="cardsPacientes" style="display: grid; gap: 10px; margin-top: 10px;">
                            <!-- Cards serão inseridos aqui via JavaScript -->
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Serviço Desejado *</label>
                        <select name="id_servico" id="selectServico" required>
                            <option value="">-- Selecione o serviço --</option>
                            <?php foreach ($lista_servicos as $s): ?>
                                <option value="<?= $s['id'] ?>">
                                    <?= htmlspecialchars($s['nome']) ?> - R$ <?= number_format($s['preco_venda'], 2, ',', '.') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Data da Agenda *</label>
                            <input type="date" name="data_agendamento" id="inputData" required>
                        </div>

                        <div class="form-group">
                            <label>Horário de Início *</label>
                            <select name="horario" id="selectHorario" required>
                                <option value="">-- Escolha --</option>
                                <?php 
                                $grade_horaria = ["08:00","08:30","09:00","09:30","10:00","10:30","11:00","11:30","13:30","14:00","14:30","15:00","15:30","16:00","16:30","17:00","17:30"];
                                foreach($grade_horaria as $hora): ?>
                                    <option value="<?= $hora ?>"><?= $hora ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Horário de Fim</label>
                        <div class="info-display" id="displayHorarioFim">
                            <i class="fas fa-info-circle"></i> Será preenchido automaticamente ao finalizar o atendimento
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Responsável</label>
                        <input type="text" value="<?= $_SESSION['usuario_nome'] ?? 'Veterinário' ?>" readonly style="background: #f0f3f5; color: #666;">
                    </div>

                    <div class="form-group" id="boxRecorrencia">
                        <div class="recorrencia-box">
                            <label class="switch">
                                <input type="checkbox" name="recorrente" id="checkRecorrente">
                                <span class="slider"></span>
                            </label>
                            <div>
                                <span style="font-weight: 700; color: #444; font-size: 14px;">Recorrência Semanal</span><br>
                                <small style="color: #888; font-size: 12px;">Criar para as próximas 4 semanas</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Observações</label>
                        <textarea name="observacoes" id="textareaObs" rows="4" placeholder="Observações e recomendações..."></textarea>
                    </div>
                </div>

                <!-- ABA EXTRAS -->
                <div class="aba-conteudo" id="abaExtras">
                    <div id="extrasContainer">
                        <h4 style="margin-bottom: 20px; color: #333; font-size: 16px;">
                            <i class="fas fa-star"></i> Serviços Extras
                        </h4>
                        
                        <div class="checklist-item">
                            <input type="checkbox" name="extras[]" value="Corte / aparação de unhas" id="extra0">
                            <label for="extra0">Corte / aparação de unhas</label>
                        </div>
                        
                        <div class="checklist-item">
                            <input type="checkbox" name="extras[]" value="Limpeza de ouvidos" id="extra1">
                            <label for="extra1">Limpeza de ouvidos</label>
                        </div>
                        
                        <div class="checklist-item">
                            <input type="checkbox" name="extras[]" value="Limpeza de olhos" id="extra2">
                            <label for="extra2">Limpeza de olhos</label>
                        </div>
                        
                        <div class="checklist-item">
                            <input type="checkbox" name="extras[]" value="Escovação de dentes" id="extra3">
                            <label for="extra3">Escovação de dentes</label>
                        </div>
                        
                        <div class="checklist-item">
                            <input type="checkbox" name="extras[]" value="Higienização das patas" id="extra4">
                            <label for="extra4">Higienização das patas</label>
                        </div>
                        
                        <div class="checklist-item">
                            <input type="checkbox" name="extras[]" value="Limpeza das glândulas anais" id="extra5">
                            <label for="extra5">Limpeza das glândulas anais</label>
                        </div>
                        
                        <div class="checklist-item">
                            <input type="checkbox" name="extras[]" value="Aparar pelos da região íntima" id="extra6">
                            <label for="extra6">Aparar pelos da região íntima</label>
                        </div>
                        
                        <div class="checklist-item">
                            <input type="checkbox" name="extras[]" value="Aparar pelos do rosto" id="extra7">
                            <label for="extra7">Aparar pelos do rosto</label>
                        </div>
                        
                        <div class="checklist-item">
                            <input type="checkbox" name="extras[]" value="Banho com shampoo terapêutico" id="extra8">
                            <label for="extra8">Banho com shampoo terapêutico</label>
                        </div>
                        
                        <div class="checklist-item">
                            <input type="checkbox" name="extras[]" value="Banho dermatológico" id="extra9">
                            <label for="extra9">Banho dermatológico</label>
                        </div>
                        
                        <div class="checklist-item">
                            <input type="checkbox" name="extras[]" value="Banho clareador" id="extra10">
                            <label for="extra10">Banho clareador</label>
                        </div>
                        
                        <div class="checklist-item">
                            <input type="checkbox" name="extras[]" value="Não usar perfume / colônia pet" id="extra11">
                            <label for="extra11">Não usar perfume / colônia pet</label>
                        </div>
                    </div>
                </div>

                <div style="padding: 0 30px 30px;">
                    <button type="submit" class="btn-submit" id="btnSubmit">
                        <i class="fas fa-save"></i> Salvar Agendamento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div class="filtros-visualizacao">
            <a href="?modo=dia&data=<?= $data_base->format('Y-m-d') ?>" class="tab-filtro <?= $modo_visualizacao == 'dia' ? 'ativo' : '' ?>">Dia</a>
            <a href="?modo=semana&data=<?= $data_base->format('Y-m-d') ?>" class="tab-filtro <?= $modo_visualizacao == 'semana' ? 'ativo' : '' ?>">Semana</a>
            <a href="?modo=mes&data=<?= $data_base->format('Y-m-d') ?>" class="tab-filtro <?= $modo_visualizacao == 'mes' ? 'ativo' : '' ?>">Mês</a>
        </div>

        <?php if ($feriado_semana): ?>
        <div class="alerta-feriado">
            <i class="fas fa-calendar-day"></i>
            <div class="texto-feriado">
                <strong>Feriado nacional - <?= $feriado_semana['nome'] ?> - <?= $feriado_semana['data'] ?> (<?= $dias_semana_pt[$feriado_semana['dia_semana']] ?>)</strong>
            </div>
        </div>
        <?php endif; ?>

        <div class="agenda-nav-box">
            <div class="agenda-nav-left">
                <h2 style="font-size: 24px; color: #2c3e50; font-weight: 700; margin: 0;">
                    <i class="far fa-calendar-check" style="margin-right: 10px;"></i> Agenda de Atendimentos
                </h2>
                <span style="color: #888; font-weight: 600; font-size: 14px;">Visualização: <?= ucfirst($modo_visualizacao) ?></span>
            </div>
            
            <div class="agenda-nav-center">
                <div class="periodo-box">
                    <span>
                        <?php if ($modo_visualizacao == 'dia'): ?>
                            <?= $data_base->format('d/m/Y') ?>
                        <?php elseif ($modo_visualizacao == 'mes'): ?>
                            <?= strftime('%B de %Y', $data_base->getTimestamp()) ?>
                        <?php else: ?>
                            <?= $inicio_semana->format('d/m/Y') ?> - <?= $fim_semana->format('d/m/Y') ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <div class="agenda-nav-right" style="display: flex; gap: 10px; align-items: center;">
                <a href="?modo=<?= $modo_visualizacao ?>&data=<?= $anterior->format('Y-m-d') ?>" class="btn-ui btn-prev-next">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <a href="?modo=<?= $modo_visualizacao ?>" class="btn-ui btn-hoje">Hoje</a>
                <a href="?modo=<?= $modo_visualizacao ?>&data=<?= $proxima->format('Y-m-d') ?>" class="btn-ui btn-prev-next">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <button onclick="abrirPainelNovo()" class="btn-ui btn-novo" style="margin-left: 20px;">
                    <i class="fas fa-plus"></i> Novo Horário
                </button>
            </div>
        </div>

        <div style="overflow-x: auto; padding-bottom: 20px;">
            
            <?php if ($modo_visualizacao == 'dia'): ?>
                <!-- VISUALIZAÇÃO DIA -->
                <div class="dia-grid">
                    <h3 style="text-align: center; margin-bottom: 30px; color: #333; font-size: 20px;">
                        <i class="fas fa-calendar-day"></i> 
                        <?= strftime('%A, %d de %B de %Y', $data_base->getTimestamp()) ?>
                    </h3>
                    
                    <?php 
                    $dataFormatada = $data_base->format('Y-m-d');
                    if (isset($agenda_organizada[$dataFormatada]) && count($agenda_organizada[$dataFormatada]) > 0): 
                    ?>
                        <?php foreach ($agenda_organizada[$dataFormatada] as $ag): ?>
                            <div class="dia-lista-item" onclick='abrirPainelEditar(<?= json_encode($ag) ?>)'>
                                <div class="dia-lista-horario">
                                    <span class="dia-lista-horario-hora"><?= substr($ag['horario'], 0, 5) ?></span>
                                    <span class="dia-lista-horario-label">Horário</span>
                                </div>
                                
                                <div class="dia-lista-info">
                                    <div class="dia-lista-paciente"><?= htmlspecialchars($ag['nome_paciente']) ?></div>
                                    <div class="dia-lista-servico">
                                        <i class="fas fa-cut"></i> <?= htmlspecialchars($ag['nome_servico']) ?>
                                    </div>
                                    <div class="dia-lista-tutor">
                                        <i class="fas fa-user"></i> <?= htmlspecialchars($ag['nome_cliente']) ?>
                                    </div>
                                </div>
                                
                                <div class="dia-lista-status status-<?= strtolower($ag['status']) ?>">
                                    <?= $ag['status'] ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="far fa-calendar-times"></i>
                            <p>Nenhum agendamento para este dia</p>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($modo_visualizacao == 'mes'): ?>
                <!-- VISUALIZAÇÃO MÊS -->
                <div class="mes-calendario">
                    <div class="mes-header">
                        <div class="mes-nome">
                            <?= strftime('%B de %Y', $data_base->getTimestamp()) ?>
                        </div>
                    </div>
                    
                    <div class="calendario-grid">
                        <!-- Dias da semana -->
                        <?php 
                        $dias_semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                        foreach ($dias_semana as $dia): ?>
                            <div class="calendario-dia-semana"><?= $dia ?></div>
                        <?php endforeach; ?>
                        
                        <!-- Dias do mês -->
                        <?php
                        $primeiro_dia = new DateTime($data_base->format('Y-m-01'));
                        $ultimo_dia = new DateTime($data_base->format('Y-m-t'));
                        $dia_semana_inicio = (int)$primeiro_dia->format('w');
                        
                        $mes_anterior = clone $primeiro_dia;
                        $mes_anterior->modify('-1 month');
                        $ultimo_dia_mes_anterior = (int)$mes_anterior->format('t');
                        
                        for ($i = $dia_semana_inicio - 1; $i >= 0; $i--) {
                            $dia_numero = $ultimo_dia_mes_anterior - $i;
                            echo '<div class="calendario-dia outro-mes"><div class="calendario-dia-numero">' . $dia_numero . '</div></div>';
                        }
                        
                        $total_dias = (int)$ultimo_dia->format('d');
                        for ($dia = 1; $dia <= $total_dias; $dia++) {
                            $data_celula = $data_base->format('Y-m-') . str_pad($dia, 2, '0', STR_PAD_LEFT);
                            $is_hoje = ($data_celula == date('Y-m-d'));
                            $tem_eventos = isset($agenda_organizada[$data_celula]);
                            $qtd_eventos = $tem_eventos ? count($agenda_organizada[$data_celula]) : 0;
                            
                            echo '<div class="calendario-dia ' . ($is_hoje ? 'hoje' : '') . '" onclick="window.location.href=\'?modo=dia&data=' . $data_celula . '\'">';
                            echo '<div class="calendario-dia-numero">' . $dia . '</div>';
                            
                            if ($qtd_eventos > 0) {
                                echo '<div class="calendario-evento-count">' . $qtd_eventos . '</div>';
                                echo '<div class="calendario-eventos">';
                                for ($e = 0; $e < min($qtd_eventos, 3); $e++) {
                                    echo '<div class="calendario-evento-dot"></div>';
                                }
                                echo '</div>';
                            }
                            
                            echo '</div>';
                        }
                        
                        $dia_semana_fim = (int)$ultimo_dia->format('w');
                        $dias_faltantes = 6 - $dia_semana_fim;
                        for ($i = 1; $i <= $dias_faltantes; $i++) {
                            echo '<div class="calendario-dia outro-mes"><div class="calendario-dia-numero">' . $i . '</div></div>';
                        }
                        ?>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- VISUALIZAÇÃO SEMANA (PADRÃO) -->
                <div class="semana-grid">
                <?php 
                $dias_nomes = ["Segunda", "Terça", "Quarta", "Quinta", "Sexta", "Sábado", "Domingo"];
                for ($i = 0; $i < 7; $i++): 
                    $data_loop = clone $inicio_semana;
                    $data_loop->modify("+$i days");
                    $dataFormatada = $data_loop->format('Y-m-d');
                    $is_hoje = ($dataFormatada == date('Y-m-d'));
                    $is_feriado = isset($feriados[$dataFormatada]);
                ?>
                    <div class="dia-card <?= $is_hoje ? 'hoje' : '' ?> <?= $is_feriado ? 'feriado' : '' ?>">
                        <?php if ($is_feriado): ?>
                            <div class="badge-feriado-card" title="<?= $feriados[$dataFormatada] ?>">
                                <i class="fas fa-star"></i> Feriado
                            </div>
                        <?php endif; ?>
                        
                        <div class="dia-header">
                            <h4><?= $dias_nomes[$i] ?></h4>
                            <span><?= $data_loop->format('d/m') ?></span>
                        </div>

                        <div class="dia-body">
                            <?php if (isset($agenda_organizada[$dataFormatada])): ?>
                                <?php foreach ($agenda_organizada[$dataFormatada] as $ag): ?>
                                    <div class="ag-item <?= strtolower($ag['status']) == 'finalizado' ? 'finalizado' : '' ?>" 
                                         onclick='abrirPainelEditar(<?= json_encode($ag) ?>)'>
                                        
                                        <span class="ag-time">
                                            <i class="far fa-clock"></i> <?= substr($ag['horario'], 0, 5) ?>
                                            <?php if ($ag['recorrente']): ?><i class="fas fa-sync" style="font-size: 9px; color: #ff9800;"></i><?php endif; ?>
                                        </span>

                                        <span class="ag-paciente"><?= htmlspecialchars($ag['nome_paciente']) ?></span>
                                        <small style="font-size: 11px; color: #999;">Tutor: <?= htmlspecialchars($ag['nome_cliente']) ?></small>
                                        <span class="ag-servico"><?= htmlspecialchars($ag['nome_servico']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px 10px; color: #ddd; font-size: 13px; font-style: italic;">
                                    <?= $is_feriado ? 'Feriado' : 'Livre' ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            
        </div>
    </main>

    <script>
        let pacientesData = <?= json_encode($todos_pacientes) ?>;
        let pacienteSelecionadoId = null;

        $(document).ready(function() {
            $('#selectCliente').select2({
                placeholder: 'Digite para pesquisar o cliente...',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#painelLateral')
            });

            $('#selectServico').select2({
                placeholder: 'Digite para pesquisar...',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#painelLateral'),
                templateResult: formatarServico,
                templateSelection: formatarServicoSelecionado
            });

            function formatarServico(servico) {
                if (!servico.id) return servico.text;
                const partes = servico.text.split(' - R$ ');
                if (partes.length === 2) {
                    return $('<div style="display:flex;justify-content:space-between;"><span>' + partes[0] + '</span><span style="color:#28a745;font-weight:700;">R$ ' + partes[1] + '</span></div>');
                }
                return servico.text;
            }

            function formatarServicoSelecionado(servico) {
                return servico.text;
            }

            const hoje = new Date().toISOString().split('T')[0];
            $('#inputData').attr('min', hoje).val(hoje);

            $('#selectCliente').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const pacientesInfo = selectedOption.data('pacientes');
                
                if (pacientesInfo) {
                    $('#cardsPacientes').empty();
                    pacienteSelecionadoId = null;
                    $('#hiddenIdPaciente').val('');
                    
                    pacientesInfo.ids.forEach((id, index) => {
                        const nome = pacientesInfo.nomes[index];
                        const especie = pacientesInfo.especies[index] || 'Pet';
                        
                        const icone = especie.toLowerCase().includes('gato') || especie.toLowerCase().includes('felino') 
                            ? 'fa-cat' 
                            : 'fa-dog';
                        
                        const card = $(`
                            <div class="card-paciente" data-id="${id}" data-especie="${especie}">
                                <div class="card-paciente-icon">
                                    <i class="fas ${icone}"></i>
                                </div>
                                <div class="card-paciente-info">
                                    <div class="card-paciente-nome">${nome}</div>
                                    <div class="card-paciente-especie">
                                        <i class="fas fa-paw" style="font-size: 11px;"></i>
                                        ${especie}
                                    </div>
                                </div>
                                <div class="card-paciente-check">
                                    <i class="fas fa-check" style="display: none;"></i>
                                </div>
                            </div>
                        `);
                        
                        card.on('click', function() {
                            $('.card-paciente').removeClass('selecionado');
                            $('.card-paciente-check i').hide();
                            $(this).addClass('selecionado');
                            $(this).find('.card-paciente-check i').show();
                            
                            pacienteSelecionadoId = $(this).data('id');
                            $('#hiddenIdPaciente').val(pacienteSelecionadoId);
                        });
                        
                        $('#cardsPacientes').append(card);
                    });
                    
                    $('#grupoPacientes').show();
                } else {
                    $('#grupoPacientes').hide();
                }
            });
        });

        function trocarAba(aba) {
            $('.aba-btn').removeClass('ativa');
            $('.aba-conteudo').removeClass('ativa');
            
            if (aba === 'agendamento') {
                $('.aba-btn:first').addClass('ativa');
                $('#abaAgendamento').addClass('ativa');
            } else {
                $('.aba-btn:last').addClass('ativa');
                $('#abaExtras').addClass('ativa');
            }
        }

        function abrirPainelNovo() {
            $('#painelTitulo span').text('Novo Agendamento');
            $('#painelTitulo i').removeClass('fa-calendar-edit').addClass('fa-calendar-plus');
            $('#formAcao').val('salvar_agenda');
            $('#formIdAgenda').val('');
            $('#formAgenda')[0].reset();
            $('#selectCliente').val(null).trigger('change');
            $('#selectServico').val(null).trigger('change');
            $('#grupoPacientes').hide();
            $('#cardsPacientes').empty();
            pacienteSelecionadoId = null;
            $('#hiddenIdPaciente').val('');
            $('#boxRecorrencia').show();
            $('input[name="extras[]"]').prop('checked', false);
            $('#btnSubmit').removeClass('btn-atualizar').html('<i class="fas fa-save"></i> Salvar Agendamento');
            trocarAba('agendamento');
            
            $('#painelLateral').addClass('aberto');
            $('.overlay-painel').addClass('ativo');
        }

        function abrirPainelEditar(dados) {
            $('#painelTitulo span').text('Editar Agendamento');
            $('#painelTitulo i').removeClass('fa-calendar-plus').addClass('fa-calendar-edit');
            $('#formAcao').val('atualizar_agenda');
            $('#formIdAgenda').val(dados.id);
            
            const paciente = pacientesData.find(p => p.id == dados.id_paciente);
            if (paciente) {
                $('#selectCliente').val(paciente.id_cliente).trigger('change');
                setTimeout(() => {
                    $(`.card-paciente[data-id="${dados.id_paciente}"]`).click();
                }, 200);
            }
            
            $('#selectServico').val(dados.id_servico).trigger('change');
            $('#inputData').val(dados.data_agendamento);
            $('#selectHorario').val(dados.horario.substring(0, 5));
            $('#textareaObs').val(dados.observacoes);
            $('#boxRecorrencia').hide();
            $('#btnSubmit').addClass('btn-atualizar').html('<i class="fas fa-sync-alt"></i> Atualizar Agendamento');
            
            if (dados.checklist_data) {
                try {
                    const extrasSalvos = JSON.parse(dados.checklist_data);
                    $('input[name="extras[]"]').prop('checked', false);
                    setTimeout(() => {
                        extrasSalvos.forEach(item => {
                            $(`input[name="extras[]"][value="${item}"]`).prop('checked', true);
                        });
                    }, 200);
                } catch(e) {}
            }
            
            trocarAba('agendamento');
            $('#painelLateral').addClass('aberto');
            $('.overlay-painel').addClass('ativo');
        }

        function fecharPainel() {
            $('#painelLateral').removeClass('aberto');
            $('.overlay-painel').removeClass('ativo');
        }

        $('#formAgenda').on('submit', function(e) {
            const idPaciente = $('#hiddenIdPaciente').val();
            if (!idPaciente) {
                e.preventDefault();
                Swal.fire('Atenção', 'Selecione um animal antes de continuar!', 'warning');
                return false;
            }
            $('#btnSubmit').attr('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processando...');
        });
    </script>
</body>
</html>
<?php
try {
    $check_horario = $pdo->query("SHOW COLUMNS FROM agendas LIKE 'horario_fim'")->fetch();
    if (!$check_horario) {
        $pdo->exec("ALTER TABLE agendas ADD COLUMN horario_fim DATETIME NULL");
    }
    
    $check_checklist = $pdo->query("SHOW COLUMNS FROM agendas LIKE 'checklist_data'")->fetch();
    if (!$check_checklist) {
        $pdo->exec("ALTER TABLE agendas ADD COLUMN checklist_data TEXT NULL");
    }
} catch (PDOException $e) {}
?>