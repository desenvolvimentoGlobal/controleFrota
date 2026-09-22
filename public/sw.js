// Service Worker — Web Push do sistema de Gestão de Pessoas.

self.addEventListener('push', function (event) {
    let dados = {};
    try {
        dados = event.data ? event.data.json() : {};
    } catch (e) {
        dados = { title: 'Notificação', body: event.data ? event.data.text() : '' };
    }

    const titulo = dados.title || 'Notificação';
    const opcoes = {
        body: dados.body || '',
        icon: '/images/logo_apenas_bola.jpg',
        badge: '/images/logo_apenas_bola.jpg',
        tag: dados.tag || 'geral',
        renotify: true,
        data: { url: dados.url || '/' },
    };

    event.waitUntil(self.registration.showNotification(titulo, opcoes));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const destino = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (janelas) {
            // Se já houver uma janela do sistema aberta, foca e navega até o destino.
            for (const janela of janelas) {
                if ('focus' in janela) {
                    if ('navigate' in janela) {
                        janela.navigate(destino).catch(function () {});
                    }
                    return janela.focus();
                }
            }
            // Caso contrário, abre uma nova janela. A rota passa pelos middlewares
            // normais (auth/permissões); se não autenticado, o sistema leva ao login.
            if (clients.openWindow) {
                return clients.openWindow(destino);
            }
        })
    );
});
