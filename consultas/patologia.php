<?php
/**
 * =========================================================================================
 * ZIIPVET - SISTEMA DE GESTÃO VETERINÁRIA PROFISSIONAL
 * MÓDULO: REGISTRO DE PATOLOGIAS (LAUDOS PADRONIZADOS)
 * VERSÃO: V16.2 - LAYOUT PADRONIZADO E FAIXA CORRIGIDA
 * =========================================================================================
 */

require_once '../auth.php';
require_once '../config/configuracoes.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==============================================================
// 1. BLOCO DE PROCESSAMENTO E CARREGAMENTO (TABELA patologias)
// ==============================================================
$msg_feedback = "";
$dados_existentes = null;

// Verificar se foi passado um ID para Visualização/Edição
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM patologias WHERE id = :id");
    $stmt->execute([':id' => $_GET['id']]);
    $dados_existentes = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Processar o salvamento quando o formulário é enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $usuario_responsavel = $_SESSION['usuario_nome'] ?? 'Veterinário';
        
        if (isset($_POST['id_registro']) && !empty($_POST['id_registro'])) {
            // UPDATE: Se já existe um ID
            $sql = "UPDATE patologias SET 
                        id_paciente = :paciente, 
                        nome_doenca = :doenca, 
                        protocolo_descricao = :protocolo, 
                        data_registro = :data 
                    WHERE id = :id";
            $params = [
                ':id'        => $_POST['id_registro'],
                ':paciente'  => $_POST['id_paciente'],
                ':doenca'    => $_POST['patologia_nome'],
                ':protocolo' => $_POST['protocolo_descricao'],
                ':data'      => $_POST['data_registro'] . ' ' . date('H:i:s')
            ];
        } else {
            // INSERT: Novo registro na tabela patologias
            $sql = "INSERT INTO patologias (id_paciente, nome_doenca, protocolo_descricao, data_registro, usuario_responsavel) 
                    VALUES (:paciente, :doenca, :protocolo, :data, :usuario)";
            $params = [
                ':paciente'  => $_POST['id_paciente'],
                ':doenca'    => $_POST['patologia_nome'],
                ':protocolo' => $_POST['protocolo_descricao'],
                ':data'      => $_POST['data_registro'] . ' ' . date('H:i:s'),
                ':usuario'   => $usuario_responsavel
            ];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Redirecionamento para evitar reenvio de formulário e mostrar sucesso
        header("Location: listar_patologias.php?msg=sucesso");
        exit;
        
    } catch (PDOException $e) {
        $msg_feedback = "Erro ao processar: " . $e->getMessage();
    }
}

// Buscar lista de Clientes/Pacientes para o Select
try {
    $query_pacientes = "SELECT p.id as id_paciente, p.nome_paciente, c.nome as nome_cliente 
                        FROM pacientes p 
                        INNER JOIN clientes c ON p.id_cliente = c.id 
                        ORDER BY c.nome ASC";
    $lista_pacientes = $pdo->query($query_pacientes)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}

$titulo_pagina = $dados_existentes ? "Visualizar Patologia" : "Registrar Patologia";
$usuario_logado = $_SESSION['usuario_nome'] ?? 'veterinário';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> | ZiipVet</title>
    
    <base href="https://www.lepetboutique.com.br/app/">

    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { 
            --fundo: #ecf0f5; 
            --texto-dark: #333; 
            --primaria: #1c329f; 
            --sucesso: #28a745; 
            --borda: #d2d6de;
            --radius: 8px;
            --sidebar-collapsed: 75px; 
            --sidebar-expanded: 260px; 
            --header-height: 80px;
            --transition-speed: 0.4s;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Source Sans Pro', sans-serif; 
            background-color: var(--fundo); 
            color: var(--texto-dark); 
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* SIDEBAR E TOP-HEADER COM POSICIONAMENTO FIXO (PADRÃO V16.2) */
        aside.sidebar-container { 
            position: fixed; left: 0; top: 0; height: 100vh; 
            width: var(--sidebar-collapsed); z-index: 1000; 
            transition: width var(--transition-speed); 
            background: #fff;
        }
        aside.sidebar-container:hover { width: var(--sidebar-expanded); }

        header.top-header { 
            position: fixed; top: 0; left: var(--sidebar-collapsed); right: 0; 
            height: var(--header-height); z-index: 900; 
            transition: left var(--transition-speed); margin: 0 !important; 
        }
        aside.sidebar-container:hover ~ header.top-header { left: var(--sidebar-expanded); }

        main.main-content { 
            margin-left: var(--sidebar-collapsed); 
            padding: calc(var(--header-height) + 30px) 25px 30px; 
            transition: margin-left var(--transition-speed); 
        }
        aside.sidebar-container:hover ~ main.main-content { margin-left: var(--sidebar-expanded); }

        .faixa-superior { width: 100% !important; margin: 0 !important; border-radius: 0 0 0 30px !important; }

        /* Estilo do Card do Formulário */
        .card-patologia { 
            background: #fff; padding: 35px; border-radius: 12px; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 3px solid #6f42c1;
            max-width: 1100px; margin: 0 auto;
        }
        
        .header-info h3 { color: #6f42c1; font-size: 24px; font-weight: 700; display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
        .header-info p { font-size: 14px; color: #888; margin-bottom: 25px; }

        .form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
        label { font-size: 13px; font-weight: 700; color: #555; text-transform: uppercase; margin-left: 2px; }

        select, input, textarea { 
            width: 100%; padding: 12px 15px; border: 1px solid var(--borda); 
            border-radius: 4px; font-size: 15px; outline: none; background: #fff; transition: 0.2s;
        }
        select:focus, input:focus, textarea:focus { border-color: var(--primaria); }

        textarea { min-height: 450px; line-height: 1.6; resize: vertical; font-family: inherit; }

        .footer-actions { display: flex; gap: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #f4f4f4; }
        
        .btn-acao { 
            padding: 14px 28px; border-radius: 4px; font-weight: 600; font-size: 14px; 
            cursor: pointer; border: none; display: flex; align-items: center; gap: 10px; 
            text-transform: uppercase; transition: 0.2s; text-decoration: none;
        }
        .btn-salvar { background: var(--sucesso); color: #fff; }
        .btn-salvar:hover { background: #008d4c; }
        .btn-voltar { background: #f4f4f4; color: #555; border: 1px solid #ddd; }
        .btn-voltar:hover { background: #e7e7e7; }
    </style>
</head>
<body>

    <aside class="sidebar-container"><?php include '../menu/menulateral.php'; ?></aside>
    <header class="top-header"><?php include '../menu/faixa.php'; ?></header>

    <main class="main-content">
        <div class="card-patologia">
            <div class="header-info">
                <h3><i class="fas fa-microscope"></i> <?= $titulo_pagina ?></h3>
                <p>Médico Veterinário Responsável: <strong><?= htmlspecialchars($usuario_logado) ?></strong></p>
            </div>

            <form method="POST">
                <input type="hidden" name="id_registro" value="<?= $dados_existentes['id'] ?? '' ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Vincular ao Cliente / Paciente *</label>
                        <select name="id_paciente" required>
                            <option value="">Selecione o paciente...</option>
                            <?php foreach($lista_pacientes as $row): ?>
                                <option value="<?= $row['id_paciente'] ?>" <?= (isset($dados_existentes['id_paciente']) && $dados_existentes['id_paciente'] == $row['id_paciente']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['nome_cliente']) ?> (<?= htmlspecialchars($row['nome_paciente']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Data da Ocorrência *</label>
                        <input type="date" name="data_registro" value="<?= isset($dados_existentes['data_registro']) ? date('Y-m-d', strtotime($dados_existentes['data_registro'])) : date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Patologia Detectada *</label>
                    <select id="select-patologia" name="patologia_nome" onchange="carregarProtocolo()" required>
                        <option value="">Selecione a patologia...</option>
                        <option value="Cinomose" <?= (isset($dados_existentes['nome_doenca']) && $dados_existentes['nome_doenca'] == 'Cinomose') ? 'selected' : '' ?>>Cinomose</option>
                        <option value="Coronavirus" <?= (isset($dados_existentes['nome_doenca']) && $dados_existentes['nome_doenca'] == 'Coronavirus') ? 'selected' : '' ?>>Coronavirus</option>
                        <option value="Hepatite Contagiosa Canina" <?= (isset($dados_existentes['nome_doenca']) && $dados_existentes['nome_doenca'] == 'Hepatite Contagiosa Canina') ? 'selected' : '' ?>>Hepatite Contagiosa Canina</option>
                        <option value="Leptospirose" <?= (isset($dados_existentes['nome_doenca']) && $dados_existentes['nome_doenca'] == 'Leptospirose') ? 'selected' : '' ?>>Leptospirose</option>
                        <option value="Parvovirose" <?= (isset($dados_existentes['nome_doenca']) && $dados_existentes['nome_doenca'] == 'Parvovirose') ? 'selected' : '' ?>>Parvovirose</option>
                        <option value="Raiva" <?= (isset($dados_existentes['nome_doenca']) && $dados_existentes['nome_doenca'] == 'Raiva') ? 'selected' : '' ?>>Raiva</option>
                        <option value="Tosse Canina" <?= (isset($dados_existentes['nome_doenca']) && $dados_existentes['nome_doenca'] == 'Tosse Canina') ? 'selected' : '' ?>>Tosse Canina</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Protocolo de Tratamento / Descrição Clínica</label>
                    <textarea id="protocolo-texto" name="protocolo_descricao" placeholder="O protocolo padrão será carregado aqui após a seleção da doença..."><?= htmlspecialchars($dados_existentes['protocolo_descricao'] ?? '') ?></textarea>
                </div>

                <div class="footer-actions">
                    <button type="submit" class="btn-acao btn-salvar">
                        <i class="fas fa-save"></i> <?= $dados_existentes ? 'Atualizar Registro' : 'Salvar Patologia' ?>
                    </button>
                    <a href="consultas/listar_patologias.php" class="btn-acao btn-voltar">
                        <i class="fas fa-arrow-left"></i> Voltar ao Histórico
                    </a>
                </div>
            </form>
        </div>
    </main>

    <script>
        // Banco de Dados de Textos Padrões (Protocolos)
        const protocolos = {
            "Cinomose": "Os cães morrem de cinomose do que de outra doença infecciosa. Esse é um vírus altamente contagioso que se espalha pelo contato direto ou através do ar. Um cão saudável e forte pode sobreviver à cinomose, normalmente com sintomas relativamente brandos. Por outro lado, se o sistema imunológico de seu cão não tem resistência, todo seu corpo pode ser dominado pelo vírus, bem como bactérias que se aproveitam para causar infecções secundárias.\n\nA cinomose geralmente acontece em dois estágios. Três a quinze dias após a exposição ao vírus, o cão desenvolve uma febre, não quer comer, não tem energia e seus olhos e nariz começam a gotejar. Conforme o tempo passa, a descarga de seus olhos e nariz começa a ficar espessa, amarela e pegajosa - o clássico sinal de cinomose. Se você não levou seu cão ao veterinário antes deste sintoma aparecer, você deve levá-lo agora. Outros sinais do primeiro estágio da cinomose são tosse seca, diarréia e bolhas de pus no estômago. O segundo estágio da cinomose é ainda mais grave, pois a doença pode começar a afetar o cérebro e até a espinha dorsal. Uma cão neste estágio pode babar frequentemente, sacudir sua cabeça ou agir como se estivesse com um gosto ruim na boca. Às vezes tem convulsões, fazendo com que ande em círculos, caia e chute o ar. Mais tarde, parece confuso, andando a esmo e se encolhendo frente às pessoas.\n\nInfelizmente, quando a doença chega até aqui, não há muita esperança de sobrevivência para o cão. Os cães que sobrevivem freqüentemente têm danos neurológicos (cérebro e nervos) permanentes. A cinomose também pode se espalhar para os pulmões, causando pneumonia, conjuntivite e passagens nasais inflamadas (rinite); também pode se espalhar para a pele, fazendo-a engrossar, especialmente na planta dos pés. Essa forma de cinomose é chamada de doença da pata grossa. A cinomose tem mais probabilidade de atacar cães filhotes de nove a doze semanas de idade, especialmente se vierem de um ambiente com muitos outros cães (abrigo de animais, loja de animais, canis de criação). Se seu cão foi diagnosticado como portador de cinomose, seu veterinário lhe dará fluidos intravenosos para substituir o que ele perdeu, medicamentos para controlar a diarréia e o vômito e antibióticos para combater infecções secundárias.",
            "Coronavirus": "Uma doença geralmente branda, o coronavírus é disseminado quando um cão entra em contato com as fezes ou outras excreções de cães infectados. Embora raramente mate os cães, o coronavírus pode ser especialmente difícil em filhotes ou cães que estão estressados ou que não estejam no melhor de sua saúde.\nSuspeite do coronavírus se seu cão estiver deprimido, não quiser comer, vomitar - especialmente se for com sangue - e tenha um episódio ruim de diarréia. Excepcionalmente, fezes com cheiro forte, particularmente se forem com sangue ou uma estranha coloração amarelo-laranja, também são sinais.\nSe o coronavírus for diagnosticado, o veterinário recomendará para seu cão abundância de fluidos para substituir o que foi perdido pelo vômito e diarréia, bem como a medicação para ajudar a manter o vômito e a diarréia no mínimo. Uma vacina contra o coronavírus normalmente é recomendada se o seu cão estiver encontrando muitos outros cães - ou seus excrementos - em parques, exposições de cães, canis e outras instalações de reunião.",
            "Hepatite Contagiosa Canina": "Essa é uma doença viral espalhada por contato direto. Os casos brandos duram somente um ou dois dias, com o cão sofrendo uma febre branda e tendo baixa contagem de células sanguíneas brancas.\nFilhotes muito jovens, de duas a seis semanas de idade, podem sofrer de uma forma da doença que surge rapidamente. Eles têm uma febre, as amígdalas ficam inchadas e seus estômagos doem. Muito rapidamente eles podem entrar em choque e morrer. O ataque é rápido e inesperado: o filhote pode estar bem em um dia e entrar em choque no seguinte. A forma mais comum de hepatite infecciosa canina ocorre em filhotes quando têm de seis a dez semanas de idade. Eles mostram os sinais usuais de febre, falta de energia e amígdalas inchadas e linfonodos.\nUm cão cujo sistema imunológico responde bem começa a se recuperar em quatro a sete dias. Em casos graves, contudo, o vírus ataca as paredes dos vasos sanguíneos e o cão começa a sangrar pela boca, nariz, reto e aparelho urinário. Se seu filhote tem hepatite infecciosa, irá precisar de fluidos intravenosos, antibióticos e pode até mesmo precisar de uma transfusão de sangue.",
            "Leptospirose": "Essa doença bacteriana é causada por um espiroqueta, que é um tipo de bactéria com uma forma espiral estreita. O espiroqueta da leptospirose é passado na urina de animais infectados e entra no corpo do cão através de uma ferida aberta na pele ou quando ele come ou bebe algo contaminado pela urina infecciosa. Os sinais da leptospirose não são bonitos. Os sintomas iniciais incluem febre, depressão, letargia e perda de apetite. Normalmente, a leptospirose ataca os rins, portanto um cão infectado pode andar todo encurvado pois seus rins doem. Conforme a infecção avança, aparecem úlceras em sua boca e língua, e sua língua fica com uma cobertura marrom espessa. Dói comer porque sua boca está cheia de feridas e pode até mesmo estar sangrando. Suas fezes contêm sangue, e ele tem muita sede, portanto bebe muita água. Acima de tudo isso, ele provavelmente está vomitando e com diarréia.\n\nO tratamento da leptospirose requer hospitalização devido a algumas razões. Primeiro, além de precisar de antibióticos para combater as bactérias e outros medicamentos para controlar o vômito e a diarréia, um cão com sintomas avançados terá perdido muito fluido e precisará repô-los. Segundo, a leptospirose é uma zoonose, o que significa que pode se espalhar para pessoas. Os cães com leptospirose devem ser manejados cuidadosamente para evitar infecção. Mesmo que seu cão se recupere, ele ainda pode ser um portador por até um ano. Seu veterinário pode aconselhá-lo sobre como evitar infecção depois que ele estiver bem.",
            "Parvovirose": "Uma doença altamente contagiosa, a parvovirose pode se espalhar através das patas, pêlo, saliva e fezes de um cão infectado. Também pode ser transportado nos sapatos das pessoas e em caixas ou camas usadas por cães infectados. Os filhotes com menos de cinco meses são especialmente atingidos de forma dura pela parvovirose e estão mais propensos a morrer. Dobermanns, Pinchers, Rottweilers e Pitbulls são especialmente suscetíveis à parvovirose. Os sinais da parvovirose começam a aparecer de três a quatorze dias após um cão ter sido exposto a ela. A parvovirose pode assumir duas formas: a forma mais comum é caracterizada por diarréia aguda, e a outra forma rara por dano ao músculo cardíaco.\n\nUm cão com parvovirose é literalmente um filhote doente. Se a doença afetar seus intestinos, ele ficará gravemente deprimido com vômito, dor abdominal, febre alta, diarréia hemorrágica e falta de apetite. Poucas doenças causam essa ampla variedade de sintomas graves. Quando a parvo ataca o coração, os jovens filhotes param mamar e têm problemas em respirar. Normalmente eles morrem rapidamente, mas até mesmo quando se recuperam estão propensos a ter falha cardíaca congestiva, o que eventualmente os mata.\n\nA parvovirose é difícil de matar. O vírus pode durar de semanas a meses no ambiente. Se o seu cão teve parvo, desinfete completamente tudo o que ele entrou em contato, usando uma parte de alvejante de cloro misturado com 30 partes de água.",
            "Raiva": "O vírus da raiva entra no corpo através de uma ferida aberta, normalmente na saliva deixada durante uma mordida. Ela pode infectar e matar qualquer animal de sangue quente, incluindo seres humanos. A raiva assume duas formas. Uma é descrita como furiosa e a outra é chamada de paralítica. A raiva paralítica normalmente é o estágio final, terminando em morte. Um cão no estágio furioso da raiva, que pode durar de um a sete dias, atravessa vários comportamentos. Ele pode ficar agitado ou nervoso, cruel, excitável e sensível à luz e ao toque. Sua respiração torna-se pesada e rápida, fazendo-o espumar pela boca. Outro sinal da raiva é a \"mudança de personalidade\". Conforme o vírus da raiva faz o seu trabalho no sistema nervoso central, o animal tem dificuldade para andar e se movimentar.\n\nComo a raiva é fatal, os veterinários da saúde pública recomendam a eutanásia de qualquer animal com sinal de raiva que tenha mordido alguém. Um cão que pareça saudável, mas tenha mordido alguém deve ser mantido confinado por dez dias para ver se os sinais de raiva se desenvolvem.",
            "Tosse Canina": "Esta é uma infecção respiratória comum em qualquer situação onde muitos cães são mantidos juntos, como canis, abrigos de animais e lojas de animais de estimação. A infecção faz com que a traquéia, a laringe (caixa de voz) e os brônquios (os pequenos tubos ramificados nos pulmões) fiquem inflamados. Sucumbindo à bactéria Bordetella bronchiseptica, um cão infectado desenvolverá uma tosse de branda a grave, algumas vezes com um nariz escorrendo, de cinco a dez dias após a exposição. Pode ser tratada com antibióticos e abundância de repouso, o que é muito importante. A prevenção é a escolha mais sensível e humana. Se você planeja hospedar seu cão ou vai expô-lo a muitos outros cães, certifique-se de que ele está protegido contra a Bordetella."
        };

        function carregarProtocolo() {
            const select = document.getElementById('select-patologia');
            const textarea = document.getElementById('protocolo-texto');
            
            // Pergunta confirmação se já houver texto
            if (!textarea.value || confirm("Deseja substituir o texto atual pelo protocolo padrão desta doença?")) {
                textarea.value = protocolos[select.value] || "";
            }
        }

        window.onload = function() {
            // Verifica parâmetro de sucesso na URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('msg') === 'sucesso') {
                Swal.fire('Sucesso!', 'Registro de patologia processado com sucesso!', 'success');
            }
        };
    </script>
</body>
</html>