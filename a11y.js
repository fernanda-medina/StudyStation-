/* StudyStation — acessibilidade (visão e audição). Carregar DEPOIS do <script> principal. */
(function () {
  'use strict';

  // Carrega o a11y.css sozinho (mesma pasta do a11y.js) e injeta o mínimo para o botão sempre aparecer
  const meuSrc = (document.currentScript && document.currentScript.src) || '';
  if (!document.querySelector('link[href*="a11y.css"]')) {
    const l = document.createElement('link');
    l.rel = 'stylesheet'; l.href = meuSrc ? meuSrc.replace(/a11y\.js.*$/, 'a11y.css') : 'a11y.css';
    document.head.appendChild(l);
  }
  const critico = document.createElement('style');
  critico.textContent = '#a11y-btn{position:fixed;top:12px;right:12px;z-index:300;padding:9px 14px;border-radius:999px;border:2px solid #fff;background:#1a1e2a;color:#fff;font:500 .85rem sans-serif;cursor:pointer}#a11y-panel{position:fixed;top:58px;right:12px;z-index:300;width:280px;max-width:92vw;background:#13161e;color:#e8ecf4;border:1px solid #525a70;border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:12px}#a11y-panel[hidden]{display:none}';
  document.head.appendChild(critico);

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
  else iniciar();

  function iniciar() {
  try {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  /* ---------- Preferências ---------- */
  const KEY = 'a11y-prefs';
  const prefs = { fonte: 100, contraste: false, movimento: false, libras: true };
  try { Object.assign(prefs, JSON.parse(localStorage.getItem(KEY) || '{}')); } catch (e) {}
  const salvar = () => { try { localStorage.setItem(KEY, JSON.stringify(prefs)); } catch (e) {} };

  /* ---------- Região viva (leitores de tela) ---------- */
  const live = document.createElement('div');
  live.id = 'a11y-live'; live.className = 'sr-only';
  live.setAttribute('aria-live', 'polite'); live.setAttribute('aria-atomic', 'true');
  document.body.appendChild(live);
  function anunciar(msg) { live.textContent = ''; setTimeout(() => { live.textContent = msg; }, 60); }

  /* ---------- Avisos visuais (audição): toast mais longo, com aria-live, fecha ao clicar ---------- */
  let toastTimer;
  window.showToast = function (msg) {
    const t = $('#toast'); if (!t) return;
    $('#toast-msg').textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 7000);
  };
  const toast = $('#toast');
  if (toast) {
    toast.setAttribute('role', 'status'); toast.setAttribute('aria-live', 'polite');
    toast.title = 'Clique para fechar';
    toast.addEventListener('click', () => toast.classList.remove('show'));
  }

  /* ---------- Libras (VLibras, gratuito do governo) ---------- */
  function libras(on) {
    let w = $('[vw]');
    if (on && !w) {
      w = document.createElement('div'); w.setAttribute('vw', ''); w.className = 'enabled';
      w.innerHTML = '<div vw-access-button class="active"></div><div vw-plugin-wrapper><div class="vw-plugin-top-wrapper"></div></div>';
      document.body.appendChild(w);
      const s = document.createElement('script');
      s.src = 'https://vlibras.gov.br/app/vlibras-plugin.js';
      s.onload = () => { try { new window.VLibras.Widget('https://vlibras.gov.br/app'); } catch (e) {} };
      document.body.appendChild(s);
    } else if (w) { w.style.display = on ? '' : 'none'; }
  }

  function aplicar() {
    const h = document.documentElement;
    h.style.fontSize = prefs.fonte + '%';
    h.classList.toggle('a11y-big', prefs.fonte >= 150);
    h.classList.toggle('a11y-hc', prefs.contraste);
    h.classList.toggle('a11y-nomotion', prefs.movimento);
    const f = $('#a11y-fonte'); if (f) f.textContent = prefs.fonte + '%';
    libras(prefs.libras);
  }

  /* ---------- Painel de acessibilidade ---------- */
  function criarPainel() {
    const b = document.createElement('button');
    b.id = 'a11y-btn'; b.type = 'button';
    b.setAttribute('aria-expanded', 'false'); b.setAttribute('aria-controls', 'a11y-panel');
    b.innerHTML = '<span aria-hidden="true">♿</span> Acessibilidade';
    const p = document.createElement('div');
    p.id = 'a11y-panel'; p.hidden = true;
    p.setAttribute('role', 'group'); p.setAttribute('aria-label', 'Opções de acessibilidade');
    p.innerHTML = `
      <h2>Texto</h2>
      <div class="linha">
        <button type="button" data-a="menos" aria-label="Diminuir texto">A−</button>
        <span id="a11y-fonte" aria-live="polite">100%</span>
        <button type="button" data-a="mais" aria-label="Aumentar texto">A+</button>
      </div>
      <h2>Visão</h2>
      <label><input type="checkbox" data-p="contraste"> Alto contraste</label>
      <label><input type="checkbox" data-p="movimento"> Reduzir animações</label>
      <h2>Audição</h2>
      <label><input type="checkbox" data-p="libras"> Tradutor de Libras (VLibras)</label>
      <button type="button" data-a="reset">Restaurar padrão</button>`;
    document.body.append(b, p);
    $$('[data-p]', p).forEach(c => { c.checked = !!prefs[c.dataset.p]; });
    b.addEventListener('click', () => {
      p.hidden = !p.hidden; b.setAttribute('aria-expanded', String(!p.hidden));
      if (!p.hidden) $('button, input', p).focus();
    });
    p.addEventListener('click', e => {
      const a = e.target.dataset && e.target.dataset.a; if (!a) return;
      if (a === 'mais') prefs.fonte = Math.min(200, prefs.fonte + 25);
      if (a === 'menos') prefs.fonte = Math.max(100, prefs.fonte - 25);
      if (a === 'reset') {
        Object.assign(prefs, { fonte: 100, contraste: false, movimento: false, libras: true });
        $$('[data-p]', p).forEach(c => { c.checked = !!prefs[c.dataset.p]; });
      }
      salvar(); aplicar();
    });
    p.addEventListener('change', e => {
      const k = e.target.dataset && e.target.dataset.p; if (!k) return;
      prefs[k] = e.target.checked; salvar(); aplicar();
    });
    p.addEventListener('keydown', e => {
      if (e.key === 'Escape') { p.hidden = true; b.setAttribute('aria-expanded', 'false'); b.focus(); }
    });
  }

  /* ---------- Semântica / ARIA nos elementos do app ---------- */
  const alfa = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
  const txt = (el) => (el ? el.textContent.trim() : '');

  function enhance() {
    // Skip link e marcos
    let skip = $('.skip-link');
    if (!skip) {
      skip = document.createElement('a'); skip.className = 'skip-link'; skip.href = '#main-content';
      skip.textContent = 'Pular para o conteúdo';
      skip.addEventListener('click', e => { e.preventDefault(); const m = $('#main-content'); if (m) m.focus(); });
      document.body.prepend(skip);
    }
    const app = $('#app'); skip.hidden = !app || getComputedStyle(app).display === 'none';
    const main = $('main.main'); if (main && !main.id) { main.id = 'main-content'; main.tabIndex = -1; }
    const nav = $('.sidebar-nav'); if (nav) nav.setAttribute('aria-label', 'Navegação principal');

    // Decorativos
    $$('.nav-icon,.stat-icon,.tool-icon,.file-icon-big,.book-cover,.activity-dot,.toast-dot').forEach(e => e.setAttribute('aria-hidden', 'true'));

    // Títulos
    $$('.page-title,.auth-logo').forEach(e => { e.setAttribute('role', 'heading'); e.setAttribute('aria-level', '1'); });
    $$('.modal-title,.section-title').forEach(e => { e.setAttribute('role', 'heading'); e.setAttribute('aria-level', '2'); });

    // Elementos clicáveis que eram <div>
    $$('.nav-item,.tool-card,.file-card,.user-chip,.auth-tab,.event-chip,.meta-check').forEach(e => {
      if (!e.hasAttribute('tabindex')) e.tabIndex = 0;
      if (!e.hasAttribute('role')) e.setAttribute('role', e.classList.contains('auth-tab') ? 'tab' : e.classList.contains('meta-check') ? 'checkbox' : 'button');
    });
    $$('.nav-item').forEach(n => n.classList.contains('active') ? n.setAttribute('aria-current', 'page') : n.removeAttribute('aria-current'));
    $$('.tool-card').forEach(c => c.setAttribute('aria-label', txt($('.tool-name', c)) + '. ' + txt($('.tool-desc', c))));
    $$('.file-card').forEach(c => c.setAttribute('aria-label', 'Abrir ' + txt($('.file-name', c))));
    $$('.user-chip').forEach(c => c.setAttribute('aria-label', 'Sair da conta de ' + txt($('.user-name', c))));
    $$('.event-chip').forEach(c => c.setAttribute('aria-label', 'Evento: ' + txt(c) + '. ' + (c.title || '')));
    $$('.meta-item').forEach(m => {
      const nome = txt($('.meta-name', m)), ck = $('.meta-check', m), del = $('.btn-sm', m);
      if (ck) { ck.setAttribute('aria-checked', String(ck.classList.contains('done'))); ck.setAttribute('aria-label', 'Meta concluída: ' + nome); }
      if (del) del.setAttribute('aria-label', 'Apagar meta: ' + nome);
    });
    $$('.modal-close').forEach(b => { b.setAttribute('aria-label', 'Fechar janela'); b.type = 'button'; });
    $$('#slides-list .btn-sm').forEach(b => b.setAttribute('aria-label', 'Remover slide'));
    $$('.book-card .btn-sm').forEach(b => b.setAttribute('aria-label', txt(b) + ': ' + txt($('.book-title', b.parentElement))));

    // Abas do login
    const tabs = $('.auth-tabs');
    if (tabs) {
      tabs.setAttribute('role', 'tablist');
      $$('.auth-tab', tabs).forEach((t, i) => { t.setAttribute('aria-selected', String(t.classList.contains('active'))); t.setAttribute('aria-controls', i ? 'register-form' : 'login-form'); });
    }

    // Filtros (botões de alternância)
    $$('#page-metas .mm-btn,#page-biblioteca .mm-btn').forEach(b => b.setAttribute('aria-pressed', String(b.classList.contains('active'))));

    // Formulários: rótulos ligados aos campos
    $$('.form-group').forEach(g => {
      const l = $('label', g), c = $('input,select,textarea', g);
      if (l && c && c.id) { l.htmlFor = c.id; }
      if (c && l && !c.id) c.setAttribute('aria-label', txt(l));
    });
    const ac = { 'login-email': 'email', 'login-pass': 'current-password', 'reg-name': 'name', 'reg-email': 'email', 'reg-pass': 'new-password' };
    Object.keys(ac).forEach(id => { const e = document.getElementById(id); if (e) e.autocomplete = ac[id]; });
    const bs = $('#bib-search'); if (bs) bs.setAttribute('aria-label', 'Pesquisar livros, apostilas e artigos');
    $$('#slides-list > div').forEach((s, i) => {
      const t = $('input', s), c = $('textarea', s);
      if (t) t.setAttribute('aria-label', 'Título do slide ' + (i + 1));
      if (c) c.setAttribute('aria-label', 'Conteúdo do slide ' + (i + 1));
    });
    $$('#pl-table tr').forEach((tr, r) => {
      $$('th', tr).forEach(th => th.setAttribute('scope', 'col'));
      $$('td', tr).forEach((td, c) => { const i = $('input', td); if (i && c > 0) i.setAttribute('aria-label', 'Célula ' + alfa[c - 1] + r); });
    });

    // Progresso
    $$('.progress-bar').forEach(b => {
      const f = $('.progress-fill', b), prev = b.previousElementSibling;
      if (!f) return;
      b.setAttribute('role', 'progressbar'); b.setAttribute('aria-valuemin', '0'); b.setAttribute('aria-valuemax', '100');
      b.setAttribute('aria-valuenow', String(parseInt(f.style.width, 10) || 0));
      const l = prev && $('span', prev); if (l) b.setAttribute('aria-label', txt(l));
    });

    // Cronograma
    $$('.day-col').forEach(d => d.setAttribute('role', 'group') || d.setAttribute('aria-label', txt($('.day-header', d)) + ' ' + txt($('.day-number', d)) + ($('.today', d) ? ' (hoje)' : '')));

    sincModais();
    mmEnhance();
  }

  /* ---------- Modais: foco, Esc, inert ---------- */
  let ultimoFoco = null;
  function sincModais() {
    $$('.modal-overlay').forEach(o => {
      const aberto = o.classList.contains('open'), m = $('.modal', o);
      m.setAttribute('role', 'dialog'); m.setAttribute('aria-modal', 'true');
      const t = $('.modal-title', m); if (t) { if (!t.id) t.id = o.id + '-t'; m.setAttribute('aria-labelledby', t.id); }
      if (aberto && !o._aberto) {
        o._aberto = true; ultimoFoco = document.activeElement; o.removeAttribute('inert');
        setTimeout(() => { const f = $('input,select,textarea,canvas,button:not(.modal-close)', m) || $('.modal-close', m); if (f) f.focus(); }, 80);
      } else if (!aberto && o._aberto !== false) {
        const era = o._aberto; o._aberto = false; o.setAttribute('inert', '');
        if (era && ultimoFoco && document.contains(ultimoFoco)) ultimoFoco.focus();
      }
    });
  }

  document.addEventListener('keydown', e => {
    const alvo = e.target;
    // Teclado em elementos que eram <div onclick>
    if ((e.key === 'Enter' || e.key === ' ') && alvo.matches && alvo.matches('[role="button"],[role="tab"],[role="checkbox"]')) {
      e.preventDefault(); alvo.click(); return;
    }
    // Enter envia formulários de login/cadastro/meta/evento
    if (e.key === 'Enter' && alvo.tagName === 'INPUT') {
      const box = alvo.closest('#login-form,#register-form,#modal-meta,#modal-evento');
      const btn = box && $('.btn-primary,.btn-add', box);
      if (btn) { e.preventDefault(); btn.click(); return; }
    }
    const aberto = $('.modal-overlay.open');
    if (!aberto) return;
    if (e.key === 'Escape') { e.preventDefault(); if (window.closeModal) closeModal(aberto.id); return; }
    if (e.key === 'Tab') {
      const f = $$('a[href],button:not([disabled]),input,select,textarea,canvas[tabindex],[tabindex="0"]', aberto).filter(x => x.offsetParent !== null);
      if (!f.length) return;
      const a = f[0], z = f[f.length - 1];
      if (e.shiftKey && document.activeElement === a) { e.preventDefault(); z.focus(); }
      else if (!e.shiftKey && document.activeElement === z) { e.preventDefault(); a.focus(); }
    }
  });

  /* ---------- Troca de página e login: mover foco ---------- */
  const goOrig = window.goPage;
  if (goOrig) window.goPage = function (p) {
    goOrig(p);
    const t = $('#page-' + p + ' .page-title');
    if (t) { t.tabIndex = -1; t.focus(); }
  };
  const launchOrig = window.launchApp;
  if (launchOrig) window.launchApp = function (u) {
    launchOrig(u);
    setTimeout(() => { const t = $('#page-dashboard .page-title'); if (t) { t.tabIndex = -1; t.focus(); } }, 50);
  };

  /* ---------- Mapa mental acessível por teclado ---------- */
  function descMM() {
    if (!mindmapNodes.length) return 'Mapa mental vazio. Pressione Enter para adicionar um nó.';
    const partes = mindmapNodes.map(n => {
      const p = mindmapNodes.find(m => m.id === n.parent);
      return n.label + (p ? ' (filho de ' + p.label + ')' : ' (raiz)');
    });
    return 'Mapa mental com ' + mindmapNodes.length + ' nós: ' + partes.join('; ') + (selectedNode ? '. Selecionado: ' + selectedNode.label : '') + '.';
  }
  function mmAtualizar() { const c = $('#mindmap-canvas'); if (c) c.setAttribute('aria-label', descMM()); drawMM(); }
  function mmNovo() {
    const c = $('#mindmap-canvas');
    const label = prompt('Nome do nó:', ''); if (!label) return;
    const base = selectedNode;
    const k = base ? mindmapNodes.filter(n => n.parent === base.id).length : 0;
    const ang = k * (Math.PI / 3) - Math.PI / 6;
    let x = base ? base.x + Math.cos(ang) * 110 : c.width / 2 + mindmapNodes.length * 20;
    let y = base ? base.y + Math.sin(ang) * 110 : c.height / 2;
    x = Math.min(Math.max(x, 40), c.width - 40); y = Math.min(Math.max(y, 40), c.height - 40);
    const cores = ['#6c8cff', '#a78bfa', '#34d399', '#f59e0b', '#f87171'];
    const n = { x, y, label, id: Date.now(), color: cores[mindmapNodes.length % cores.length], parent: base ? base.id : null };
    mindmapNodes.push(n); selectedNode = n; mmAtualizar();
    anunciar('Nó ' + label + ' adicionado' + (base ? ', ligado a ' + base.label : ''));
  }
  function mmSel(d) {
    if (!mindmapNodes.length) return;
    const i = mindmapNodes.indexOf(selectedNode);
    const j = i < 0 ? (d > 0 ? 0 : mindmapNodes.length - 1) : (i + d + mindmapNodes.length) % mindmapNodes.length;
    selectedNode = mindmapNodes[j]; mmAtualizar(); anunciar('Selecionado: ' + selectedNode.label);
  }
  function mmDel() {
    if (!selectedNode) { anunciar('Nenhum nó selecionado'); return; }
    const l = selectedNode.label;
    mindmapNodes = mindmapNodes.filter(n => n !== selectedNode); selectedNode = null; mmAtualizar(); anunciar('Nó ' + l + ' removido');
  }
  function mmEnhance() {
    const c = $('#mindmap-canvas'); if (!c || c._a11y) return; c._a11y = true;
    c.tabIndex = 0; c.setAttribute('role', 'application'); c.setAttribute('aria-label', descMM());
    c.setAttribute('aria-describedby', 'mm-ajuda');
    const ajuda = document.createElement('div'); ajuda.id = 'mm-ajuda'; ajuda.className = 'sr-only';
    ajuda.textContent = 'Enter: adicionar nó ligado ao selecionado. Setas: trocar de nó. Shift mais setas: mover o nó. Delete: remover.';
    c.after(ajuda);
    const bar = $('.mm-toolbar');
    if (bar) {
      bar.insertAdjacentHTML('beforeend',
        '<button type="button" class="mm-btn" data-mm="novo">＋ Nó (teclado)</button>' +
        '<button type="button" class="mm-btn" data-mm="ant">◀ Nó anterior</button>' +
        '<button type="button" class="mm-btn" data-mm="prox">Próximo nó ▶</button>' +
        '<button type="button" class="mm-btn" data-mm="del">Remover selecionado</button>');
      bar.addEventListener('click', e => {
        const a = e.target.dataset && e.target.dataset.mm; if (!a) return;
        if (a === 'novo') mmNovo(); if (a === 'ant') mmSel(-1); if (a === 'prox') mmSel(1); if (a === 'del') mmDel();
      });
    }
    c.addEventListener('focus', () => c.setAttribute('aria-label', descMM()));
    c.addEventListener('keydown', e => {
      const mover = { ArrowLeft: [-12, 0], ArrowRight: [12, 0], ArrowUp: [0, -12], ArrowDown: [0, 12] }[e.key];
      if (e.key === 'Enter') { e.preventDefault(); mmNovo(); }
      else if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); mmDel(); }
      else if (mover && e.shiftKey && selectedNode) { e.preventDefault(); selectedNode.x += mover[0]; selectedNode.y += mover[1]; drawMM(); }
      else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); mmSel(-1); }
      else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); mmSel(1); }
    });
  }

  /* ---------- Reaplicar quando o DOM muda ---------- */
  let pend = false;
  new MutationObserver(() => {
    if (pend) return; pend = true;
    requestAnimationFrame(() => { pend = false; enhance(); });
  }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });

  criarPainel();
  aplicar();
  enhance();
  } catch (erro) { console.error('[a11y.js] erro ao iniciar:', erro); }
  }
})();