// Admin Service Worker — push notification receiver
self.addEventListener('install',  () => self.skipWaiting());
self.addEventListener('activate', e  => e.waitUntil(self.clients.claim()));

self.addEventListener('push', e => {
  const d = e.data?.json() ?? {};
  e.waitUntil(
    self.registration.showNotification(d.title ?? '🍺 Novo pedido!', {
      body:      d.body ?? 'Um novo pedido está aguardando.',
      icon:      '/favicon-icone.webp',
      badge:     '/favicon-icone.webp',
      tag:       'new-order',
      renotify:  true,
      vibrate:   [200, 100, 200],
      data:      { url: self.location.origin + '/pedidos' },
    })
  );
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = e.notification.data?.url ?? self.location.origin + '/pedidos';
  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(ws => {
      const w = ws.find(c => new URL(c.url).pathname.includes('pedidos') || new URL(c.url).pathname.includes('painel'));
      return w ? w.focus() : clients.openWindow(url);
    })
  );
});
