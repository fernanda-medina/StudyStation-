/**
 * keyboard-nav.js — toggleable keyboard navigation for any web page.
 *
 * Usage:
 *   <script src="keyboard-nav.js"></script>
 *   Press Alt+K to toggle, or call keyboardNav.toggle() / .enable() / .disable().
 *
 * Optional markup hints (otherwise sensible selectors are guessed):
 *   data-kbnav-sidebar, data-kbnav-main, data-kbnav-sidebar-toggle, data-kbnav-search
 *
 * Shortcuts while enabled (ignored while typing in a field):
 *   Arrow keys   Move focus to the nearest focusable element in that direction
 *                (scrolls the page if nothing is there)
 *   In sidebar   Up/Down = previous/next item, Left/Right = collapse/expand
 *                (Right on a leaf jumps to main content), Home/End = first/last
 *   s            Focus the sidebar
 *   m            Focus the main content
 *   b            Show/hide the sidebar (clicks its toggle button)
 *   /            Focus the search field
 *   Home / End   Scroll to top / bottom (when not in sidebar)
 *   Esc          Blur the current element / close help
 *   ?            Show help
 *   Alt+K        Toggle keyboard navigation on/off (works anywhere)
 */
(function (global) {
  'use strict';

  const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'summary',
    '[tabindex]:not([tabindex="-1"])',
    '[role="button"]',
    '[role="menuitem"]',
    '[role="treeitem"]',
    '[role="tab"]',
  ].join(',');

  class KeyboardNav {
    constructor(options = {}) {
      this.opts = Object.assign(
        {
          toggleKey: { key: 'k', alt: true },
          sidebarSelector:
            '[data-kbnav-sidebar], aside, [role="complementary"], .sidebar, #sidebar',
          mainSelector:
            '[data-kbnav-main], main, [role="main"], #main, .main',
          sidebarToggleSelector:
            '[data-kbnav-sidebar-toggle], .sidebar-toggle, #sidebar-toggle, [aria-controls*="sidebar" i]',
          searchSelector:
            '[data-kbnav-search], input[type="search"], input[name*="search" i], input[placeholder*="search" i]',
          scrollStep: 140,
          storageKey: 'kbnav-enabled',
          persist: true,
          showBadge: true,
          startEnabled: false,
        },
        options
      );

      this.enabled = false;
      this._onKeyDown = this._onKeyDown.bind(this);
      this._clearLast = () => { this._last = null; };
      document.addEventListener('keydown', this._onKeyDown, true);
      document.addEventListener('focusin', this._clearLast, true);
      document.addEventListener('mousedown', this._clearLast, true);

      this._injectStyles();
      if (this.opts.showBadge) this._createBadge();

      const saved = this._load();
      if (saved === null ? this.opts.startEnabled : saved) this.enable(true);
      else this._updateBadge();
    }

    /* ---------- public API ---------- */

    enable(silent) {
      this.enabled = true;
      document.documentElement.classList.add('kbnav-on');
      this._save();
      this._updateBadge(silent ? null : 'Keyboard navigation ON');
    }

    disable() {
      this.enabled = false;
      document.documentElement.classList.remove('kbnav-on');
      this._closeHelp();
      this._save();
      this._updateBadge('Keyboard navigation OFF');
    }

    toggle() {
      this.enabled ? this.disable() : this.enable();
    }

    destroy() {
      document.removeEventListener('keydown', this._onKeyDown, true);
      document.removeEventListener('focusin', this._clearLast, true);
      document.removeEventListener('mousedown', this._clearLast, true);
      document.documentElement.classList.remove('kbnav-on');
      this._badge && this._badge.remove();
      this._style && this._style.remove();
      this._closeHelp();
    }

    /* ---------- key handling ---------- */

    _isToggle(e) {
      const t = this.opts.toggleKey;
      return (
        e.key.toLowerCase() === t.key &&
        !!t.alt === e.altKey &&
        !!t.ctrl === e.ctrlKey &&
        !!t.shift === e.shiftKey &&
        !!t.meta === e.metaKey
      );
    }

    _isTyping(el) {
      if (!el) return false;
      if (el.isContentEditable) return true;
      const tag = el.tagName;
      if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
      if (tag === 'INPUT') {
        const type = (el.type || '').toLowerCase();
        return !['button', 'submit', 'reset', 'checkbox', 'image', 'file'].includes(type);
      }
      return false;
    }

    _onKeyDown(e) {
      if (this._isToggle(e)) {
        e.preventDefault();
        this.toggle();
        return;
      }
      if (!this.enabled || e.defaultPrevented) return;
      if (e.ctrlKey || e.metaKey || e.altKey) return;
      if (e.target.closest && e.target.closest('[role="application"]')) return; // mapa mental
if (document.querySelector('.modal-overlay.open')) return;                // modal aberto


      if (this._isTyping(e.target)) {
        if (e.key === 'Escape') {
          e.preventDefault();
          this._exitField(e.target);
        } else if (
          (e.key === 'ArrowUp' || e.key === 'ArrowDown') &&
          !e.shiftKey &&
          this._isSingleLineField(e.target)
        ) {
          e.preventDefault();
          this._exitField(e.target, e.key === 'ArrowUp' ? 'up' : 'down');
        }
        return;
      }

      const active = this._ref();
      const sidebar = this._sidebar();
      const inSidebar = !!(sidebar && active && sidebar.contains(active));
      let handled = true;

      switch (e.key) {
        case 'ArrowUp':
          inSidebar ? this._sidebarStep(-1) : this._move('up');
          break;
        case 'ArrowDown':
          inSidebar ? this._sidebarStep(1) : this._move('down');
          break;
        case 'ArrowLeft':
          inSidebar ? this._sidebarCollapse(active) : this._move('left');
          break;
        case 'ArrowRight':
          inSidebar ? this._sidebarExpand(active) : this._move('right');
          break;
        case 'Home':
          inSidebar ? this._sidebarEdge(0) : window.scrollTo({ top: 0, behavior: 'smooth' });
          break;
        case 'End':
          inSidebar
            ? this._sidebarEdge(-1)
            : window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
          break;
        case 's':
        case 'S':
          this._focusSidebar();
          break;
        case 'm':
        case 'M':
          this._focusMain();
          break;
        case 'b':
        case 'B':
          this._toggleSidebar();
          break;
        case '/':
          this._focusSearch();
          break;
        case '?':
          this._toggleHelp();
          break;
        case 'Escape':
          if (this._help) this._closeHelp();
          else if (active && active !== document.body) active.blur();
          break;
        default:
          handled = false;
      }

      if (handled && e.shiftKey === false) e.preventDefault();
    }

    /* ---------- focus helpers ---------- */

    _visible(el) {
      if (el.closest('[inert], [hidden]')) return false;
      const r = el.getBoundingClientRect();
      if (r.width === 0 && r.height === 0) return false;
      const cs = getComputedStyle(el);
      return cs.visibility !== 'hidden' && cs.display !== 'none';
    }

    _focusables(root) {
      return Array.from((root || document).querySelectorAll(FOCUSABLE)).filter((el) =>
        this._visible(el)
      );
    }

    _first(selector) {
      return Array.from(document.querySelectorAll(selector)).find((el) => this._visible(el)) || null;
    }

    _sidebar() {
      return this._first(this.opts.sidebarSelector);
    }

    _focusEl(el) {
      if (!el) return;
      el.focus({ preventScroll: true });
      el.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }

    _focusSidebar() {
      const sb = this._sidebar();
      if (!sb) return this._flash('No sidebar found');
      const items = this._focusables(sb);
      const current = items.find((el) => el.matches('[aria-current], .active, .current'));
      this._focusEl(current || items[0]);
    }

    _focusMain() {
      const main = this._first(this.opts.mainSelector);
      if (!main) return this._flash('No main content found');
      const items = this._focusables(main);
      if (items.length) return this._focusEl(items[0]);
      if (!main.hasAttribute('tabindex')) main.setAttribute('tabindex', '-1');
      this._focusEl(main);
    }

    _focusSearch() {
      const el = this._first(this.opts.searchSelector);
      if (el) this._focusEl(el);
      else this._flash('No search field found');
    }

    _toggleSidebar() {
      const btn = this._first(this.opts.sidebarToggleSelector);
      if (btn) btn.click();
      else this._flash('No sidebar toggle found');
    }

    /* ---------- leaving form fields ---------- */

    // Current element, or the field we just escaped from (so navigation continues from there).
    _ref() {
      const a = document.activeElement;
      if (a && a !== document.body) return a;
      return this._last && document.contains(this._last) ? this._last : null;
    }

    _isSingleLineField(el) {
      if (el.tagName !== 'INPUT') return false;
      const type = (el.type || 'text').toLowerCase();
      if (!['text', 'search', 'email', 'url', 'tel', 'password'].includes(type)) return false;
      // Leave Up/Down alone when the field drives an autocomplete list.
      return !(
        el.getAttribute('role') === 'combobox' ||
        el.getAttribute('aria-expanded') === 'true' ||
        el.hasAttribute('aria-autocomplete') ||
        el.hasAttribute('list')
      );
    }

    _exitField(el, dir) {
      this._last = el;
      el.blur();
      if (!dir) return;
      const sb = this._sidebar();
      if (sb && sb.contains(el)) this._sidebarStep(dir === 'up' ? -1 : 1);
      else this._move(dir);
    }

    /* ---------- sidebar navigation ---------- */

    _sidebarItems() {
      const sb = this._sidebar();
      return sb ? this._focusables(sb) : [];
    }

    _sidebarStep(dir) {
      const items = this._sidebarItems();
      if (!items.length) return;
      const i = items.indexOf(this._ref());
      const next = Math.min(items.length - 1, Math.max(0, i + dir));
      this._focusEl(items[next]);
    }

    _sidebarEdge(index) {
      const items = this._sidebarItems();
      this._focusEl(index === 0 ? items[0] : items[items.length - 1]);
    }

    _expandable(el) {
      return el.closest('[aria-expanded]') || null;
    }

    _sidebarCollapse(el) {
      const node = this._expandable(el);
      if (node && node.getAttribute('aria-expanded') === 'true') node.click();
      else if (el.closest('details[open]')) el.closest('details').open = false;
    }

    _sidebarExpand(el) {
      const node = this._expandable(el);
      if (node && node.getAttribute('aria-expanded') === 'false') return node.click();
      const details = el.closest('details');
      if (details && !details.open) details.open = true;
      else this._focusMain();
    }

    /* ---------- spatial navigation ---------- */

    _move(dir) {
      const cur = this._ref();

      if (!cur) {
        const first = this._focusables().find((el) => {
          const r = el.getBoundingClientRect();
          return r.bottom > 0 && r.top < innerHeight && r.right > 0 && r.left < innerWidth;
        });
        if (first) return this._focusEl(first);
        return this._scroll(dir, document.scrollingElement);
      }

      const r = cur.getBoundingClientRect();
      const cx = r.left + r.width / 2;
      const cy = r.top + r.height / 2;
      let best = null;
      let bestScore = Infinity;

      for (const el of this._focusables()) {
        if (el === cur || cur.contains(el) || el.contains(cur)) continue;
        const b = el.getBoundingClientRect();
        const dx = b.left + b.width / 2 - cx;
        const dy = b.top + b.height / 2 - cy;
        let primary;
        let secondary;
        if (dir === 'down') { if (dy <= 1) continue; primary = dy; secondary = Math.abs(dx); }
        else if (dir === 'up') { if (dy >= -1) continue; primary = -dy; secondary = Math.abs(dx); }
        else if (dir === 'right') { if (dx <= 1) continue; primary = dx; secondary = Math.abs(dy); }
        else { if (dx >= -1) continue; primary = -dx; secondary = Math.abs(dy); }

        const score = primary + secondary * 2;
        if (score < bestScore) { bestScore = score; best = el; }
      }

      if (best) this._focusEl(best);
      else this._scroll(dir, this._scrollParent(cur));
    }

    _scrollParent(el) {
      for (let p = el.parentElement; p; p = p.parentElement) {
        const cs = getComputedStyle(p);
        const canY = /(auto|scroll)/.test(cs.overflowY) && p.scrollHeight > p.clientHeight;
        const canX = /(auto|scroll)/.test(cs.overflowX) && p.scrollWidth > p.clientWidth;
        if (canY || canX) return p;
      }
      return document.scrollingElement;
    }

    _scroll(dir, target) {
      const s = this.opts.scrollStep;
      const opts = { behavior: 'smooth', top: 0, left: 0 };
      if (dir === 'down') opts.top = s;
      if (dir === 'up') opts.top = -s;
      if (dir === 'right') opts.left = s;
      if (dir === 'left') opts.left = -s;
      (target || document.scrollingElement).scrollBy(opts);
    }

    /* ---------- UI: badge, help, styles ---------- */

    _injectStyles() {
      this._style = document.createElement('style');
      this._style.textContent = `
        .kbnav-on :focus { outline: 3px solid #4c8bf5 !important; outline-offset: 2px !important; }
        .kbnav-badge {
          position: fixed; right: 12px; bottom: 12px; z-index: 2147483646;
          font: 12px/1.2 system-ui, sans-serif; padding: 6px 10px; border-radius: 999px;
          background: #222; color: #fff; opacity: .85; pointer-events: none;
          transition: opacity .3s, background .2s;
        }
        .kbnav-badge.on { background: #1a7f37; }
        .kbnav-badge.dim { opacity: .35; }
        .kbnav-help {
          position: fixed; inset: 0; z-index: 2147483647; display: grid; place-items: center;
          background: rgba(0,0,0,.5); font: 14px/1.5 system-ui, sans-serif;
        }
        .kbnav-help > div {
          background: #fff; color: #111; padding: 20px 24px; border-radius: 10px;
          max-width: 440px; box-shadow: 0 10px 40px rgba(0,0,0,.3);
        }
        .kbnav-help h2 { margin: 0 0 10px; font-size: 16px; }
        .kbnav-help dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 4px 14px; }
        .kbnav-help dt { font-family: ui-monospace, monospace; font-weight: 600; }
        .kbnav-help dd { margin: 0; }
        @media (prefers-color-scheme: dark) {
          .kbnav-help > div { background: #1e1e1e; color: #eee; }
        }
      `;
      document.head.appendChild(this._style);
    }

    _createBadge() {
      this._badge = document.createElement('div');
      this._badge.className = 'kbnav-badge';
      this._badge.setAttribute('role', 'status');
      this._badge.setAttribute('aria-live', 'polite');
      document.body.appendChild(this._badge);
    }

    _updateBadge(message) {
      if (!this._badge) return;
      this._badge.classList.toggle('on', this.enabled);
      const hint = `${this.enabled ? 'Keys ON' : 'Keys OFF'} · Alt+K${this.enabled ? ' · ? help' : ''}`;
      this._badge.textContent = message || hint;
      this._badge.classList.remove('dim');
      clearTimeout(this._badgeTimer);
      this._badgeTimer = setTimeout(() => {
        this._badge.textContent = hint;
        this._badge.classList.add('dim');
      }, 1800);
    }

    _flash(msg) {
      this._updateBadge(msg);
    }

    _toggleHelp() {
      if (this._help) return this._closeHelp();
      const rows = [
        ['Arrows', 'Move focus / scroll'],
        ['s', 'Focus sidebar'],
        ['m', 'Focus main content'],
        ['b', 'Show/hide sidebar'],
        ['/', 'Focus search'],
        ['↑ ↓ in sidebar', 'Previous / next item'],
        ['← → in sidebar', 'Collapse / expand'],
        ['Home / End', 'Top / bottom'],
        ['Esc', 'Blur / close'],
        ['Alt+K', 'Toggle keyboard nav'],
      ];
      this._help = document.createElement('div');
      this._help.className = 'kbnav-help';
      this._help.innerHTML =
        '<div role="dialog" aria-label="Keyboard shortcuts"><h2>Keyboard shortcuts</h2><dl>' +
        rows.map(([k, d]) => `<dt>${k}</dt><dd>${d}</dd>`).join('') +
        '</dl></div>';
      this._help.addEventListener('click', () => this._closeHelp());
      document.body.appendChild(this._help);
    }

    _closeHelp() {
      if (this._help) { this._help.remove(); this._help = null; }
    }

    /* ---------- persistence ---------- */

    _load() {
      if (!this.opts.persist) return null;
      try {
        const v = localStorage.getItem(this.opts.storageKey);
        return v === null ? null : v === '1';
      } catch (_) { return null; }
    }

    _save() {
      if (!this.opts.persist) return;
      try { localStorage.setItem(this.opts.storageKey, this.enabled ? '1' : '0'); } catch (_) {}
    }
  }

  function init() {
    global.KeyboardNav = KeyboardNav;
    global.keyboardNav = new KeyboardNav(global.KEYBOARD_NAV_OPTIONS || {});
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window);