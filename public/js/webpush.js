// Web Push — registro do Service Worker, inscrição/desinscrição e UI de estado.
// Lê configuração de <meta> injetadas no layout apenas para usuários autenticados.
// Padrão do gestaoEmpresarial, adaptado ao Gestão de Pessoas.
(function () {
    'use strict';

    var meta = function (nome) {
        var el = document.querySelector('meta[name="' + nome + '"]');
        return el ? el.getAttribute('content') : null;
    };

    var chavePublica = meta('vapid-public-key');
    var urlInscrever = meta('webpush-inscrever-url');
    var urlDesinscrever = meta('webpush-desinscrever-url');
    var urlTestar = meta('webpush-testar-url');
    var csrf = meta('csrf-token');

    // Sem configuração (usuário não autenticado / VAPID ausente): não faz nada.
    if (!chavePublica || !urlInscrever) {
        return;
    }

    var suportado = ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);

    function urlBase64ParaUint8Array(base64) {
        var padding = '='.repeat((4 - (base64.length % 4)) % 4);
        var b64 = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(b64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) {
            out[i] = raw.charCodeAt(i);
        }
        return out;
    }

    function post(url, corpo) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: corpo ? JSON.stringify(corpo) : null,
        });
    }

    function registrarSW() {
        return navigator.serviceWorker.register('/sw.js');
    }

    function inscricaoAtual() {
        return navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription();
        });
    }

    function ativar() {
        return Notification.requestPermission().then(function (permissao) {
            if (permissao !== 'granted') {
                return Promise.reject({ tipo: permissao === 'denied' ? 'bloqueado' : 'nao_autorizado' });
            }
            return navigator.serviceWorker.ready.then(function (reg) {
                return reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ParaUint8Array(chavePublica),
                });
            }).then(function (sub) {
                var json = sub.toJSON();
                return post(urlInscrever, {
                    endpoint: sub.endpoint,
                    keys: { p256dh: json.keys.p256dh, auth: json.keys.auth },
                }).then(function (r) {
                    if (!r.ok) { return Promise.reject({ tipo: 'servidor' }); }
                });
            });
        });
    }

    function desativar() {
        return inscricaoAtual().then(function (sub) {
            if (!sub) { return; }
            var endpoint = sub.endpoint;
            return sub.unsubscribe().then(function () {
                return post(urlDesinscrever, { endpoint: endpoint });
            });
        });
    }

    // ── Interface (opcional; presente na página de Notificações) ──────────────
    var el = {
        wrap: document.getElementById('push-config'),
        status: document.getElementById('push-status'),
        ativar: document.getElementById('push-ativar'),
        desativar: document.getElementById('push-desativar'),
        testar: document.getElementById('push-testar'),
    };

    function definirStatus(texto, classe) {
        if (el.status) {
            el.status.textContent = texto;
            el.status.className = 'small ' + (classe || 'text-muted');
        }
    }

    function mostrar(elemento, visivel) {
        if (elemento) { elemento.classList.toggle('d-none', !visivel); }
    }

    function atualizarUI() {
        if (!el.wrap) { return; }

        if (!suportado) {
            definirStatus('Seu navegador não suporta notificações push (ou o acesso não é seguro/HTTPS).', 'text-warning');
            mostrar(el.ativar, false); mostrar(el.desativar, false); mostrar(el.testar, false);
            return;
        }
        if (Notification.permission === 'denied') {
            definirStatus('As notificações estão BLOQUEADAS nas configurações do navegador para este site.', 'text-danger');
            mostrar(el.ativar, false); mostrar(el.desativar, false); mostrar(el.testar, false);
            return;
        }

        inscricaoAtual().then(function (sub) {
            if (sub) {
                definirStatus('Notificações ATIVAS neste dispositivo.', 'text-success');
                mostrar(el.ativar, false); mostrar(el.desativar, true); mostrar(el.testar, true);
            } else {
                definirStatus('Notificações inativas neste dispositivo.', 'text-muted');
                mostrar(el.ativar, true); mostrar(el.desativar, false); mostrar(el.testar, false);
            }
        });
    }

    function bloquear(botao, bloqueado) {
        if (botao) { botao.disabled = bloqueado; }
    }

    if (el.ativar) {
        el.ativar.addEventListener('click', function () {
            bloquear(el.ativar, true);
            ativar().then(function () {
                atualizarUI();
            }).catch(function (err) {
                if (err && err.tipo === 'bloqueado') {
                    definirStatus('Permissão negada. Libere as notificações nas configurações do navegador.', 'text-danger');
                } else {
                    definirStatus('Não foi possível ativar as notificações agora.', 'text-warning');
                }
            }).finally(function () { bloquear(el.ativar, false); });
        });
    }

    if (el.desativar) {
        el.desativar.addEventListener('click', function () {
            bloquear(el.desativar, true);
            desativar().then(function () {
                atualizarUI();
            }).finally(function () { bloquear(el.desativar, false); });
        });
    }

    if (el.testar) {
        el.testar.addEventListener('click', function () {
            bloquear(el.testar, true);
            post(urlTestar).then(function (r) { return r.json().catch(function () { return {}; }); })
                .then(function (data) {
                    if (data && data.ok) {
                        definirStatus('Notificação de teste enviada. Verifique seu dispositivo.', 'text-success');
                    } else {
                        definirStatus('Não foi possível enviar o teste. Verifique se a inscrição está ativa.', 'text-warning');
                    }
                })
                .finally(function () { bloquear(el.testar, false); });
        });
    }

    // ── Convite global de ativação ─────────────────────────────────────────────
    // Banner discreto em qualquer tela para quem ainda não ativou o push neste
    // dispositivo. Não aparece na tela de Notificações (o card #push-config já
    // cumpre o papel) nem após o usuário dispensar ("Agora não" fica lembrado).
    var CHAVE_DISPENSA = 'gc_push_convite_dispensado_em';
    var DIAS_REOFERTA = 30;

    function conviteDispensado() {
        try {
            var quando = parseInt(localStorage.getItem(CHAVE_DISPENSA) || '0', 10);
            return quando > 0 && (Date.now() - quando) < DIAS_REOFERTA * 24 * 60 * 60 * 1000;
        } catch (e) { return false; }
    }

    function dispensarConvite() {
        try { localStorage.setItem(CHAVE_DISPENSA, String(Date.now())); } catch (e) {}
    }

    function montarConvite() {
        var banner = document.createElement('div');
        banner.id = 'push-convite';
        banner.className = 'card shadow position-fixed bottom-0 end-0 m-3';
        banner.style.maxWidth = '340px';
        banner.style.zIndex = '1080';
        banner.innerHTML =
            '<div class="card-body py-3">' +
                '<div class="d-flex align-items-start gap-2 mb-2">' +
                    '<i class="bi bi-bell fs-5"></i>' +
                    '<div class="small">Receba os lembretes do sistema como <strong>notificações do navegador</strong>, mesmo com a tela fechada.</div>' +
                '</div>' +
                '<div class="d-flex justify-content-end gap-2">' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary" data-acao="depois">Agora não</button>' +
                    '<button type="button" class="btn btn-sm btn-gc-primary" data-acao="ativar"><i class="bi bi-bell"></i> Ativar</button>' +
                '</div>' +
            '</div>';

        banner.querySelector('[data-acao="depois"]').addEventListener('click', function () {
            dispensarConvite();
            banner.remove();
        });

        banner.querySelector('[data-acao="ativar"]').addEventListener('click', function () {
            var botao = banner.querySelector('[data-acao="ativar"]');
            botao.disabled = true;
            ativar().then(function () {
                banner.remove();
            }).catch(function () {
                // Negou no prompt do navegador (ou falhou): não insistir agora.
                dispensarConvite();
                banner.remove();
            });
        });

        document.body.appendChild(banner);
    }

    function oferecerConvite() {
        if (el.wrap || !suportado || Notification.permission === 'denied' || conviteDispensado()) {
            return;
        }
        inscricaoAtual().then(function (sub) {
            if (!sub) { montarConvite(); }
        });
    }

    // ── Sair desinscreve este dispositivo ──────────────────────────────────────
    // Celular/tablet compartilhado: sem isto, o próximo usuário continuaria
    // recebendo as notificações (nomes, alocações, ocorrências) do anterior.
    // Espera no máximo 2 s para não travar o logout.
    if (suportado) {
        document.querySelectorAll('form[action$="/logout"]').forEach(function (form) {
            form.addEventListener('submit', function (ev) {
                if (form.dataset.pushLimpo) { return; }
                ev.preventDefault();
                form.dataset.pushLimpo = '1';
                var limite = new Promise(function (ok) { setTimeout(ok, 2000); });
                Promise.race([desativar().catch(function () {}), limite]).then(function () { form.submit(); });
            });
        });
    }

    // Registra o Service Worker e atualiza a UI (se existir na página).
    if (suportado) {
        registrarSW().then(function () {
            atualizarUI();
            oferecerConvite();
        }).catch(function () {
            definirStatus('Não foi possível registrar o serviço de notificações.', 'text-warning');
        });
    } else {
        atualizarUI();
    }
})();
