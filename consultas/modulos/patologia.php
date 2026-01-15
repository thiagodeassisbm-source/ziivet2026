<div class="secao-titulo"><i class="fas fa-virus"></i> Registro de Patologia</div>

<form id="formPatologia" method="POST" action="consultas/processar_realizar_consulta.php">
    <input type="hidden" name="salvar_patologia" value="1">
    <input type="hidden" name="id_paciente" value="<?= $dados_paciente['id'] ?>">

    <div class="form-row">
        <div class="form-group">
            <label>Patologia <span class="req">*</span></label>
            <select id="select-patologia" name="patologia_nome" onchange="carregarProtocolo()" required>
                <option value="">Selecione...</option>
                <option value="Cinomose">Cinomose</option>
                <option value="Coronavirus">Coronavirus</option>
                <option value="Hepatite Contagiosa Canina">Hepatite Contagiosa Canina</option>
                <option value="Leptospirose">Leptospirose</option>
                <option value="Parvovirose">Parvovirose</option>
                <option value="Raiva">Raiva</option>
                <option value="Tosse Canina">Tosse Canina</option>
                <option value="Giardíase">Giardíase</option>
                <option value="Sarna">Sarna</option>
                <option value="Leishmaniose">Leishmaniose</option>
                <option value="Erliquiose">Erliquiose</option>
            </select>
        </div>
        <div class="form-group">
            <label>Data <span class="req">*</span></label>
            <input type="date" name="data_registro" value="<?= date('Y-m-d') ?>" required>
        </div>
    </div>

    <div class="form-group">
        <label>Protocolo</label>
        <textarea id="protocolo-texto" name="protocolo_descricao" rows="15"></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-acao btn-salvar"><i class="fas fa-save"></i> Salvar</button>
        <a href="consultas/realizar_consulta.php?id_paciente=<?= $dados_paciente['id'] ?>" class="btn-acao btn-cancelar"><i class="fas fa-times"></i> Cancelar</a>
    </div>
</form>

<script>
function carregarProtocolo() {
    const patologia = document.getElementById('select-patologia').value;
    const textarea = document.getElementById('protocolo-texto');
    
    const protocolos = {
        "Cinomose": `Os cães morrem de cinomose do que de outra doença infecciosa. Esse é um vírus altamente contagioso que se espalha pelo contato direto ou através do ar. Um cão saudável e forte pode sobreviver à cinomose, normalmente com sintomas relativamente brandos. Por outro lado, se o sistema imunológico de seu cão não tem resistência, todo seu corpo pode ser dominado pelo vírus, bem como bactérias que se aproveitam para causar infecções secundárias.

A cinomose geralmente acontece em dois estágios. Três a quinze dias após a exposição ao vírus, o cão desenvolve uma febre, não quer comer, não tem energia e seus olhos e nariz começam a gotejar. Conforme o tempo passa, a descarga de seus olhos e nariz começa a ficar espessa, amarela e pegajosa - o clássico sinal de cinomose. Se você não levou seu cão ao veterinário antes deste sintoma aparecer, você deve levá-lo agora.

Outros sinais do primeiro estágio da cinomose são tosse seca, diarréia e bolhas de pus no estômago. O segundo estágio da cinomose é ainda mais grave, pois a doença pode começar a afetar o cérebro e até a espinha dorsal.

Um cão neste estágio pode babar frequentemente, sacudir sua cabeça ou agir como se estivesse com um gosto ruim na boca. Às vezes tem convulsões, fazendo com que ande em círculos, caia e chute o ar. Mais tarde, parece confuso, andando a esmo e se encolhendo frente às pessoas.

Infelizmente, quando a doença chega até aqui, não há muita esperança de sobrevivência para o cão. Os cães que sobrevivem frequentemente têm danos neurológicos (cérebro e nervos) permanentes.

A cinomose também pode se espalhar para os pulmões, causando pneumonia, conjuntivite e passagens nasais inflamadas (rinite); também pode se espalhar para a pele, fazendo-a engrossar, especialmente na planta dos pés. Essa forma de cinomose é chamada de doença da pata grossa.

A cinomose tem mais probabilidade de atacar cães filhotes de nove a doze semanas de idade, especialmente se vierem de um ambiente com muitos outros cães (abrigo de animais, loja de animais, canis de criação).

Se seu cão foi diagnosticado como portador de cinomose, seu veterinário lhe dará fluidos intravenosos para substituir o que ele perdeu, medicamentos para controlar a diarréia e o vômito e antibióticos para combater infecções secundárias.`,

        "Coronavirus": `Uma doença geralmente branda, o coronavírus é disseminado quando um cão entra em contato com as fezes ou outras excreções de cães infectados. Embora raramente mate os cães, o coronavírus pode ser especialmente difícil em filhotes ou cães que estão estressados ou que não estejam no melhor de sua saúde.

Suspeite do coronavírus se seu cão estiver deprimido, não quiser comer, vomitar - especialmente se for com sangue - e tenha um episódio ruim de diarréia. Excepcionalmente, fezes com cheiro forte, particularmente se forem com sangue ou uma estranha coloração amarelo-laranja, também são sinais.

Se o coronavírus for diagnosticado, o veterinário recomendará para seu cão abundância de fluidos para substituir o que foi perdido pelo vômito e diarréia, bem como a medicação para ajudar a manter o vômito e a diarréia no mínimo. Uma vacina contra o coronavírus normalmente é recomendada se o seu cão estiver encontrando muitos outros cães - ou seus excrementos - em parques, exposições de cães, canis e outras instalações de reunião.`,

        "Hepatite Contagiosa Canina": `Essa é uma doença viral espalhada por contato direto. Os casos brandos duram somente um ou dois dias, com o cão sofrendo uma febre branda e tendo baixa contagem de células sanguíneas brancas.

Filhotes muito jovens, de duas a seis semanas de idade, podem sofrer de uma forma da doença que surge rapidamente. Eles têm uma febre, as amígdalas ficam inchadas e seus estômagos doem. Muito rapidamente eles podem entrar em choque e morrer. O ataque é rápido e inesperado: o filhote pode estar bem em um dia e entrar em choque no seguinte. A forma mais comum de hepatite infecciosa canina ocorre em filhotes quando têm de seis a dez semanas de idade. Eles mostram os sinais usuais de febre, falta de energia e amígdalas inchadas e linfonodos.

Um cão cujo sistema imunológico responde bem começa a se recuperar em quatro a sete dias. Em casos graves, contudo, o vírus ataca as paredes dos vasos sanguíneos e o cão começa a sangrar pela boca, nariz, reto e aparelho urinário. Se seu filhote tem hepatite infecciosa, irá precisar de fluidos intravenosos, antibióticos e pode até mesmo precisar de uma transfusão de sangue.`,

        "Leptospirose": `Essa doença bacteriana é causada por um espiroqueta, que é um tipo de bactéria com uma forma espiral estreita. O espiroqueta da leptospirose é passado na urina de animais infectados e entra no corpo do cão através de uma ferida aberta na pele ou quando ele come ou bebe algo contaminado pela urina infecciosa.

Os sinais da leptospirose não são bonitos. Os sintomas iniciais incluem febre, depressão, letargia e perda de apetite. Normalmente, a leptospirose ataca os rins, portanto um cão infectado pode andar todo encurvado pois seus rins doem. Conforme a infecção avança, aparecem úlceras em sua boca e língua, e sua língua fica com uma cobertura marrom espessa. Dói comer porque sua boca está cheia de feridas e pode até mesmo estar sangrando. Suas fezes contêm sangue, e ele tem muita sede, portanto bebe muita água. Acima de tudo isso, ele provavelmente está vomitando e com diarréia.

O tratamento da leptospirose requer hospitalização devido a algumas razões. Primeiro, além de precisar de antibióticos para combater as bactérias e outros medicamentos para controlar o vômito e a diarréia, um cão com sintomas avançados terá perdido muito fluido e precisará repô-los. Segundo, a leptospirose é uma zoonose, o que significa que pode se espalhar para pessoas. Os cães com leptospirose devem ser manejados cuidadosamente para evitar infecção. Mesmo que seu cão se recupere, ele ainda pode ser um portador por até um ano. Seu veterinário pode aconselhá-lo sobre como evitar infecção depois que ele estiver bem.`,

        "Parvovirose": `Uma doença altamente contagiosa, a parvovirose pode se espalhar através das patas, pêlo, saliva e fezes de um cão infectado. Também pode ser transportado nos sapatos das pessoas e em caixas ou camas usadas por cães infectados. Os filhotes com menos de cinco meses são especialmente atingidos de forma dura pela parvovirose e estão mais propensos a morrer. Dobermanns, Pinchers, Rottweilers e Pitbulls são especialmente suscetíveis à parvovirose.

Os sinais da parvovirose começam a aparecer de três a quatorze dias após um cão ter sido exposto a ela. A parvovirose pode assumir duas formas: a forma mais comum é caracterizada por diarréia aguda, e a outra forma rara por dano ao músculo cardíaco.

Um cão com parvovirose é literalmente um filhote doente. Se a doença afetar seus intestinos, ele ficará gravemente deprimido com vômito, dor abdominal, febre alta, diarréia hemorrágica e falta de apetite. Poucas doenças causam essa ampla variedade de sintomas graves. Quando a parvo ataca o coração, os jovens filhotes param mamar e têm problemas em respirar. Normalmente eles morrem rapidamente, mas até mesmo quando se recuperam estão propensos a ter falha cardíaca congestiva, o que eventualmente os mata.

Existem vacinas disponíveis contra a parvovirose, mas entre seis semanas e cinco meses de idade, os filhotes estão especialmente vulneráveis à doença, mesmo se foram vacinados. A razão é complicada. Veja bem, no nascimento, os filhotes obtêm suas imunidades passivamente, através do leite da mãe. Quaisquer que sejam as doenças que a mãe tenha tido ou contra as quais tenha sido vacinada, os filhotes obtêm proteção também. O efeito desses anticorpos maternais desvanece após o desmame mais ainda pode ser forte o suficiente para interferir com a ação da vacina contra parvovirose. Com nenhum tipo de proteção em plena força, o vírus consegue passar. Ainda assim, isso não significa que você deve deixar de vacinar um filhote contra a parvo - dois tipos de proteção com menos da força total é melhor que apenas uma ou nenhuma.`,

        "Raiva": `Harper Lee certamente poderia nos contar uma história. Sua descrição de um cão com raiva no livro vencedor do Prêmio Pulitzer "To Kill a Mockingbird" não só é medicalmente preciso, ela transporta todo o medo e perigo dessa doença fatal. Claro, ela dificilmente foi a primeira a escrever sobre isso: a raiva é conhecida por milhares de anos e é mencionada nas tábuas legais da Mesopotâmia e nos escritos de Aristóteles e Xenofonte. Algumas áreas do mundo - notavelmente a Austrália, Grã-Bretanha, Islândia, Japão e nações escandinavas - governaram para a eliminação da raiva através de quarentenas estritas em animais que chegavam, mas ela é encontrada em qualquer lugar do mundo.

O vírus da raiva entra no corpo através de uma ferida aberta, normalmente na saliva deixada durante uma mordida. Ela pode infectar e matar qualquer animal de sangue quente, incluindo seres humanos. Dependendo da área do país, os animais selvagens mais propensos a transmitir a raiva são guaxinins, gambás, morcegos e raposas. Em 2004, de um total de 6.844 casos relatados de raiva, 94 casos foram relatados em cães e 281 em gatos.

A raiva assume duas formas. Uma é descrita como furiosa e a outra é chamada de paralítica. A raiva paralítica normalmente é o estágio final, terminando em morte. Um cão no estágio furioso da raiva, que pode durar de um a sete dias, atravessa vários comportamentos. Ele pode ficar agitado ou nervoso, cruel, excitável e sensível à luz e ao toque. Sua respiração torna-se pesada e rápida, fazendo-o espumar pela boca. Outro sinal da raiva é a "mudança de personalidade". Por exemplo, um cão amigável pode se tornar retraído e mordedor, ou um cão tímido pode se tornar muito mais amigável que o normal. Conforme o vírus da raiva faz o seu trabalho no sistema nervoso central, o animal tem dificuldade para andar e se movimentar. Assim como não é bom se aproximar de qualquer animal ou cachorro estranho, nunca tente se aproximar de um que esteja se comportando atípico ou tendo dificuldade. Você deve ser extremamente cauteloso perto de qualquer animal que você saiba estar agindo estranhamente.

Como a raiva é fatal, os veterinários da saúde pública recomendam a eutanásia de qualquer animal com sinal de raiva que tenha mordido alguém. Um cão que pareça saudável, mas tenha mordido alguém deve ser mantido confinado por dez dias para ver se os sinais de raiva se desenvolvem. Um cão não-vacinado que tenha sido exposto à raiva deve ser submetido à eutanásia ou estritamente confinado por seis meses, recebendo uma vacina contra raiva um mês antes de ser liberado da quarentena. Se um cão vacinado for exposto à raiva, ele deve receber uma dose de reforço imediatamente, ser confinado e observado atentamente por 90 dias. Infelizmente, a única forma infalível de confirmar se um cão tem raiva é examinar seu cérebro (especificamente, o tecido de seu sistema nervoso central) - o que significa que o cão não pode estar vivo. Se você tem um cão ou gato que morre repentinamente - particularmente após mostrar comportamento incomum - chame seu veterinário imediatamente para ver se é necessário investigar a existência de raiva no animal.`,

        "Tosse Canina": `Esta é uma infecção respiratória comum em qualquer situação onde muitos cães são mantidos juntos, como canis, abrigos de animais e lojas de animais de estimação.

A infecção faz com que a traquéia, a laringe (caixa de voz) e os brônquios (os pequenos tubos ramificados nos pulmões) fiquem inflamados. Sucumbindo à bactéria Bordetella bronchiseptica, um cão infectado desenvolverá uma tosse de branda a grave, algumas vezes com um nariz escorrendo, de cinco a dez dias após a exposição.

Pode ser tratada com antibióticos e abundância de repouso, o que é muito importante. A prevenção é a escolha mais sensata e humana. Se você planeja hospedar seu cão ou vai expô-lo a muitos outros cães, certifique-se de que ele está protegido contra a Bordetella.

O "golpe duplo" é geralmente uma boa estratégia: uma vacina líquida administrada através do nariz do cão combinada com uma injeção para o vírus parainfluenza canino.`,

        "Giardíase": `Giardíase é uma doença intestinal causada por um parasita protozoário chamado Giardia.

O parasita vive no intestino delgado do cão e causa diarréia, às vezes grave, com fezes gordurosas e mal-cheirosas.

O tratamento inclui antiparasitários específicos como metronidazol ou fenbendazol.`,

        "Sarna": `Sarna é uma infecção de pele causada por ácaros.

Existem diferentes tipos de sarna: a sarna sarcóptica (altamente contagiosa) causa coceira intensa, e a sarna demodécica (não contagiosa) causa perda de pelo em áreas localizadas.

O tratamento varia conforme o tipo, podendo incluir banhos medicamentosos, ivermectina ou outros antiparasitários.`,

        "Leishmaniose": `Leishmaniose é uma doença parasitária transmitida por insetos (flebótomos/mosquito-palha).

O parasita afeta a pele e órgãos internos, causando lesões cutâneas, perda de peso, aumento de linfonodos e problemas renais.

O tratamento é longo e pode incluir alopurinol e antimoniais. A prevenção inclui coleiras repelentes e vacinação.`,

        "Erliquiose": `Erliquiose é uma doença transmitida por carrapatos causada pela bactéria Ehrlichia canis.

A doença pode ser aguda (febre, letargia, perda de apetite) ou crônica (anemia, sangramentos, perda de peso).

O diagnóstico é feito por exames de sangue. O tratamento inclui doxiciclina por 28 dias e controle de carrapatos.`
    };
    
    textarea.value = protocolos[patologia] || '';
}
</script>