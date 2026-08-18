(() => {
  'use strict';

  const contentSelector = '.content';
  let navigating = false;
  let navController = null;

  const sameOrigin = url => url.origin === window.location.origin;
  const isHtmlNavigation = anchor => {
    if (!anchor || anchor.target || anchor.hasAttribute('download') || anchor.dataset.noAsync !== undefined) return false;
    const href = anchor.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) return false;
    const url = new URL(anchor.href, window.location.href);
    if (!sameOrigin(url)) return false;
    const file = url.pathname.split('/').pop() || 'index.php';
    // These endpoints either stream files, terminate the session, or intentionally
    // redirect to/from an external payment provider. They must use normal browser
    // navigation rather than the async fetch/page-swap layer.
    if (['download.php', 'preview.php', 'media.php', 'logout.php', 'checkout.php', 'paypal-resume.php', 'paypal-return.php', 'paypal-cancel.php', 'paypal-webhook.php'].includes(file)) return false;
    return true;
  };

  const setLoading = loading => {
    document.documentElement.classList.toggle('async-loading', loading);
    const content = document.querySelector(contentSelector);
    if (content) content.setAttribute('aria-busy', loading ? 'true' : 'false');
  };

  const syncShell = doc => {
    const incomingTop = doc.querySelector('.top-actions');
    const currentTop = document.querySelector('.top-actions');
    if (incomingTop && currentTop) currentTop.innerHTML = incomingTop.innerHTML;

    const incomingNav = doc.querySelector('.side-nav');
    const currentNav = document.querySelector('.side-nav');
    if (incomingNav && currentNav) currentNav.innerHTML = incomingNav.innerHTML;
  };

  const applyDocument = (html, url, push = true) => {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const incoming = doc.querySelector(contentSelector);
    const current = document.querySelector(contentSelector);
    if (!incoming || !current) {
      window.location.href = url;
      return;
    }

    current.innerHTML = incoming.innerHTML;
    document.title = doc.title || document.title;
    syncShell(doc);
    if (push) history.pushState({ recordstore: true }, '', url);
    window.scrollTo({ top: 0, behavior: 'instant' });
    document.dispatchEvent(new CustomEvent('recordstore:navigated', { detail: { url } }));
  };

  const navigate = async (url, options = {}) => {
    if (navigating && navController) navController.abort();
    navController = new AbortController();
    navigating = true;
    setLoading(true);

    try {
      const response = await fetch(url, {
        method: options.method || 'GET',
        body: options.body || null,
        credentials: 'same-origin',
        headers: {
          'X-RecordStore-Async': '1',
          'X-Requested-With': 'fetch',
          ...(options.headers || {})
        },
        redirect: 'follow',
        signal: navController.signal
      });

      const contentType = response.headers.get('content-type') || '';
      if (!response.ok || !contentType.includes('text/html')) {
        if (response.redirected) window.location.href = response.url;
        else window.location.href = url;
        return;
      }

      const html = await response.text();
      applyDocument(html, response.url || url, options.push !== false);
    } catch (error) {
      if (error.name !== 'AbortError') {
        console.warn('RecordStore async navigation fallback:', error);
        window.location.href = url;
      }
    } finally {
      navigating = false;
      setLoading(false);
    }
  };

  document.addEventListener('click', async event => {
    const link = event.target.closest('a.download-action[data-item]');
    if (!link || event.defaultPrevented) return;
    event.preventDefault();
    if (link.dataset.busy === '1') return;
    link.dataset.busy = '1';
    const original = link.textContent;
    link.textContent = 'Preparing…';
    try {
      const endpoint = new URL('download.php', window.location.href);
      endpoint.searchParams.set('item', link.dataset.item);
      endpoint.searchParams.set('ajax', '1');
      const response = await fetch(endpoint, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Download unavailable.');
      const row = link.closest('.account-item-row');
      const counter = row?.querySelector('[data-download-value]');
      if (counter) counter.textContent = `${data.used} / ${data.limit}`;
      if (data.used >= data.limit) { link.textContent = 'Limit reached'; link.classList.add('disabled'); link.removeAttribute('href'); }
      else link.textContent = original;
      const dl = document.createElement('a'); dl.href = data.download_url; dl.download = ''; dl.style.display = 'none'; document.body.appendChild(dl); dl.click(); dl.remove();
    } catch (error) {
      console.warn('RecordStore download error:', error); link.textContent = original; alert(error.message || 'Download could not be prepared.');
    } finally { link.dataset.busy = '0'; }
  });

  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const anchor = event.target.closest('a');
    if (!isHtmlNavigation(anchor)) return;
    event.preventDefault();
    navigate(anchor.href);
  });

  document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.classList.contains('quick-add-form')) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const button = form.querySelector('.quick-add-button');
    if (!button || button.dataset.busy === '1') return;
    button.dataset.busy = '1';
    button.dataset.originalLabel = button.getAttribute('aria-label') || 'Add to cart';
    const original = button.textContent;
    button.textContent = '…';
    button.disabled = true;
    try {
      const response = await fetch(form.action || window.location.href, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
      });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Could not add this track to your cart.');
      document.querySelectorAll('.cart-link .pill, .mobile-cart-link .pill').forEach(pill => {
        pill.textContent = String(data.cart_count ?? '');
      });
      button.textContent = '✓';
      button.classList.add('is-added');
      button.setAttribute('aria-label', 'Added to cart');
      button.title = 'Added to cart';
      window.setTimeout(() => {
        button.textContent = original;
        button.classList.remove('is-added');
        button.setAttribute('aria-label', button.dataset.originalLabel || 'Add to cart');
        button.title = 'Add to cart';
      }, 1400);
    } catch (error) {
      console.warn('RecordStore quick add error:', error);
      button.textContent = original;
      alert(error.message || 'Could not add this track to your cart.');
    } finally {
      button.disabled = false;
      button.dataset.busy = '0';
    }
  });
  document.addEventListener('submit', async event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.classList.contains('favourite-form')) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const button = form.querySelector('.favourite-toggle');
    if (!button || button.dataset.busy === '1') return;
    button.dataset.busy = '1';
    button.disabled = true;
    try {
      const response = await fetch(form.action || window.location.href, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' } });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Could not update your favourites.');
      const saved = Boolean(data.favourite);
      button.textContent = saved ? (form.classList.contains('track-favourite-form') ? '♥ Saved' : '♥') : (form.classList.contains('track-favourite-form') ? '♡ Save to favourites' : '♡');
      button.classList.toggle('is-favourite', saved);
      button.setAttribute('aria-label', saved ? 'Remove from favourites' : 'Save to favourites');
      button.title = saved ? 'Remove from favourites' : 'Save to favourites';
      if (!saved && window.location.pathname.endsWith('/favourites.php')) {
        const card = button.closest('[data-favourite-track-id]');
        if (card) card.remove();
      }
    } catch (error) {
      console.warn('RecordStore favourites error:', error);
      alert(error.message || 'Could not update your favourites.');
    } finally {
      button.disabled = false;
      button.dataset.busy = '0';
    }
  });
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.noAsync !== undefined) return;
    if (form.querySelector('input[type="file"]')) return; // keep very large uploads as native POSTs

    const method = (form.method || 'GET').toUpperCase();
    const actionAttr = form.getAttribute('action');
    let url = new URL(actionAttr && actionAttr.trim() !== '' ? actionAttr : window.location.href, window.location.href);
    const data = new FormData(form);

    event.preventDefault();
    if (method === 'GET') {
      url.search = new URLSearchParams(data).toString();
      navigate(url.href);
    } else {
      navigate(url.href, { method, body: data });
    }
  });


  // RecordStore styled confirmation for customer pending-order cancellation.
  let pendingCancelForm = null;
  document.addEventListener('click', event => {
    const open = event.target.closest('[data-cancel-order-open]');
    const modal = document.getElementById('cancel-order-modal');
    if (open && modal) {
      event.preventDefault();
      pendingCancelForm = open.closest('form[data-cancel-order-form]');
      const number = modal.querySelector('[data-cancel-order-number]');
      if (number) number.textContent = `#${open.dataset.orderId || ''}`;
      if (typeof modal.showModal === 'function') modal.showModal();
      else modal.setAttribute('open', '');
      return;
    }

    if (event.target.closest('[data-cancel-order-close]') && modal) {
      event.preventDefault();
      if (typeof modal.close === 'function') modal.close();
      else modal.removeAttribute('open');
      pendingCancelForm = null;
      return;
    }

    if (event.target.closest('[data-cancel-order-confirm]') && modal) {
      event.preventDefault();
      const form = pendingCancelForm;
      pendingCancelForm = null;
      if (typeof modal.close === 'function') modal.close();
      else modal.removeAttribute('open');
      if (form) form.submit();
    }
  });

  document.addEventListener('click', event => {
    const modal = event.target.closest('#cancel-order-modal');
    if (modal && event.target === modal) {
      if (typeof modal.close === 'function') modal.close();
      else modal.removeAttribute('open');
      pendingCancelForm = null;
    }
  });

  window.addEventListener('popstate', () => navigate(window.location.href, { push: false }));
  // RecordStore styled confirmation for admin preview/master changes.
  let pendingAudioActionForm = null;
  document.addEventListener('click', event => {
    const open = event.target.closest('[data-audio-action-open]');
    const modal = document.getElementById('audio-action-modal');
    if (open && modal) {
      event.preventDefault();
      pendingAudioActionForm = open.closest('form[data-audio-action-form]');
      const title = modal.querySelector('#audio-action-modal-title');
      const message = modal.querySelector('#audio-action-modal-message');
      if (title) title.textContent = open.dataset.audioActionTitle || 'Confirm audio action';
      if (message) message.textContent = open.dataset.audioActionMessage || 'Please confirm this audio file change.';
      if (typeof modal.showModal === 'function') modal.showModal();
      else modal.setAttribute('open', '');
      return;
    }
    if (event.target.closest('[data-audio-action-close]') && modal) {
      event.preventDefault();
      if (typeof modal.close === 'function') modal.close(); else modal.removeAttribute('open');
      pendingAudioActionForm = null;
      return;
    }
    if (event.target.closest('[data-audio-action-confirm]') && modal) {
      event.preventDefault();
      const form = pendingAudioActionForm;
      pendingAudioActionForm = null;
      if (typeof modal.close === 'function') modal.close(); else modal.removeAttribute('open');
      if (form) {
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
      }
    }
  });
  document.addEventListener('click', event => {
    const modal = event.target.closest('#audio-action-modal');
    if (modal && event.target === modal) {
      if (typeof modal.close === 'function') modal.close(); else modal.removeAttribute('open');
      pendingAudioActionForm = null;
    }
  });
})();


// RecordStore v1.13.4 mobile navigation
document.addEventListener('click',function(e){const toggle=e.target.closest('.mobile-menu-toggle');const sidebar=document.querySelector('.sidebar');if(toggle&&sidebar){const open=sidebar.classList.toggle('mobile-menu-open');toggle.setAttribute('aria-expanded',open?'true':'false');const icon=toggle.querySelector('.mobile-menu-icon');if(icon)icon.textContent=open?'✕':'☰';return}if(e.target.closest('.mobile-nav-panel a')&&sidebar){sidebar.classList.remove('mobile-menu-open');const b=sidebar.querySelector('.mobile-menu-toggle');if(b){b.setAttribute('aria-expanded','false');const i=b.querySelector('.mobile-menu-icon');if(i)i.textContent='☰'}}});
window.addEventListener('resize',function(){if(innerWidth>820){const s=document.querySelector('.sidebar');if(s)s.classList.remove('mobile-menu-open')}});


// RecordStore v1.13.6 — lock the page behind the full-height mobile menu.
document.addEventListener('click', function (event) {
    if (event.target.closest('.mobile-menu-toggle')) {
        requestAnimationFrame(function () {
            const sidebar = document.querySelector('.sidebar');
            document.body.classList.toggle('mobile-nav-lock', !!(sidebar && sidebar.classList.contains('mobile-menu-open')));
        });
    }
    if (event.target.closest('.mobile-nav-panel a')) {
        document.body.classList.remove('mobile-nav-lock');
    }
});

// Page sharing: use the native share sheet where available and copy-link fallback elsewhere.
document.addEventListener('DOMContentLoaded', function () {
  const title = document.title;
  const url = window.location.href;
  const shareText = title.replace(/\s*[|·—-]\s*[^|·—-]+$/, '').trim();
  const addShareControls = function (anchor, label) {
    if (!anchor || document.querySelector('[data-page-share-ui]')) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'page-share';
    wrapper.dataset.pageShareUi = '1';
    wrapper.setAttribute('aria-label', 'Share this page');
    wrapper.innerHTML = '<span class="page-share-label">' + label + '</span>' +
      '<button type="button" data-share-page data-share-url="' + encodeURIComponent(url) + '" data-share-title="' + encodeURIComponent(title) + '" data-share-text="' + encodeURIComponent(shareText) + '">↗ Share</button>' +
      '<a href="https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url) + '" target="_blank" rel="noopener noreferrer">Facebook</a>' +
      '<a href="https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(shareText) + '" target="_blank" rel="noopener noreferrer">X</a>' +
      '<span class="page-share-status" data-share-status aria-live="polite"></span>';
    anchor.insertAdjacentElement('afterend', wrapper);
  };
  addShareControls(document.querySelector('.track-assurance-row'), 'Share track');
  if (!document.querySelector('[data-page-share-ui]')) addShareControls(document.querySelector('.artist-socials'), 'Share artist');
});

document.addEventListener('click', async function (event) {
  const trigger = event.target.closest('[data-share-page]');
  if (!trigger) return;
  event.preventDefault();
  const decode = value => { try { return decodeURIComponent(value); } catch (_) { return value; } };
  const url = decode(trigger.dataset.shareUrl || window.location.href);
  const title = decode(trigger.dataset.shareTitle || document.title);
  const text = decode(trigger.dataset.shareText || title);
  const status = trigger.parentElement?.querySelector('[data-share-status]');
  try {
    if (navigator.share) {
      await navigator.share({title, text, url});
    } else if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(url);
      if (status) {
        status.textContent = 'Link copied';
        window.setTimeout(() => { status.textContent = ''; }, 2200);
      }
    } else {
      window.prompt('Copy this link', url);
    }
  } catch (error) {
    if (error?.name !== 'AbortError' && status) status.textContent = 'Unable to share';
  }
});
window.addEventListener('resize', function () {
    if (window.innerWidth > 820) document.body.classList.remove('mobile-nav-lock');
});

// RecordStore mobile flyout toggles
document.addEventListener('click',function(e){if(window.innerWidth>820)return;const top=e.target.closest('.mobile-nav-panel .nav-flyout > .nav');if(!top)return;e.preventDefault();e.stopImmediatePropagation();const flyout=top.parentElement;const open=flyout.classList.toggle('is-open');top.setAttribute('aria-expanded',open?'true':'false')},true);

