<?php
/**
 * ZIIPVET - CADASTRO/EDIÇÃO DE PACIENTES
 * ARQUIVO: pacientes.php
 * VERSÃO: 3.0.0 - PADRÃO MODERNO COM ABAS
 */
require_once 'auth.php'; 
require_once 'config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_admin = $_SESSION['id_admin'] ?? 1;

// ==========================================================
// LÓGICA DE PROCESSAMENTO (POST)
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    ob_clean();
    
    try {
        $pdo->beginTransaction();
        
        $id = $_POST['id'] ?? null;
        $id_cliente = $_POST['id_cliente'];
        $nome_paciente = strtoupper(trim($_POST['nome_paciente']));
        $especie = $_POST['especie'];
        $raca = $_POST['raca'];
        $sexo = $_POST['sexo'];
        $peso = $_POST['peso'];
        $data_nascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : null;
        $porte = $_POST['porte'];
        $pelagem = $_POST['pelagem'];
        $esterilizacao = $_POST['esterilizacao'];
        $chip = $_POST['chip'];
        $status = $_POST['status'];
        $observacoes = $_POST['observacoes'];

        if ($id) {
            $sql = "UPDATE pacientes SET 
                    id_cliente = :id_cliente, nome_paciente = :nome, especie = :especie,
                    raca = :raca, sexo = :sexo, peso = :peso, data_nascimento = :nasc,
                    porte = :porte, pelagem = :pelagem, esterilizacao = :esterilizacao,
                    chip = :chip, status = :status, observacoes = :obs
                    WHERE id = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id);
            $msg = "Paciente atualizado com sucesso!";
        } else {
            $sql = "INSERT INTO pacientes (
                    id_cliente, nome_paciente, especie, raca, sexo, peso, 
                    data_nascimento, porte, pelagem, esterilizacao, chip, 
                    status, observacoes
                    ) VALUES (
                    :id_cliente, :nome, :especie, :raca, :sexo, :peso, 
                    :nasc, :porte, :pelagem, :esterilizacao, :chip, 
                    :status, :obs)";
            
            $stmt = $pdo->prepare($sql);
            $msg = "Paciente cadastrado com sucesso!";
        }

        $stmt->bindValue(':id_cliente', $id_cliente);
        $stmt->bindValue(':nome', $nome_paciente);
        $stmt->bindValue(':especie', $especie);
        $stmt->bindValue(':raca', $raca);
        $stmt->bindValue(':sexo', $sexo);
        $stmt->bindValue(':peso', $peso);
        $stmt->bindValue(':nasc', $data_nascimento);
        $stmt->bindValue(':porte', $porte);
        $stmt->bindValue(':pelagem', $pelagem);
        $stmt->bindValue(':esterilizacao', $esterilizacao);
        $stmt->bindValue(':chip', $chip);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':obs', $observacoes);
        
        $stmt->execute();
        $pdo->commit();

        echo json_encode(['status' => 'success', 'message' => $msg]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar: ' . $e->getMessage()]);
    }
    exit;
}

// ==========================================================
// CARREGAMENTO DE DADOS (GET)
// ==========================================================
$id_paciente = $_GET['id'] ?? null;
$id_cliente_pre = $_GET['id_cliente'] ?? null;
$dados = [];

// Carrega Clientes para o Select
try {
    $stmt_cli = $pdo->prepare("SELECT id, nome FROM clientes WHERE id_admin = ? AND status = 'ATIVO' ORDER BY nome ASC");
    $stmt_cli->execute([$id_admin]);
    $clientes = $stmt_cli->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar clientes: " . $e->getMessage());
}

// Se for edição, carrega os dados do paciente
if ($id_paciente) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE id = ?");
        $stmt->execute([$id_paciente]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erro ao carregar paciente.");
    }
}

$titulo_pagina = $id_paciente ? "Editar Paciente" : "Novo Paciente";
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        /* CSS PARA AS ABAS */
        .tabs-container {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }
        
        .tabs-header {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab-button {
            flex: 1;
            padding: 18px 24px;
            background: transparent;
            border: none;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Exo', sans-serif;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .tab-button:hover {
            background: rgba(98, 37, 153, 0.05);
            color: #622599;
        }
        
        .tab-button.active {
            background: #fff;
            color: #622599;
            border-bottom-color: #622599;
        }
        
        .tab-button i {
            font-size: 18px;
        }
        
        .tabs-content {
            padding: 30px;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include 'menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include 'menu/faixa.php'; ?></header>

    <main class="main-content">
        
        <!-- HEADER: Título e Botão -->
        <div class="form-header">
            <h1 class="form-title">
                <i class="fas fa-paw"></i>
                <?= $titulo_pagina ?>
            </h1>
            
            <a href="listar_pacientes.php" class="btn-voltar">
                <i class="fas fa-arrow-left"></i>
                Voltar para Lista
            </a>
        </div>

        <!-- FORMULÁRIO COM ABAS -->
        <form id="formPaciente">
            <input type="hidden" name="id" value="<?= $dados['id'] ?? '' ?>">
            
            <div class="tabs-container">
                <!-- CABEÇALHO DAS ABAS -->
                <div class="tabs-header">
                    <button type="button" class="tab-button active" onclick="trocarAba(event, 'aba-dados')">
                        <i class="fas fa-info-circle"></i>
                        Dados Principais
                    </button>
                    <button type="button" class="tab-button" onclick="trocarAba(event, 'aba-fisicas')">
                        <i class="fas fa-weight"></i>
                        Características Físicas
                    </button>
                </div>
                
                <!-- CONTEÚDO DAS ABAS -->
                <div class="tabs-content">
                    
                    <!-- ABA 1: DADOS PRINCIPAIS -->
                    <div id="aba-dados" class="tab-pane active">
                        <div class="form-grid">
                            <div class="form-group half">
                                <label class="required">
                                    <i class="fas fa-user"></i>
                                    Cliente / Tutor
                                </label>
                                <select name="id_cliente" required>
                                    <option value="">Selecione o Cliente</option>
                                    <?php foreach($clientes as $cli): ?>
                                        <option value="<?= $cli['id'] ?>" 
                                                <?= ($dados['id_cliente'] ?? $id_cliente_pre) == $cli['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cli['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group half">
                                <label class="required">
                                    <i class="fas fa-paw"></i>
                                    Nome do Paciente
                                </label>
                                <input type="text" 
                                       name="nome_paciente" 
                                       value="<?= $dados['nome_paciente'] ?? '' ?>" 
                                       required 
                                       placeholder="Nome do Pet">
                            </div>

                            <div class="form-group">
                                <label class="required">
                                    <i class="fas fa-dog"></i>
                                    Espécie
                                </label>
                                <select name="especie" id="p_especie" required onchange="carregarRacas()">
                                    <option value="">Selecione</option>
                                    <option value="Canina" <?= ($dados['especie'] ?? '') == 'Canina' ? 'selected' : '' ?>>Canina</option>
                                    <option value="Felina" <?= ($dados['especie'] ?? '') == 'Felina' ? 'selected' : '' ?>>Felina</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="required">
                                    <i class="fas fa-dna"></i>
                                    Raça
                                </label>
                                <select name="raca" id="p_raca" required>
                                    <option value="">Selecione a Espécie primeiro</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-venus-mars"></i>
                                    Sexo
                                </label>
                                <select name="sexo">
                                    <option value="Macho" <?= ($dados['sexo'] ?? '') == 'Macho' ? 'selected' : '' ?>>Macho</option>
                                    <option value="Fêmea" <?= ($dados['sexo'] ?? '') == 'Fêmea' ? 'selected' : '' ?>>Fêmea</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-birthday-cake"></i>
                                    Data de Nascimento
                                </label>
                                <input type="date" 
                                       name="data_nascimento" 
                                       value="<?= $dados['data_nascimento'] ?? '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-toggle-on"></i>
                                    Status
                                </label>
                                <select name="status">
                                    <option value="ATIVO" <?= ($dados['status'] ?? 'ATIVO') == 'ATIVO' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="INATIVO" <?= ($dados['status'] ?? '') == 'INATIVO' ? 'selected' : '' ?>>Inativo</option>
                                    <option value="OBITO" <?= ($dados['status'] ?? '') == 'OBITO' ? 'selected' : '' ?>>Óbito</option>
                                </select>
                            </div>

                            <div class="form-group full">
                                <label>
                                    <i class="fas fa-comment-alt"></i>
                                    Observações Adicionais
                                </label>
                                <textarea name="observacoes" 
                                          rows="4" 
                                          placeholder="Histórico breve, alergias, temperamento..."><?= $dados['observacoes'] ?? '' ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ABA 2: CARACTERÍSTICAS FÍSICAS -->
                    <div id="aba-fisicas" class="tab-pane">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-weight"></i>
                                    Peso (kg)
                                </label>
                                <input type="text" 
                                       name="peso" 
                                       value="<?= $dados['peso'] ?? '' ?>" 
                                       placeholder="0.00">
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-ruler-vertical"></i>
                                    Porte
                                </label>
                                <select name="porte">
                                    <option value="Pequeno" <?= ($dados['porte'] ?? '') == 'Pequeno' ? 'selected' : '' ?>>Pequeno</option>
                                    <option value="Médio" <?= ($dados['porte'] ?? '') == 'Médio' ? 'selected' : '' ?>>Médio</option>
                                    <option value="Grande" <?= ($dados['porte'] ?? '') == 'Grande' ? 'selected' : '' ?>>Grande</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-cut"></i>
                                    Pelagem
                                </label>
                                <input type="text" 
                                       name="pelagem" 
                                       value="<?= $dados['pelagem'] ?? '' ?>" 
                                       placeholder="Ex: Curta, Longa">
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-check-circle"></i>
                                    Castrado?
                                </label>
                                <select name="esterilizacao">
                                    <option value="Não" <?= ($dados['esterilizacao'] ?? 'Não') == 'Não' ? 'selected' : '' ?>>Não</option>
                                    <option value="Sim" <?= ($dados['esterilizacao'] ?? '') == 'Sim' ? 'selected' : '' ?>>Sim</option>
                                </select>
                            </div>

                            <div class="form-group half">
                                <label>
                                    <i class="fas fa-microchip"></i>
                                    Número do Microchip
                                </label>
                                <input type="text" 
                                       name="chip" 
                                       value="<?= $dados['chip'] ?? '' ?>" 
                                       placeholder="Opcional">
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- BOTÕES DE AÇÃO -->
            <div class="form-actions">
                <button type="button" onclick="salvarPaciente()" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Paciente
                </button>
                <a href="listar_pacientes.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // SISTEMA DE ABAS
        function trocarAba(event, abaId) {
            event.preventDefault();
            
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            
            event.currentTarget.classList.add('active');
            document.getElementById(abaId).classList.add('active');
        }
        
        // RAÇAS COMPLETAS
        const RACAS_COMPLETO = {
            "Canina": ["Affenpinscher", "Afghan Hound", "Airedale Terrier", "Akita", "Akita Americano", "Alano Espanhol", "Alaskan Husky", "Alaskan Malamute", "American Bulldog", "American Eskimo", "American Foxhound", "American Hairless Terrier", "American Pit Bull Terrier", "American Staffordshire Terrier", "American Water Spaniel", "Anatolian Shepherd Dog", "Appenzeller Sennenhund", "Australian Cattle Dog", "Australian Kelpie", "Australian Shepherd", "Australian Silky Terrier", "Australian Terrier", "Azawakh", "Bassenji", "Basset Azul da Gasconha", "Basset Hound", "Basset Fulvo da Bretanha", "Beagle", "Beagle Harrier", "Bearded Collie", "Bedlington Terrier", "Belgian Malinois", "Belgian Sheepdog", "Belgian Tervuren", "Bernese Mountain Dog", "Bichon Frisé", "Bloodhound", "Boerboel", "Border Collie", "Border Terrier", "Borzoí", "Boston Terrier", "Bouvier des Flandres", "Boxer", "Boykin Spaniel", "Braco Alemão", "Braco Italiano", "Briard", "Brittany Spaniel", "Bull Terrier", "Bulldog", "Bulldog Francês", "Bulldog Inglês", "Bullmastiff", "Cairn Terrier", "Cane Corso", "Cão d'Água Português", "Cão da Montanha dos Pirineus", "Cão Lobo Tchecoslovaco", "Cavalier King Charles Spaniel", "Chesapeake Bay Retriever", "Chihuahua", "Chinese Crested", "Chow Chow", "Cocker Spaniel Americano", "Cocker Spaniel Inglês", "Collie", "Corgi Cardigan", "Corgi Pembroke", "Coton de Tulear", "Dachshund", "Dálmata", "Dandie Dinmont Terrier", "Doberman", "Dogo Argentino", "Dogue Alemão", "Dogue de Bordeaux", "Elkhound Norueguês", "Estrela da Montanha", "Fila Brasileiro", "Flat-Coated Retriever", "Fox Terrier", "Foxhound Inglês", "Galgo Espanhol", "Galgo Irlandês", "Golden Retriever", "Gordon Setter", "Greyhound", "Grifo da Vendéia", "Havanese", "Husky Siberiano", "Irish Setter", "Irish Terrier", "Irish Wolfhound", "Jack Russell Terrier", "Japanese Chin", "Keeshond", "Komondor", "Kuvasz", "Labrador Retriever", "Lakeland Terrier", "Lhasa Apso", "Malamute do Alasca", "Maltês", "Mastiff", "Mastim Espanhol", "Mastim Inglês", "Mastim Napolitano", "Mudi", "Papillon", "Pastor Alemão", "Pastor Australiano", "Pastor Belga", "Pastor Branco Suíço", "Pastor de Shetland", "Pastor Maremano Abruzês", "Pequinês", "Perdigueiro Português", "Pinscher Miniatura", "Pit Bull", "Pointer Inglês", "Poodle", "Pug", "Rafeiro do Alentejo", "Rottweiler", "Saluki", "Samoieda", "São Bernardo", "Schipperke", "Schnauzer Gigante", "Schnauzer Miniatura", "Schnauzer Standard", "Setter Inglês", "Shar Pei", "Shiba Inu", "Shih Tzu", "Skye Terrier", "Spaniel Tibetano", "Spitz Alemão", "Spitz dos Visigodos", "Staffordshire Bull Terrier", "Terra Nova", "Terrier Escocês", "Terrier Irlandês", "Terrier Preto da Rússia", "Tosa Inu", "Valhund Sueco", "Weimaraner", "Welsh Corgi", "Whippet", "Xoloitzcuintli", "Yorkshire Terrier", "Vira-lata (SRD)"],
            "Felina": ["Abyssinian", "American Bobtail", "American Curl", "American Shorthair", "American Wirehair", "Angorá Turco", "Ashera", "Australian Mist", "Balinese", "Bambino", "Bengal", "Birman", "Bobtail Japonês", "Bombay", "British Longhair", "British Shorthair", "Burmês", "Burmilla", "California Spangled", "Chartreux", "Chausie", "Cingapura (Singapura)", "Colorpoint Shorthair", "Cornish Rex", "Cymric", "Devon Rex", "Donskoy", "Dragon Li", "Egípcio Mau", "Europeu Comum", "Exótico de Pelo Curto (Exotic Shorthair)", "Havana Brown", "Himalaio", "Khao Manee", "Korat", "LaPerm", "Lykoi", "Maine Coon", "Manx", "Mau Árabe", "Munchkin", "Nebelung", "Norueguês da Floresta", "Ocicat", "Ojos Azules", "Oriental", "Persa", "Peterbald", "Pixie-Bob", "Ragamuffin", "Ragdoll", "Russian Blue", "Savannah", "Scottish Fold", "Scottish Straight", "Selkirk Rex", "Serengeti", "Seychellois", "Siamês", "Siberiano", "Singapura", "Snowshoe", "Sokoke", "Somali", "Sphynx", "Thai", "Tiffanie", "Tonkines", "Toyger", "Ucrânia Levkoy", "Van Turco", "SRD (Vira-lata)"]
        };

        function carregarRacas() {
            const esp = document.getElementById('p_especie').value;
            const combo = document.getElementById('p_raca');
            const racaAtual = "<?= $dados['raca'] ?? '' ?>";
            
            combo.innerHTML = '<option value="">Selecione a Raça</option>';
            
            if(RACAS_COMPLETO[esp]) {
                RACAS_COMPLETO[esp].sort().forEach(r => {
                    let opt = document.createElement('option');
                    opt.value = r; 
                    opt.text = r;
                    if(r === racaAtual) opt.selected = true;
                    combo.appendChild(opt);
                });
            }
        }
        
        // Carrega ao iniciar
        window.onload = carregarRacas;
        
        // SALVAR PACIENTE
        async function salvarPaciente() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            btn.disabled = true;

            const formData = new FormData(document.getElementById('formPaciente'));
            
            try {
                const res = await fetch('pacientes.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Sucesso!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#622599'
                    }).then(() => {
                        window.location.href = 'listar_pacientes.php';
                    });
                } else {
                    Swal.fire({
                        title: 'Erro!',
                        text: data.message,
                        icon: 'error',
                        confirmButtonColor: '#622599'
                    });
                }
            } catch (e) {
                Swal.fire({
                    title: 'Erro!',
                    text: 'Falha de conexão com o servidor.',
                    icon: 'error',
                    confirmButtonColor: '#622599'
                });
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>