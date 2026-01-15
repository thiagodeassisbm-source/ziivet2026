<?php
/**
 * =========================================================================================
 * ZIIPVET - CONTROLE DE VACINAÇÃO POR CICLO ANUAL
 * ARQUIVO: listar_vacinas.php
 * VERSÃO: 19.0.0 - INCLUINDO CICLOS CONCLUÍDOS (2025) + PENDENTES (2026+)
 * =========================================================================================
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. LÓGICA AJAX PARA EXCLUSÃO
if (isset($_POST['acao']) && $_POST['acao'] === 'excluir_vacina') {
    header('Content-Type: application/json');
    $id_excluir = (int)$_POST['id'];
    try {
        $stmt_del = $pdo->prepare("DELETE FROM lembretes_vacinas WHERE id = ?");
        $stmt_del->execute([$id_excluir]);
        echo json_encode(['status' => 'success', 'message' => 'Registro removido com sucesso.']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

// 2. CAPTURA DE FILTROS
$status_filtro = $_GET['status'] ?? 'Todos';
$id_paciente_filtro = $_GET['id_paciente'] ?? '';
$ano_filtro = $_GET['ano'] ?? '';

// 3. CARREGAR LISTA DE PACIENTES
try {
    $sql_pacs = "SELECT p.id as id_paciente, p.nome_paciente, c.nome as nome_cliente 
                FROM pacientes p 
                INNER JOIN clientes c ON p.id_cliente = c.id 
                ORDER BY c.nome ASC";
    $lista_pacientes = $pdo->query($sql_pacs)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $err) { 
    $lista_pacientes = [];
}

// 4. CARREGAR LISTA DE ANOS (lembretes + atendimentos)
try {
    $sql_anos = "SELECT DISTINCT ano FROM (
                    SELECT YEAR(data_prevista) as ano FROM lembretes_vacinas
                    UNION
                    SELECT YEAR(data_atendimento) as ano FROM atendimentos WHERE tipo_atendimento = 'Vacinação'
                 ) AS anos ORDER BY ano DESC";
    $lista_anos = $pdo->query($sql_anos)->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $err) { 
    $lista_anos = [];
}

// 5. BUSCAR CICLOS: LEMBRETES PENDENTES + ATENDIMENTOS CONCLUÍDOS
$ciclos = [];

try {
    // 5.1. BUSCAR LEMBRETES PENDENTES/FUTUROS
    $sql_lembretes = "SELECT 
            lv.id,
            lv.id_paciente,
            lv.vacina_nome,
            lv.dose_prevista,
            lv.data_prevista,
            lv.status,
            p.nome_paciente,
            c.nome as nome_tutor,
            c.telefone,
            a.data_atendimento as data_aplicacao_real,
            YEAR(lv.data_prevista) as ano_ciclo
            FROM lembretes_vacinas lv
            INNER JOIN pacientes p ON lv.id_paciente = p.id
            INNER JOIN clientes c ON p.id_cliente = c.id
            LEFT JOIN atendimentos a ON lv.id_atendimento_origem = a.id
            WHERE 1=1";

    $params_lem = [];
    
    if ($id_paciente_filtro) {
        $sql_lembretes .= " AND lv.id_paciente = :id_p";
        $params_lem[':id_p'] = $id_paciente_filtro;
    }
    if ($ano_filtro) {
        $sql_lembretes .= " AND YEAR(lv.data_prevista) = :ano";
        $params_lem[':ano'] = $ano_filtro;
    }

    $sql_lembretes .= " ORDER BY lv.data_prevista ASC";

    $stmt_lem = $pdo->prepare($sql_lembretes);
    foreach ($params_lem as $key => $value) {
        $stmt_lem->bindValue($key, $value);
    }
    
    $stmt_lem->execute();
    $lembretes_raw = $stmt_lem->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar lembretes
    foreach ($lembretes_raw as $lem) {
        $key = $lem['ano_ciclo'] . '-' . $lem['id_paciente'] . '-' . $lem['vacina_nome'];
        
        if (!isset($ciclos[$key])) {
            $ciclos[$key] = [
                'ano_ciclo' => $lem['ano_ciclo'],
                'id_paciente' => $lem['id_paciente'],
                'nome_paciente' => $lem['nome_paciente'],
                'nome_tutor' => $lem['nome_tutor'],
                'telefone' => $lem['telefone'],
                'vacina_nome' => $lem['vacina_nome'],
                'doses' => [],
                'total_doses' => 0,
                'doses_concluidas' => 0,
                'doses_atrasadas' => 0
            ];
        }
        
        $ciclos[$key]['doses'][] = $lem;
        $ciclos[$key]['total_doses']++;
        
        if ($lem['status'] == 'Concluido') {
            $ciclos[$key]['doses_concluidas']++;
        }
        
        if ($lem['status'] == 'Pendente' && strtotime($lem['data_prevista']) < strtotime(date('Y-m-d'))) {
            $ciclos[$key]['doses_atrasadas']++;
        }
    }
    
    // 5.2. BUSCAR ATENDIMENTOS CONCLUÍDOS (CICLOS FINALIZADOS)
    $sql_atendimentos = "SELECT 
            a.id,
            a.id_paciente,
            a.resumo,
            a.data_atendimento,
            p.nome_paciente,
            c.nome as nome_tutor,
            c.telefone,
            YEAR(a.data_atendimento) as ano_ciclo
            FROM atendimentos a
            INNER JOIN pacientes p ON a.id_paciente = p.id
            INNER JOIN clientes c ON p.id_cliente = c.id
            WHERE a.tipo_atendimento = 'Vacinação'
            AND a.status = 'Finalizado'";
    
    $params_at = [];
    
    if ($id_paciente_filtro) {
        $sql_atendimentos .= " AND a.id_paciente = :id_p";
        $params_at[':id_p'] = $id_paciente_filtro;
    }
    if ($ano_filtro) {
        $sql_atendimentos .= " AND YEAR(a.data_atendimento) = :ano";
        $params_at[':ano'] = $ano_filtro;
    }
    
    $sql_atendimentos .= " ORDER BY a.data_atendimento DESC";
    
    $stmt_at = $pdo->prepare($sql_atendimentos);
    foreach ($params_at as $key => $value) {
        $stmt_at->bindValue($key, $value);
    }
    
    $stmt_at->execute();
    $atendimentos_raw = $stmt_at->fetchAll(PDO::FETCH_ASSOC);
    
    // Extrair vacina do resumo e agrupar
    foreach ($atendimentos_raw as $at) {
        // Extrair nome da vacina do resumo (formato: "Vacina: NOME (DOSE)")
        if (preg_match('/Vacina:\s*([^(]+)/i', $at['resumo'], $matches)) {
            $vacina_nome = trim($matches[1]);
            
            // Extrair dose
            $dose_nome = 'Dose Aplicada';
            if (preg_match('/\(([^)]+)\)/i', $at['resumo'], $matches_dose)) {
                $dose_nome = trim($matches_dose[1]);
            }
            
            $key = $at['ano_ciclo'] . '-' . $at['id_paciente'] . '-' . $vacina_nome;
            
            // Só adicionar se não existir lembrete futuro para esse ciclo
            // (evitar duplicação de ciclos finalizados que já têm lembrete para o próximo ano)
            $tem_lembrete_futuro = false;
            foreach ($lembretes_raw as $lem) {
                if ($lem['id_paciente'] == $at['id_paciente'] && 
                    $lem['vacina_nome'] == $vacina_nome && 
                    $lem['ano_ciclo'] == $at['ano_ciclo']) {
                    $tem_lembrete_futuro = true;
                    break;
                }
            }
            
            if (!$tem_lembrete_futuro) {
                if (!isset($ciclos[$key])) {
                    $ciclos[$key] = [
                        'ano_ciclo' => $at['ano_ciclo'],
                        'id_paciente' => $at['id_paciente'],
                        'nome_paciente' => $at['nome_paciente'],
                        'nome_tutor' => $at['nome_tutor'],
                        'telefone' => $at['telefone'],
                        'vacina_nome' => $vacina_nome,
                        'doses' => [],
                        'total_doses' => 0,
                        'doses_concluidas' => 0,
                        'doses_atrasadas' => 0
                    ];
                }
                
                $ciclos[$key]['doses'][] = [
                    'id' => $at['id'],
                    'id_paciente' => $at['id_paciente'],
                    'vacina_nome' => $vacina_nome,
                    'dose_prevista' => $dose_nome,
                    'data_prevista' => date('Y-m-d', strtotime($at['data_atendimento'])),
                    'status' => 'Concluido',
                    'data_aplicacao_real' => $at['data_atendimento']
                ];
                
                $ciclos[$key]['total_doses']++;
                $ciclos[$key]['doses_concluidas']++;
            }
        }
    }
    
    // Filtrar por status
    if ($status_filtro !== 'Todos') {
        $ciclos = array_filter($ciclos, function($c) use ($status_filtro) {
            if ($c['total_doses'] == $c['doses_concluidas']) {
                $status_calculado = 'Finalizado';
            } elseif ($c['doses_atrasadas'] > 0) {
                $status_calculado = 'Atrasado';
            } else {
                $status_calculado = 'Em Andamento';
            }
            return ($status_filtro === $status_calculado);
        });
    }
    
    // Ordenar por ano DESC
    uasort($ciclos, function($a, $b) {
        return $b['ano_ciclo'] <=> $a['ano_ciclo'];
    });
    
} catch (PDOException $e) {
    $ciclos = [];
}

$titulo_pagina = "Controle de Vacinas";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    <base href="https://www.lepetboutique.com.br/app/">
    
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { 
            --fundo: #ecf0f5; --primaria: #1c329f; --azul-claro: #3258db; 
            --borda: #d2d6de; --sidebar-collapsed: 75px; --sidebar-expanded: 260px; 
            --header-height: 80px; --transition: 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            --perigo: #c62828; --sucesso: #28a745; --finalizado: #1565c0;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Open Sans', sans-serif; 
            background-color: var(--fundo); 
            color: #333; 
            min-height: 100vh; 
            font-size: 15px;
            line-height: 1.6;
        }

        aside.sidebar-container { position: fixed; left: 0; top: 0; height: 100vh; width: var(--sidebar-collapsed); z-index: 1000; transition: width var(--transition); background: #fff; box-shadow: 2px 0 5px rgba(0,0,0,0.05); }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }
        
        header.top-header { position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; height: var(--header-height); z-index: 900; transition: left var(--transition); }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }
        
        main.main-content { margin-left: var(--sidebar-collapsed); padding: calc(var(--header-height) + 30px) 25px 30px; transition: margin-left var(--transition); }
        aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .page-header h2 { font-size: 28px; font-weight: 600; color: #444; }

        .btn-novo { background: var(--primaria); color: #fff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 8px; text-transform: uppercase; transition: 0.2s; }

        .filtros-box { 
            background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 25px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); display: grid; 
            grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: flex-end; 
        }
        .filtros-box label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 5px; display: block; }
        .form-control { width: 100%; height: 45px; padding: 0 15px; border: 1px solid var(--borda); border-radius: 8px; font-size: 16px; outline: none; }

        .card-tabela { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; }
        thead { background: var(--primaria); }
        th { text-align: left; padding: 18px 15px; color: #fff; font-size: 13px; text-transform: uppercase; font-weight: 700; }
        td { padding: 18px 15px; font-size: 16px; color: #444; border-bottom: 1px solid #f1f1f1; vertical-align: middle; }

        .badge-ziip { padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .status-finalizado { background: #e3f2fd; color: #1565c0; }
        .status-andamento { background: #fff8e1; color: #f57c00; }
        .status-atrasado { background: #ffebee; color: #c62828; }

        .dose-item { 
            padding: 8px 12px; 
            margin: 4px 0; 
            background: #f8f9fa; 
            border-radius: 6px; 
            border-left: 4px solid #ccc;
            font-size: 14px;
        }
        .dose-concluida { border-left-color: #28a745; background: #e8f5e9; }
        .dose-pendente { border-left-color: #ffa000; background: #fff8e1; }
        .dose-atrasada { border-left-color: #c62828; background: #ffebee; }

        .btn-acao { color: #777; margin-right: 12px; font-size: 18px; text-decoration: none; border: none; background: none; cursor: pointer; transition: 0.2s; }
        .btn-acao:hover { color: var(--azul-claro); }

        .ano-ciclo { 
            font-size: 28px; 
            font-weight: 700; 
            color: var(--primaria);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .paciente-info strong { color: var(--primaria); display: block; font-size: 17px; margin-bottom: 4px; }
        .paciente-info small { color: #888; font-weight: 600; font-size: 14px; }
        
        .select2-container--default .select2-selection--single { height: 45px; border: 1px solid var(--borda); border-radius: 8px; display: flex; align-items: center; }
        .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="page-header">
            <h2>Controle de Vacinas por Ciclo Anual</h2>
            <a href="consultas/vacinas.php" class="btn-novo"><i class="fas fa-plus"></i> Novo Registro</a>
        </div>

        <form method="GET" class="filtros-box">
            <div>
                <label>Paciente ou Tutor</label>
                <select name="id_paciente" id="select_busca_vacina" class="form-control">
                    <option value="">Todos os Pacientes</option>
                    <?php foreach($lista_pacientes as $p): ?>
                        <option value="<?= $p['id_paciente'] ?>" <?= ($id_paciente_filtro == $p['id_paciente']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nome_cliente']) ?> - Pet: <?= htmlspecialchars($p['nome_paciente']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Ano do Ciclo</label>
                <select name="ano" class="form-control">
                    <option value="">Todos os Anos</option>
                    <?php foreach($lista_anos as $ano): ?>
                        <option value="<?= $ano ?>" <?= ($ano_filtro == $ano) ? 'selected' : '' ?>><?= $ano ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Status do Ciclo</label>
                <select name="status" class="form-control">
                    <option value="Todos" <?= $status_filtro == 'Todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="Em Andamento" <?= $status_filtro == 'Em Andamento' ? 'selected' : '' ?>>Em Andamento</option>
                    <option value="Finalizado" <?= $status_filtro == 'Finalizado' ? 'selected' : '' ?>>Finalizados</option>
                    <option value="Atrasado" <?= $status_filtro == 'Atrasado' ? 'selected' : '' ?>>Atrasados</option>
                </select>
            </div>
            <button type="submit" style="background: var(--azul-claro); color: #fff; border: none; border-radius: 8px; height: 45px; padding: 0 25px; cursor: pointer; font-weight: 700;">FILTRAR</button>
        </form>

        <div class="card-tabela">
            <table>
                <thead>
                    <tr>
                        <th style="width: 120px;">Ciclo (Ano)</th>
                        <th>Paciente / Tutor</th>
                        <th>Vacina</th>
                        <th>Doses do Ano</th>
                        <th style="text-align: center; width: 150px;">Status</th>
                        <th style="text-align: center; width: 180px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($ciclos) > 0): ?>
                        <?php foreach($ciclos as $ciclo): 
                            // Calcular status do ciclo
                            if ($ciclo['total_doses'] == $ciclo['doses_concluidas']) {
                                $status_ciclo = 'Finalizado';
                                $classe_status = 'status-finalizado';
                                $icone_status = 'fa-check-circle';
                            } elseif ($ciclo['doses_atrasadas'] > 0) {
                                $status_ciclo = 'Atrasado';
                                $classe_status = 'status-atrasado';
                                $icone_status = 'fa-exclamation-triangle';
                            } else {
                                $status_ciclo = 'Em Andamento';
                                $classe_status = 'status-andamento';
                                $icone_status = 'fa-clock';
                            }
                            
                            $hoje = strtotime(date('Y-m-d'));
                        ?>
                            <tr>
                                <td>
                                    <div class="ano-ciclo">
                                        <i class="fas fa-calendar-alt" style="font-size: 24px; color: #ccc;"></i>
                                        <?= $ciclo['ano_ciclo'] ?>
                                    </div>
                                </td>
                                <td class="paciente-info">
                                    <strong><?= htmlspecialchars($ciclo['nome_paciente']) ?></strong>
                                    <small>Tutor: <?= htmlspecialchars($ciclo['nome_tutor']) ?></small>
                                </td>
                                <td>
                                    <span style="font-weight: 700; color: #555; font-size: 16px;">
                                        <?= htmlspecialchars($ciclo['vacina_nome']) ?>
                                    </span>
                                    <div style="margin-top: 4px;">
                                        <small style="color: #999;">
                                            <?= $ciclo['doses_concluidas'] ?> de <?= $ciclo['total_doses'] ?> doses aplicadas
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php foreach($ciclo['doses'] as $dose): 
                                        $data_ts = strtotime($dose['data_prevista']);
                                        
                                        if ($dose['status'] == 'Concluido') {
                                            $classe_dose = 'dose-concluida';
                                            $icone_dose = 'fa-check';
                                            $cor_icone = '#28a745';
                                            $texto_data = $dose['data_aplicacao_real'] ? date('d/m/Y', strtotime($dose['data_aplicacao_real'])) : date('d/m/Y', $data_ts);
                                        } elseif ($data_ts < $hoje) {
                                            $classe_dose = 'dose-atrasada';
                                            $icone_dose = 'fa-exclamation-circle';
                                            $cor_icone = '#c62828';
                                            $texto_data = date('d/m/Y', $data_ts);
                                        } else {
                                            $classe_dose = 'dose-pendente';
                                            $icone_dose = 'fa-clock';
                                            $cor_icone = '#ffa000';
                                            $texto_data = date('d/m/Y', $data_ts);
                                        }
                                    ?>
                                        <div class="dose-item <?= $classe_dose ?>">
                                            <i class="fas <?= $icone_dose ?>" style="color: <?= $cor_icone ?>; margin-right: 8px;"></i>
                                            <strong><?= htmlspecialchars($dose['dose_prevista']) ?></strong>
                                            <span style="color: #666; margin-left: 8px;"><?= $texto_data ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge-ziip <?= $classe_status ?>">
                                        <i class="fas <?= $icone_status ?>" style="margin-right: 5px;"></i>
                                        <?= $status_ciclo ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <a href="https://api.whatsapp.com/send?phone=55<?= preg_replace('/\D/', '', $ciclo['telefone']) ?>" target="_blank" class="btn-acao" style="color:#25d366;" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                    
                                    <?php if ($status_ciclo !== 'Finalizado'): ?>
                                        <a href="consultas/vacinas.php?id_paciente=<?= $ciclo['id_paciente'] ?>" class="btn-acao" style="color:var(--sucesso);" title="Aplicar Dose"><i class="fas fa-syringe"></i></a>
                                    <?php endif; ?>
                                    
                                    <a href="consultas/vacinas.php?id_paciente=<?= $ciclo['id_paciente'] ?>" class="btn-acao" title="Ver Detalhes"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 80px; color: #999;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i><br>
                            Nenhum registro encontrado para os filtros selecionados.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        $(document).ready(function() {
            $('#select_busca_vacina').select2({ placeholder: "Pesquise por Tutor ou Pet..." });
        });
    </script>
</body>
</html>