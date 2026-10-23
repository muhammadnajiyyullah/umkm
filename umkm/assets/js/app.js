/* KOPI NUSA — interaksi shell dan utilitas global */
(function () {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  const Cafe = {
    money(value) {
      const number = Number(value || 0);
      return 'Rp' + Math.round(number).toLocaleString('id-ID');
    },
    escape(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },
    csrf: document.body ? (document.body.dataset.csrf || '') : '',
    api(path) {
      const base = (document.body && document.body.dataset.base) || '';
      const clean = String(path).replace(/^\/+/, '');
      // Base bisa berupa '/' (aplikasi di document root / virtual host).
      // Jangan sampai menghasilkan '//path' karena browser akan membacanya
      // sebagai protocol-relative URL dan menghubungi host lain.
      const joined = base.replace(/\/+$/, '') + '/' + clean;
      return joined.replace(/^\/+/, '/');
    },
    notify(message, type = 'info') {
      let stack = $('.flash-stack');
      if (!stack) {
        stack = document.createElement('div');
        stack.className = 'flash-stack';
        $('.content')?.prepend(stack);
      }
      const flash = document.createElement('div');
      flash.className = 'flash flash-' + type;
      flash.innerHTML = '<span>' + Cafe.escape(message) + '</span><button type="button" data-dismiss-flash aria-label="Tutup">×</button>';
      stack.prepend(flash);
      wireFlash(flash);
      window.setTimeout(() => flash.remove(), 5000);
    },
    openModal(id) {
      const modal = typeof id === 'string' ? document.getElementById(id) : id;
      if (!modal) return;
      modal.classList.add('open');
      document.body.style.overflow = 'hidden';
      const first = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
      if (first) window.setTimeout(() => first.focus(), 80);
    },
    closeModal(id) {
      const modal = typeof id === 'string' ? document.getElementById(id) : id;
      if (!modal) return;
      modal.classList.remove('open');
      document.body.style.overflow = '';
    },
    async request(url, options = {}) {
      const config = Object.assign({ headers: { 'X-Requested-With': 'XMLHttpRequest' } }, options);
      config.headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, options.headers || {});
      const response = await fetch(url, config);
      let data = {};
      try { data = await response.json(); } catch (_) { data = { success: false, message: 'Respons server tidak valid.' }; }
      if (!response.ok || data.success === false) {
        throw new Error(data.message || 'Terjadi kesalahan pada server.');
      }
      return data;
    }
  };
  window.Cafe = Cafe;

  function wireFlash(flash) {
    const close = $('[data-dismiss-flash]', flash);
    if (close) close.addEventListener('click', () => flash.remove());
  }

  document.addEventListener('DOMContentLoaded', function () {
    const sidebar = $('#appSidebar');
    const backdrop = $('[data-sidebar-backdrop]');
    const openSidebar = () => { sidebar?.classList.add('open'); backdrop?.classList.add('open'); document.body.style.overflow = 'hidden'; };
    const closeSidebar = () => { sidebar?.classList.remove('open'); backdrop?.classList.remove('open'); document.body.style.overflow = ''; };
    $$('[data-sidebar-open]').forEach((button) => button.addEventListener('click', openSidebar));
    $$('[data-sidebar-close]').forEach((button) => button.addEventListener('click', closeSidebar));
    backdrop?.addEventListener('click', closeSidebar);

    const notificationButton = $('[data-notification-toggle]');
    const notificationPopover = $('[data-notification-popover]');
    notificationButton?.addEventListener('click', (event) => { event.stopPropagation(); notificationPopover?.classList.toggle('open'); });
    document.addEventListener('click', (event) => {
      if (notificationPopover && !notificationPopover.contains(event.target) && event.target !== notificationButton) notificationPopover.classList.remove('open');
    });

    const helpToggle = $('[data-help-toggle]');
    const helpPopover = $('[data-help-popover]');
    helpToggle?.addEventListener('click', (event) => { event.stopPropagation(); helpPopover?.classList.toggle('open'); });
    document.addEventListener('click', (event) => {
      if (helpPopover && !helpPopover.contains(event.target) && event.target !== helpToggle) helpPopover.classList.remove('open');
    });

    $$('[data-dismiss-flash]').forEach(wireFlash);
    $$('.flash').forEach((flash) => window.setTimeout(() => flash.remove(), 6500));

    $$('[data-modal-target]').forEach((button) => button.addEventListener('click', (event) => {
      event.preventDefault();
      Cafe.openModal(button.dataset.modalTarget);
    }));
    $$('[data-modal-close]').forEach((button) => button.addEventListener('click', () => Cafe.closeModal(button.dataset.modalClose || button.closest('.modal-backdrop-custom'))));
    $$('.modal-backdrop-custom').forEach((modal) => modal.addEventListener('click', (event) => { if (event.target === modal) Cafe.closeModal(modal); }));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { $$('.modal-backdrop-custom.open').forEach((modal) => Cafe.closeModal(modal)); } });

    $$('[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
      const message = form.dataset.confirm || 'Tindakan ini tidak dapat dibatalkan. Lanjutkan?';
      if (!window.confirm(message)) event.preventDefault();
    }));

    $$('.password-toggle').forEach((button) => button.addEventListener('click', () => {
      const targetId = button.dataset.togglePassword;
      const field = button.closest('.auth-field') || button.parentElement;
      const input = (targetId ? document.getElementById(targetId) : null) || (field ? $('input', field) : null);
      if (!input) return;
      const tampilkan = input.type === 'password';
      input.type = tampilkan ? 'text' : 'password';
      button.setAttribute('aria-label', tampilkan ? 'Sembunyikan password' : 'Tampilkan password');
      button.setAttribute('aria-pressed', tampilkan ? 'true' : 'false');
      input.focus();
      button.innerHTML = input.type === 'password'
        ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>'
        : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.1A10.7 10.7 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.2 4.1M6.6 6.6C3.7 8.4 2 12 2 12s3.5 7 10 7a10.8 10.8 0 0 0 3.1-.45"/></svg>';
    }));

    $$('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
      const value = button.dataset.copy || '';
      try { await navigator.clipboard.writeText(value); Cafe.notify('Nomor disalin ke clipboard.', 'success'); }
      catch (_) { Cafe.notify('Nomor: ' + value, 'info'); }
    }));

    $$('[data-auto-submit]').forEach((field) => field.addEventListener('change', () => field.form?.submit()));
    $$('[data-print]').forEach((button) => button.addEventListener('click', () => window.print()));
    $$('[data-table-filter]').forEach((input) => input.addEventListener('input', () => {
      const target = document.querySelector(input.dataset.tableFilter);
      if (!target) return;
      const query = input.value.trim().toLowerCase();
      $$('tbody tr', target).forEach((row) => { row.hidden = query ? !row.textContent.toLowerCase().includes(query) : false; });
    }));
  });
}());