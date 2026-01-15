<div class="sidebar-historico">
    <div class="card-historico" style="border-top: 4px solid var(--primaria);">
        <div class="historico-header">
            <i class="fas fa-history"></i> Histórico Completo
        </div>
        <div class="historico-lista" style="max-height: calc(100vh - 300px); overflow-y: auto; padding: 20px;">
            <?php 
            $historico_sidebar = [];
            if ($id_paciente_selecionado) {
                try {
                    $stmt_h1 = $pdo->prepare("SELECT 'atendimento' as tipo, id, tipo_atendimento as subtipo, resumo, data_atendimento as data FROM atendimentos WHERE id_paciente = ? ORDER BY data_atendimento DESC LIMIT 20");
                    $stmt_h1->execute([$id_paciente_selecionado]);
                    $h_atend = $stmt_h1->fetchAll(PDO::FETCH_ASSOC);
                    
                    $stmt_h2 = $pdo->prepare("SELECT 'patologia' as tipo, id, nome_doenca as subtipo, '' as resumo, data_registro as data FROM patologias WHERE id_paciente = ? ORDER BY data_registro DESC LIMIT 10");
                    $stmt_h2->execute([$id_paciente_selecionado]);
                    $h_pato = $stmt_h2->fetchAll(PDO::FETCH_ASSOC);

                    $stmt_h3 = $pdo->prepare("SELECT 'exame' as tipo, id, tipo_exame as subtipo, '' as resumo, data_exame as data FROM exames WHERE id_paciente = ? ORDER BY data_exame DESC LIMIT 10");
                    $stmt_h3->execute([$id_paciente_selecionado]);
                    $h_exam = $stmt_h3->fetchAll(PDO::FETCH_ASSOC);

                    $stmt_h4 = $pdo->prepare("SELECT 'receita' as tipo, id, 'Prescrição' as subtipo, '' as resumo, data_emissao as data FROM receitas WHERE id_paciente = ? ORDER BY data_emissao DESC LIMIT 10");
                    $stmt_h4->execute([$id_paciente_selecionado]);
                    $h_rec = $stmt_h4->fetchAll(PDO::FETCH_ASSOC);

                    $stmt_h5 = $pdo->prepare("SELECT 'documento' as tipo, id, tipo_documento as subtipo, '' as resumo, data_emissao as data FROM documentos_emitidos WHERE id_paciente = ? ORDER BY data_emissao DESC LIMIT 10");
                    $stmt_h5->execute([$id_paciente_selecionado]);
                    $h_doc = $stmt_h5->fetchAll(PDO::FETCH_ASSOC);

                    $historico_sidebar = array_merge($h_atend, $h_pato, $h_exam, $h_rec, $h_doc);
                    usort($historico_sidebar, function($a, $b) { return strtotime($b['data']) - strtotime($a['data']); });
                    $historico_sidebar = array_slice($historico_sidebar, 0, 30);
                } catch (PDOException $e) { error_log($e->getMessage()); }
            }

            if (!empty($historico_sidebar)): 
                foreach($historico_sidebar as $h_item): 
                    $cor = '#17a2b8'; $ico = 'fa-stethoscope'; $lbl = 'ATENDIMENTO'; $tjs = $h_item['tipo'];
                    
                    // Identificar Diagnóstico IA
                    if ($h_item['tipo'] == 'atendimento' && $h_item['subtipo'] == 'Diagnóstico IA') {
                        $cor = '#667eea'; $ico = 'fa-brain'; $lbl = 'DIAGNÓSTICO IA'; $tjs = 'diagnostico-ia';
                    }
                    elseif ($h_item['tipo'] == 'atendimento' && $h_item['subtipo'] == 'Vacinação') { 
                        $cor = '#20c997'; $ico = 'fa-syringe'; $lbl = 'VACINA'; $tjs = 'vacina'; 
                    } elseif ($h_item['tipo'] == 'patologia') { 
                        $cor = '#dc3545'; $ico = 'fa-virus'; $lbl = 'PATOLOGIA'; 
                    } elseif ($h_item['tipo'] == 'exame') { 
                        $cor = '#ffc107'; $ico = 'fa-microscope'; $lbl = 'EXAME'; 
                    } elseif ($h_item['tipo'] == 'receita') { 
                        $cor = '#28a745'; $ico = 'fa-prescription'; $lbl = 'RECEITA'; 
                    } elseif ($h_item['tipo'] == 'documento') { 
                        $cor = '#6f42c1'; $ico = 'fa-file-alt'; $lbl = 'DOCUMENTO'; 
                    }
                    $dt = new DateTime($h_item['data']);
            ?>
                <div class="historico-item-clicavel" 
                     data-tipo="<?= $tjs ?>" 
                     data-id-registro="<?= $h_item['id'] ?>" 
                     data-id-paciente="<?= $id_paciente_selecionado ?>" 
                     style="padding: 15px 0; border-bottom: 1px solid #f0f0f0; display: flex; gap: 12px;">
                    
                    <i class="fas <?= $ico ?>" style="color: <?= $cor ?>; margin-top: 4px; width: 20px; text-align: center;"></i>
                    <div style="flex: 1;">
                        <strong style="font-size: 12px; color: <?= $cor ?>; text-transform: uppercase;"><?= $lbl ?></strong><br>
                        <span style="font-size: 14px; font-weight: 600; color: #333;"><?= htmlspecialchars($h_item['subtipo']) ?></span><br> 
                        <small style="color: #999;"><?= $dt->format('d/m/Y H:i') ?></small>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div style="text-align: center; padding: 40px 0; color: #ccc;">
                    <i class="fas fa-folder-open" style="font-size: 40px; margin-bottom: 10px;"></i>
                    <p>Sem registros anteriores</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>