/**
 * Checagem por fotos (tela mobile). Cada card é um item: escolher a foto,
 * comprimir no navegador (canvas, JPEG, lado maior 1600px), responder
 * conforme/anomalia e enviar por fetch. O botão Concluir só libera quando
 * nenhum item está pendente.
 */
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const progresso = document.getElementById('chk-progresso');
    const btnConcluir = document.getElementById('chk-btn-concluir');

    function atualizarProgresso(pendentes) {
        progresso.dataset.pendentes = pendentes;
        progresso.textContent = pendentes > 0 ? `Faltam ${pendentes} item(ns).` : 'Todos os itens respondidos. Preencha os dados finais e conclua.';
        btnConcluir.disabled = pendentes > 0;
        if (pendentes === 0) document.getElementById('chk-concluir').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function comprimir(arquivo) {
        if (!arquivo.type.startsWith('image/')) return arquivo;
        const bitmap = await createImageBitmap(arquivo).catch(() => null);
        if (!bitmap) return arquivo;

        const max = 1600;
        const escala = Math.min(1, max / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * escala);
        canvas.height = Math.round(bitmap.height * escala);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', 0.82));
        return blob ? new File([blob], arquivo.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : arquivo;
    }

    document.querySelectorAll('.chk-item').forEach((card) => {
        const input = card.querySelector('.chk-arquivo');
        const preview = card.querySelector('.chk-preview');
        const placeholder = card.querySelector('.chk-placeholder');
        const observacao = card.querySelector('.chk-observacao');
        const erro = card.querySelector('.chk-erro');
        const status = card.querySelector('.chk-status');
        const badge = card.querySelector('.chk-badge');
        const botoes = card.querySelectorAll('.chk-resposta');
        let arquivo = null;
        let resposta = card.querySelector('.chk-resposta.btn-success') ? 'conforme' : (card.querySelector('.chk-resposta.btn-danger') ? 'anomalia' : null);

        input.addEventListener('change', async () => {
            if (!input.files[0]) return;
            erro.classList.add('d-none');
            arquivo = await comprimir(input.files[0]);
            preview.src = URL.createObjectURL(arquivo);
            preview.classList.remove('d-none');
            placeholder.classList.add('d-none');
            if (resposta) enviar();
        });

        botoes.forEach((b) => b.addEventListener('click', () => {
            resposta = b.dataset.valor;
            botoes.forEach((x) => {
                const ok = x.dataset.valor === 'conforme';
                x.classList.toggle(ok ? 'btn-success' : 'btn-danger', x === b);
                x.classList.toggle(ok ? 'btn-outline-success' : 'btn-outline-danger', x !== b);
            });
            observacao.classList.toggle('d-none', resposta !== 'anomalia');
            if (resposta === 'anomalia' && !observacao.value) { observacao.focus(); return; }
            enviar();
        }));

        observacao.addEventListener('change', () => { if (resposta === 'anomalia') enviar(); });

        async function enviar() {
            if (!resposta) return;
            if (!arquivo && preview.classList.contains('d-none')) { mostrarErro('Tire a foto primeiro.'); return; }
            if (resposta === 'anomalia' && !observacao.value.trim()) { mostrarErro('Descreva a anomalia.'); return; }
            // Sem arquivo novo mas com foto já enviada: só reenvia se houver arquivo.
            if (!arquivo) { mostrarErro('Para mudar a resposta, tire a foto novamente.'); return; }

            const dados = new FormData();
            dados.append('foto', arquivo);
            dados.append('situacao', resposta);
            dados.append('observacao', observacao.value);

            status.classList.remove('d-none');
            erro.classList.add('d-none');
            try {
                const resp = await fetch(card.dataset.url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: dados,
                });
                const json = await resp.json();
                if (!resp.ok || !json.ok) {
                    const msg = json.erro || (json.errors ? Object.values(json.errors).flat().join(' ') : 'Falha ao enviar.');
                    mostrarErro(msg);
                    return;
                }
                arquivo = null;
                preview.src = json.foto_url;
                badge.textContent = json.situacao === 'anomalia' ? 'Anomalia' : 'Conforme';
                badge.className = 'badge badge-situacao chk-badge ' + (json.situacao === 'anomalia' ? 'badge-inativo' : 'badge-ativo');
                card.classList.add('border-success');
                atualizarProgresso(json.pendentes);
            } catch (e) {
                mostrarErro('Sem conexão. Tente novamente.');
            } finally {
                status.classList.add('d-none');
            }
        }

        function mostrarErro(msg) { erro.textContent = msg; erro.classList.remove('d-none'); }
    });
})();
