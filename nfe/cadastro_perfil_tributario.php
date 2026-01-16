<?php
/**
 * ZIIPVET - Cadastro/Edição de Perfil Tributário
 * Arquivo: nfe/cadastro_perfil_tributario.php
 */
require_once '../auth.php';
require_once '../config/configuracoes.php';

use App\Utils\Csrf;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;
$id = $_GET['id'] ?? null;
$tipo_default = $_GET['tipo'] ?? 'SEM_ST';

// Processar salvamento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'salvar') {
    try {
        if ($id) {
            // Atualizar
            $sql = "UPDATE perfis_tributarios SET
                    tipo = ?,
                    inicio_vigencia = ?,
                    fim_vigencia = ?,
                    operacao = ?,
                    ncm = ?,
                    cest = ?,
                    ex_tipi = ?,
                    forma_aquisicao = ?,
                    origem_mercadoria = ?,
                    csosn = ?,
                    cst_pis = ?,
                    cst_cofins = ?,
                    updated_at = NOW()
                    WHERE id = ? AND id_admin = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['tipo'],
                $_POST['inicio_vigencia'],
                $_POST['fim_vigencia'] ?: null,
                $_POST['operacao'],
                $_POST['ncm'] ?: null,
                $_POST['cest'] ?: null,
                $_POST['ex_tipi'] ?: null,
                $_POST['forma_aquisicao'] ?: null,
                $_POST['origem_mercadoria'] ?: 0,
                $_POST['csosn'] ?: null,
                $_POST['cst_pis'] ?: null,
                $_POST['cst_cofins'] ?: null,
                $id,
                $id_admin
            ]);
        } else {
            // Inserir novo
            $sql = "INSERT INTO perfis_tributarios (
                    id_admin, tipo, inicio_vigencia, fim_vigencia, operacao,
                    ncm, cest, ex_tipi, forma_aquisicao, origem_mercadoria,
                    csosn, cst_pis, cst_cofins, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $id_admin,
                $_POST['tipo'],
                $_POST['inicio_vigencia'],
                $_POST['fim_vigencia'] ?: null,
                $_POST['operacao'],
                $_POST['ncm'] ?: null,
                $_POST['cest'] ?: null,
                $_POST['ex_tipi'] ?: null,
                $_POST['forma_aquisicao'] ?: null,
                $_POST['origem_mercadoria'] ?: 0,
                $_POST['csosn'] ?: null,
                $_POST['cst_pis'] ?: null,
                $_POST['cst_cofins'] ?: null
            ]);
        }
        
        header('Location: perfil_tributario.php');
        exit;
        
    } catch (PDOException $e) {
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}

// Carregar dados se for edição
$perfil = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM perfis_tributarios WHERE id = ? AND id_admin = ?");
    $stmt->execute([$id, $id_admin]);
    $perfil = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$perfil) {
        header('Location: perfil_tributario.php');
        exit;
    }
}

$csrf_token = Csrf::generate();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfis Tributários | ZiipVet</title>
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/menu.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/formularios.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .header-editar {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: #fff;
            padding: 15px 25px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
        }
        
        .header-editar h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }
        
        .btn-voltar-header {
            background: rgba(255,255,255,0.2);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-voltar-header:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .form-container {
            background: #fff;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 30px;
        }
        
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 6px;
        }
        
        .form-label i {
            color: #17a2b8;
            margin-left: 4px;
            cursor: help;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row.dois-campos {
            grid-template-columns: 1fr 1fr;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            background: #fff;
        }
        
        .form-group input:disabled,
        .form-group select:disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin: 30px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .btn-actions {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }
        
        .btn-salvar {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-cancelar {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-excluir {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
        }
        
        select.perfil-dropdown {
            font-size: 15px;
            font-weight: 600;
            padding: 12px;
        }
    </style>
</head>
<body>
    <?php $path_prefix = '../'; ?>
    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <div style="max-width: 1400px; margin: 0 auto;">
            
            <!-- HEADER AZUL -->
            <div class="header-editar">
                <h2>Editar</h2>
                <a href="perfil_tributario.php" class="btn-voltar-header">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>

            <!-- FORMULÁRIO -->
            <div class="form-container">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="acao" value="salvar">
                    
                    <!-- Perfil Tributário -->
                    <div style="margin-bottom: 25px;">
                        <label class="form-label">Perfil Tributário</label>
                        <select name="tipo" class="perfil-dropdown" required id="tipo_perfil">
                            <option value="COM_ST" <?= ($perfil['tipo'] ?? $tipo_default) == 'COM_ST' ? 'selected' : '' ?>>
                                Produtos COM Substituição Tributária
                            </option>
                            <option value="SEM_ST" <?= ($perfil['tipo'] ?? $tipo_default) == 'SEM_ST' ? 'selected' : '' ?>>
                                Produtos SEM Substituição Tributária
                            </option>
                        </select>
                    </div>

                    <!-- Linha 1: Datas, Operação, NCM, EX TIPI, CEST -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Início vigência <i class="fas fa-question-circle" title="Data de início da vigência"></i></label>
                            <input type="date" name="inicio_vigencia" value="<?= $perfil['inicio_vigencia'] ?? date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Fim vigência <i class="fas fa-question-circle" title="Data final (opcional)"></i></label>
                            <input type="date" name="fim_vigencia" value="<?= $perfil['fim_vigencia'] ?? '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Operação <i class="fas fa-question-circle"></i></label>
                            <select name="operacao" required>
                                <option value="Venda" <?= ($perfil['operacao'] ?? 'Venda') == 'Venda' ? 'selected' : '' ?>>Venda</option>
                                <option value="Compra">Compra</option>
                                <option value="Transferência">Transferência</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">NCM <i class="fas fa-question-circle"></i></label>
                            <input type="text" name="ncm" value="<?= $perfil['ncm'] ?? '' ?>" maxlength="8">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">EX TIPI <i class="fas fa-question-circle"></i></label>
                            <input type="text" name="ex_tipi" value="<?= $perfil['ex_tipi'] ?? '' ?>" maxlength="3" style="background:#e9ecef" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">CEST <i class="fas fa-question-circle"></i></label>
                            <input type="text" name="cest" value="<?= $perfil['cest'] ?? '' ?>" maxlength="7">
                        </div>
                    </div>

                    <!-- Linha 2: Forma de Aquisição e Origem -->
                    <div class="form-row dois-campos">
                        <div class="form-group">
                            <label class="form-label">Forma de aquisição <i class="fas fa-question-circle"></i></label>
                            <select name="forma_aquisicao">
                                <option value="">Selecione...</option>
                                <option value="Adquirente Originário" <?= ($perfil['forma_aquisicao'] ?? '') == 'Adquirente Originário' ? 'selected' : '' ?>>Adquirente Originário</option>
                                <option value="Substituto Tributário">Substituto Tributário</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Origem da mercadoria <i class="fas fa-question-circle"></i></label>
                            <select name="origem_mercadoria">
                                <option value="">Selecione...</option>
                                <option value="0" <?= ($perfil['origem_mercadoria'] ?? '0') == '0' ? 'selected' : '' ?>>0 - Nacional, exceto as indicadas nos códigos 3, 4, 5 e 8</option>
                                <option value="1">1 - Estrangeira - Importação direta, exceto a indicada no código 6</option>
                                <option value="2">2 - Estrangeira - Adquirida no mercado interno, exceto a indicada no código 7</option>
                                <option value="3">3 - Nacional, mercadoria ou bem com Conteúdo de Importação superior a 40%</option>
                                <option value="4">4 - Nacional, cuja produção tenha sido feita em conformidade com os processos produtivos básicos</option>
                                <option value="5">5 - Nacional, mercadoria ou bem com Conteúdo de Importação inferior ou igual a 40%</option>
                                <option value="6">6 - Estrangeira - Importação direta, sem similar nacional, constante em lista CAMEX</option>
                                <option value="7">7 - Estrangeira - Adquirida no mercado interno, sem similar nacional, constante em lista CAMEX</option>
                                <option value="8">8 - Nacional, mercadoria ou bem com Conteúdo de Importação superior a 70%</option>
                            </select>
                        </div>
                    </div>

                    <!-- SEÇÃO ICMS -->
                    <h3 class="section-title">ICMS</h3>
                    <div class="form-row" style="grid-template-columns: 1fr;">
                        <div class="form-group">
                            <label class="form-label">CSOSN <i class="fas fa-question-circle"></i></label>
                            <select name="csosn" id="csosn_select">
                                <option value="">Selecione...</option>
                                <!-- COM ST -->
                                <option value="500" <?= ($perfil['csosn'] ?? '') == '500' ? 'selected' : '' ?> data-tipo="COM_ST">
                                    500 - ICMS cobrado anteriormente por substituição tributária (substituído) ou por antecipação
                                </option>
                                <option value="900" data-tipo="COM_ST">900 - Outros</option>
                                
                                <!-- SEM ST -->
                                <option value="102" <?= ($perfil['csosn'] ?? '') == '102' ? 'selected' : '' ?> data-tipo="SEM_ST">
                                    102 - Tributada pelo Simples Nacional sem permissão de crédito
                                </option>
                                <option value="103" data-tipo="SEM_ST">103 - Isenção do ICMS no Simples Nacional para faixa de receita bruta</option>
                                <option value="201" data-tipo="SEM_ST">201 - Tributada pelo Simples Nacional com permissão de crédito e com cobrança do ICMS por ST</option>
                                <option value="202" data-tipo="SEM_ST">202 - Tributada pelo Simples Nacional sem permissão de crédito e com cobrança do ICMS por ST</option>
                            </select>
                        </div>
                    </div>

                    <!-- SEÇÃO PIS/COFINS -->
                    <h3 class="section-title">PIS / COFINS</h3>
                    <div class="form-row" style="grid-template-columns: 1fr;">
                        <div class="form-group">
                            <label class="form-label">CST <i class="fas fa-question-circle"></i></label>
                            <select name="cst_pis">
                                <option value="">Selecione...</option>
                                <option value="99" <?= ($perfil['cst_pis'] ?? '') == '99' ? 'selected' : '' ?>>99 - Outras Operações</option>
                                <option value="01">01 - Operação Tributável com Alíquota Básica</option>
                                <option value="02">02 - Operação Tributável com Alíquota Diferenciada</option>
                                <option value="04">04 - Operação Tributável Monofásica - Revenda a Alíquota Zero</option>
                                <option value="06">06 - Operação Tributável a Alíquota Zero</option>
                                <option value="07">07 - Operação Isenta da Contribuição</option>
                                <option value="08">08 - Operação sem Incidência da Contribuição</option>
                                <option value="09">09 - Operação com Suspensão da Contribuição</option>
                            </select>
                            <input type="hidden" name="cst_cofins" value="<?= $_POST['cst_pis'] ?? $perfil['cst_cofins'] ?? '99' ?>">
                        </div>
                    </div>

                    <!-- BOTÕES -->
                    <div class="btn-actions">
                        <button type="submit" class="btn-salvar">
                            <i class="fas fa-check"></i> Salvar
                        </button>
                        
                        <button type="button" onclick="window.location.href='perfil_tributario.php'" class="btn-cancelar">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        
                        <?php if ($id): ?>
                        <button type="button" class="btn-excluir" onclick="confirmarExclusao()">
                            <i class="fas fa-trash"></i> Excluir
                        </button>
                        <?php endif; ?>
                    </div>

                </form>
            </div>

        </div>

    </main>

    <script>
        // Filtrar CSOSN baseado no tipo de perfil
        document.getElementById('tipo_perfil').addEventListener('change', function() {
            const tipo = this.value;
            const csosn = document.getElementById('csosn_select');
            const options = csosn.querySelectorAll('option');
            
            options.forEach(opt => {
                if (opt.value === '') {
                    opt.style.display = 'block';
                    return;
                }
                
                const optTipo = opt.getAttribute('data-tipo');
                if (optTipo === tipo) {
                    opt.style.display = 'block';
                } else {
                    opt.style.display = 'none';
                }
            });
            
            // Resetar seleção se não for compatível
            const selectedOption = csosn.options[csosn.selectedIndex];
            if (selectedOption && selectedOption.getAttribute('data-tipo') !== tipo) {
                csosn.value = '';
            }
        });
        
        // Executar ao carregar
        document.getElementById('tipo_perfil').dispatchEvent(new Event('change'));
        
        // Sincronizar CST PIS e COFINS
        document.querySelector('select[name="cst_pis"]').addEventListener('change', function() {
            document.querySelector('input[name="cst_cofins"]').value = this.value;
        });
        
        function confirmarExclusao() {
            if (confirm('Tem certeza que deseja excluir este perfil tributário?')) {
                window.location.href = '?id=<?= $id ?>&acao=excluir';
            }
        }
    </script>
</body>
</html>
