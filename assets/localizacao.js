// Sugestões de cidades pelo IBGE nos campos de localização (cadastro e Minha conta).
// Ao escolher o estado, busca os municípios dele e preenche o <datalist> do campo Cidade.
// A lista fica guardada no navegador por 30 dias. Se o IBGE não responder, o campo
// Cidade continua aceitando qualquer texto (nada quebra e nenhum erro aparece).
(function () {
    var API = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/{UF}/municipios';
    var VALIDADE = 30 * 24 * 60 * 60 * 1000; // 30 dias
    var ESPERA_MAX = 8000;                    // desiste do IBGE depois de 8 segundos

    function lerCache(uf) {
        try {
            var c = JSON.parse(localStorage.getItem('ibge-cidades-' + uf) || 'null');
            if (c && Date.now() - c.quando < VALIDADE && c.cidades && c.cidades.length) return c.cidades;
        } catch (e) { /* cache inválido ou bloqueado: busca de novo */ }
        return null;
    }

    function salvarCache(uf, cidades) {
        try {
            localStorage.setItem('ibge-cidades-' + uf, JSON.stringify({ quando: Date.now(), cidades: cidades }));
        } catch (e) { /* sem espaço ou navegação privada: segue sem cache */ }
    }

    function buscar(uf) {
        var guardadas = lerCache(uf);
        if (guardadas) return Promise.resolve(guardadas);
        if (!window.fetch) return Promise.resolve([]);

        var controle = window.AbortController ? new AbortController() : null;
        var tempo = setTimeout(function () { if (controle) controle.abort(); }, ESPERA_MAX);
        return fetch(API.replace('{UF}', uf), controle ? { signal: controle.signal } : {})
            .then(function (r) { if (!r.ok) throw new Error('IBGE ' + r.status); return r.json(); })
            .then(function (lista) {
                var cidades = lista.map(function (m) { return m.nome; })
                    .sort(function (a, b) { return a.localeCompare(b, 'pt-BR'); });
                if (cidades.length) salvarCache(uf, cidades);
                return cidades;
            })
            .catch(function () { return []; }) // IBGE fora do ar: o campo continua como texto livre
            .finally(function () { clearTimeout(tempo); });
    }

    document.querySelectorAll('[data-local]').forEach(function (bloco) {
        var seletor = bloco.querySelector('[data-local-uf]');
        var cidade  = bloco.querySelector('[data-local-cidade]');
        var lista   = document.getElementById(cidade.getAttribute('list'));
        var pedido  = 0;

        function atualizar() {
            var uf = seletor.value;
            var este = ++pedido;
            lista.innerHTML = '';
            if (!uf) return;
            buscar(uf).then(function (cidades) {
                if (este !== pedido) return; // trocou de estado enquanto buscava
                var frag = document.createDocumentFragment();
                cidades.forEach(function (nome) {
                    var op = document.createElement('option');
                    op.value = nome;
                    frag.appendChild(op);
                });
                lista.appendChild(frag);
            });
        }

        seletor.addEventListener('change', atualizar);
        atualizar();
    });
})();
