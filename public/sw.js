// Service Worker do Controle de Frota.
//
// Duas funções:
//  1) tornar o sistema instalável no celular (PWA);
//  2) Web Push: mostrar a notificação e abrir o sistema no clique.
//
// NÃO faz cache de páginas: o sistema depende de sessão e de dados sempre
// atuais (situação do veículo, alocações). Sem rede, o navegador mostra a
// página de erro padrão — melhor do que uma tela velha e enganosa.

self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    let dados = {};
    try {
        dados = event.data ? event.data.json() : {};
    } catch (e) {
        dados = { title: 'Controle de Frota', body: event.data ? event.data.text() : '' };
    }

    const titulo = dados.title || 'Controle de Frota';
    const opcoes = {
        body: dados.body || '',
        icon: '/images/icone-pwa-192.png',
        badge: '/images/icone-pwa-192.png',
        tag: dados.tag || 'geral',
        renotify: true,
        data: { url: dados.url || '/painel' },
    };

    event.waitUntil(self.registration.showNotification(titulo, opcoes));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const destino = (event.notification.data && event.notification.data.url) || '/painel';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (janelas) {
            for (const janela of janelas) {
                if ('focus' in janela) {
                    if ('navigate' in janela) {
                        janela.navigate(destino).catch(function () {});
                    }
                    return janela.focus();
                }
            }
            // A rota passa pelos middlewares normais: sem sessão, cai no login.
            if (clients.openWindow) {
                return clients.openWindow(destino);
            }
        })
    );
});
