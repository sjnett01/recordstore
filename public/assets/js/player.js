(() => {
  'use strict';

  const root = document.getElementById('audioPlayer');
  if (!root) return;

  const toggle = document.getElementById('playerToggle');
  const seek = document.getElementById('playerSeek');
  const seekTrack = document.getElementById('playerSeekTrack');
  const time = document.getElementById('playerTime');
  const duration = document.getElementById('playerDuration');
  const title = document.getElementById('playerTitle');
  const artist = document.getElementById('playerArtist');
  const art = document.getElementById('playerArt');
  const mute = document.getElementById('playerMute');
  const back = document.getElementById('playerBack');
  const close = document.getElementById('playerClose');
  const state = document.getElementById('playerState');
  const chip = root.querySelector('.preview-chip');
  const canvas = document.getElementById('playerSpectrum');
  const spectrumWrap = document.getElementById('playerSpectrumWrap');
  const seekFill = document.getElementById('playerSeekFill');
  const spectrumProgress = document.getElementById('playerSpectrumProgress');
  const ctx = canvas ? canvas.getContext('2d') : null;

  const loadRow = document.createElement('div');
  loadRow.className = 'player-load-row';
  loadRow.innerHTML = '<span data-load-label>Downloading preview</span><div class="player-load-track"><span></span></div><strong>0%</strong>';
  const loadLabel = loadRow.querySelector('[data-load-label]');
  const loadBar = loadRow.querySelector('.player-load-track span');
  const loadPercent = loadRow.querySelector('strong');
  root.querySelector('.player-heading')?.insertAdjacentElement('afterend', loadRow);

  const audio = new Audio();
  audio.preload = 'auto';

  let activePreviewUrl = '';
  let activeIsFullTrack = false;
  let activeObjectUrl = '';
  let previewLoadController = null;
  let previewCachePromise = null;
  let cachedPreviewSource = '';
  let usingCachedPreview = false;
  let audioContext = null;
  let analyser = null;
  let sourceNode = null;
  let frequencyData = null;
  let waveformData = null;
  let graphFailed = false;
  let raf = 0;
  let lastPersist = 0;
  let pendingSeekFraction = null;
  let isScrubbing = false;
  let lastCommittedSeek = -1;
  let seekSerial = 0;
  let analyticsTrackId = 0;
  let analyticsPlayed = false;
  let analyticsCompleted = false;
  let analyticsLastSeek = -1;

  const analyticsUrl = document.body?.dataset.previewAnalyticsUrl || 'preview-analytics.php';

  const sendPreviewAnalytics = (eventType, values = {}) => {
    if (activeIsFullTrack || !analyticsTrackId) return;
    const payload = JSON.stringify({track_id: analyticsTrackId, event_type: eventType, position_seconds: Math.round((values.position_seconds ?? audio.currentTime ?? 0) * 100) / 100, duration_seconds: Math.round((values.duration_seconds ?? safeDuration()) * 100) / 100, section_seconds: Math.floor((values.section_seconds ?? audio.currentTime ?? 0) / 10) * 10});
    try {
      if (navigator.sendBeacon) { navigator.sendBeacon(analyticsUrl, new Blob([payload], {type: 'application/json'})); return; }
      fetch(analyticsUrl, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json'}, body: payload, keepalive: true}).catch(() => {});
    } catch (_) {}
  };
  const storageKey = `${document.body?.dataset.storeKey || 'store'}.player.v16`;
  const fmt = seconds => {
    if (!Number.isFinite(seconds)) return '0:00';
    seconds = Math.max(0, Math.floor(seconds));
    return Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
  };

  const safeDuration = () => {
    if (Number.isFinite(audio.duration) && audio.duration > 0) return audio.duration;
    if (audio.seekable && audio.seekable.length) return audio.seekable.end(audio.seekable.length - 1);
    return 90;
  };

  const mediaDuration = () => {
    if (Number.isFinite(audio.duration) && audio.duration > 0) return audio.duration;
    // Generated previews are approximately 90 seconds. This fallback is only used
    // before metadata arrives; the pending seek is repeated once duration is known.
    return 90;
  };

  const setLoadProgress = percent => {
    const value = Math.max(0, Math.min(100, Math.round(percent || 0)));
    if (loadBar) loadBar.style.width = value + '%';
    if (loadPercent) loadPercent.textContent = value + '%';
    loadRow.classList.toggle('is-complete', value >= 100);
  };

  const setLoadMode = (fullTrack, ready) => {
    if (!loadLabel) return;
    loadLabel.textContent = ready ? 'Ready to seek' : (fullTrack ? 'Downloading full master' : 'Downloading preview');
  };

  const updateLoadProgress = () => {
    if (activeObjectUrl) {
      setLoadProgress(100);
      return;
    }
    const d = Number.isFinite(audio.duration) && audio.duration > 0 ? audio.duration : 0;
    let buffered = 0;
    if (d && audio.buffered.length) buffered = Math.min(d, audio.buffered.end(audio.buffered.length - 1));
    const percent = d ? Math.max(0, Math.min(100, Math.round((buffered / d) * 100))) : 0;
    const fullyBuffered = d > 0 && buffered >= d - 0.05;
    setLoadProgress(fullyBuffered ? 100 : percent);
    if (fullyBuffered) setLoadMode(activeIsFullTrack, true);
  };

  const canSeekNow = () => audio.readyState >= HTMLMediaElement.HAVE_METADATA && Number.isFinite(audio.duration) && audio.duration > 0;

  const setSeekVisual = pct => {
    const value = Math.max(0, Math.min(100, pct));
    seek.style.setProperty('--progress', `${value}%`);
    if (seekTrack) {
      seekTrack.style.setProperty('--progress', `${value}%`);
      seekTrack.setAttribute('aria-valuenow', String(Math.round(value)));
    }
    if (seekFill) seekFill.style.width = `${value}%`;
    if (spectrumProgress) spectrumProgress.style.width = `${value}%`;
  };

  const persist = force => {
    const now = performance.now();
    if (!force && now - lastPersist < 750) return;
    lastPersist = now;
    if (!activePreviewUrl) return;
    try {
      sessionStorage.setItem(storageKey, JSON.stringify({
        src: activePreviewUrl,
        title: title.textContent || 'Preview',
        artist: artist.textContent || document.body?.dataset.appName || '',
        art: art.src || '',
        currentTime: audio.currentTime || 0,
        muted: audio.muted,
        volume: audio.volume,
        playing: !audio.paused && !audio.ended
      }));
    } catch (_) {}
  };

  const syncPreviewButtons = () => {
    document.querySelectorAll('[data-preview]').forEach(button => {
      const buttonUrl = new URL(button.dataset.preview || '', window.location.href).href;
      const isActive = activePreviewUrl && buttonUrl === activePreviewUrl;
      const playing = isActive && !audio.paused && !audio.ended;
      const fullTrack = button.dataset.fullTrack === '1' || button.classList.contains('review-preview-button');
      button.classList.toggle('playing', playing);
      button.setAttribute('aria-label', playing ? (fullTrack ? 'Pause private master track' : 'Pause preview') : (fullTrack ? 'Play private master track' : 'Play preview'));
      const icon = button.querySelector('[data-play-icon]');
      if (icon) icon.textContent = playing ? '❚❚' : '▶';
      else if (button.classList.contains('preview-fab') || button.classList.contains('round')) button.textContent = playing ? '❚❚' : '▶';
    });
  };


  const releaseObjectUrl = () => {
    if (activeObjectUrl) {
      try { URL.revokeObjectURL(activeObjectUrl); } catch (_) {}
      activeObjectUrl = '';
    }
  };

  const startPreviewCache = src => {
    if (previewLoadController) previewLoadController.abort();
    previewLoadController = new AbortController();
    cachedPreviewSource = '';
    usingCachedPreview = false;

    previewCachePromise = fetch(src, {
      credentials: 'same-origin',
      cache: 'force-cache',
      signal: previewLoadController.signal
    }).then(response => {
      if (!response.ok) throw new Error(`Preview request failed (${response.status})`);
      return response.blob();
    }).then(blob => {
      if (!blob.size) throw new Error('Preview response was empty.');
      if (src !== activePreviewUrl) return '';
      releaseObjectUrl();
      activeObjectUrl = URL.createObjectURL(blob);
      cachedPreviewSource = src;
      return activeObjectUrl;
    }).catch(error => {
      if (error && error.name !== 'AbortError') console.warn('Application background preview cache failed:', error);
      return '';
    });

    return previewCachePromise;
  };

  const loadFullTrack = async (src, fullTrack) => {
    setLoadMode(fullTrack, false);
    if (previewLoadController) previewLoadController.abort();
    previewLoadController = new AbortController();
    const response = await fetch(src, {credentials: 'same-origin', cache: 'no-store', signal: previewLoadController.signal});
    if (!response.ok) throw new Error(`Master request failed (${response.status})`);
    const total = Number(response.headers.get('Content-Length') || 0);
    const reader = response.body.getReader();
    const chunks = [];
    let loaded = 0;
    while (true) {
      const part = await reader.read();
      if (part.done) break;
      chunks.push(part.value);
      loaded += part.value.byteLength;
      if (total) setLoadProgress((loaded / total) * 100);
    }
    const blob = new Blob(chunks, {type: response.headers.get('Content-Type') || 'audio/mpeg'});
    if (!blob.size) throw new Error('Master response was empty.');
    setLoadProgress(100);
    setLoadMode(fullTrack, true);
    activeObjectUrl = URL.createObjectURL(blob);
    cachedPreviewSource = src;
    return activeObjectUrl;
  };

  const ensureCachedPreview = async () => {
    if (!activePreviewUrl) return '';
    if (cachedPreviewSource === activePreviewUrl && activeObjectUrl) return activeObjectUrl;
    if (!previewCachePromise) startPreviewCache(activePreviewUrl);
    return previewCachePromise ? await previewCachePromise : '';
  };

  const switchToCachedPreview = async (targetTime, shouldPlay) => {
    const localSrc = await ensureCachedPreview();
    if (!localSrc) return false;

    const currentSrc = audio.currentSrc || audio.src || '';
    if (currentSrc !== localSrc) {
      audio.src = localSrc;
      audio.load();
      await waitForMediaEvent('loadedmetadata', 5000);
      usingCachedPreview = true;
    }

    const d = safeDuration();
    const exact = Math.max(0, Math.min(targetTime, Math.max(0, d - 0.02)));
    if (Math.abs((audio.currentTime || 0) - exact) > 0.03) {
      audio.currentTime = exact;
      await waitForMediaEvent('seeked', 4000);
    }
    if (shouldPlay && audio.paused && !audio.ended) {
      await audio.play();
    }
    return true;
  };

  const ensureAudioGraph = async () => {
    if (graphFailed) return false;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) { graphFailed = true; return false; }
    try {
      if (!audioContext) {
        audioContext = new AC();
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 1024;
        analyser.smoothingTimeConstant = 0.82;
        analyser.minDecibels = -96;
        analyser.maxDecibels = -18;
        frequencyData = new Uint8Array(analyser.frequencyBinCount);
        waveformData = new Uint8Array(analyser.fftSize);
        sourceNode = audioContext.createMediaElementSource(audio);
        sourceNode.connect(analyser);
        analyser.connect(audioContext.destination);
      }
      if (audioContext.state === 'suspended') await audioContext.resume();
      return true;
    } catch (error) {
      graphFailed = true;
      console.warn('Application analyser unavailable:', error);
      return false;
    }
  };

  const resizeCanvas = () => {
    if (!canvas || !ctx) return;
    const rect = canvas.getBoundingClientRect();
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const width = Math.max(2, Math.floor(rect.width * dpr));
    const height = Math.max(2, Math.floor(rect.height * dpr));
    if (canvas.width !== width || canvas.height !== height) {
      canvas.width = width;
      canvas.height = height;
    }
  };

  const drawVisualizer = () => {
    if (!canvas || !ctx) return;
    resizeCanvas();
    const w = canvas.width;
    const h = canvas.height;
    ctx.clearRect(0, 0, w, h);

    const playing = !audio.paused && !audio.ended;
    const bars = Math.max(42, Math.min(96, Math.floor(w / 10)));
    const gap = Math.max(2, w * 0.0022);
    const barW = Math.max(1.5, (w - gap * (bars - 1)) / bars);
    const gradient = ctx.createLinearGradient(0, 0, w, 0);
    gradient.addColorStop(0, '#763cff');
    gradient.addColorStop(.5, '#c155ff');
    gradient.addColorStop(1, '#22cfff');
    ctx.fillStyle = gradient;
    ctx.shadowColor = 'rgba(132,72,255,.42)';
    ctx.shadowBlur = playing ? 12 : 3;

    if (playing && analyser && frequencyData && !graphFailed) analyser.getByteFrequencyData(frequencyData);
    const maxBin = frequencyData ? Math.min(frequencyData.length - 1, 220) : 0;

    for (let i = 0; i < bars; i++) {
      let level = 0.08 + 0.05 * ((Math.sin(i * .63) + 1) / 2);
      if (playing && frequencyData && !graphFailed) {
        const t = i / Math.max(1, bars - 1);
        const index = Math.min(maxBin, Math.floor(Math.pow(t, 1.7) * maxBin));
        level = Math.max(.035, Math.pow(frequencyData[index] / 255, .67));
      }
      const bh = Math.max(2, h * Math.min(.92, level));
      const x = i * (barW + gap);
      const y = (h - bh) / 2;
      ctx.globalAlpha = playing ? .98 : .28;
      ctx.fillRect(x, y, barW, bh);
    }
    ctx.globalAlpha = 1;
    ctx.shadowBlur = 0;

    if (playing && analyser && waveformData && !graphFailed) {
      analyser.getByteTimeDomainData(waveformData);
      ctx.beginPath();
      ctx.lineWidth = Math.max(1, window.devicePixelRatio || 1);
      ctx.strokeStyle = 'rgba(238,232,255,.56)';
      const slice = w / (waveformData.length - 1);
      waveformData.forEach((sample, i) => {
        const y = (sample / 255) * h;
        const x = i * slice;
        if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      });
      ctx.stroke();
    }

    raf = requestAnimationFrame(drawVisualizer);
  };

  const updateTimeline = () => {
    if (isScrubbing) return;
    const d = safeDuration();
    const current = Math.min(audio.currentTime || 0, d);
    const pct = d > 0 ? current / d * 100 : 0;
    time.textContent = fmt(current);
    duration.textContent = fmt(d);
    seek.value = String(Math.round(pct * 10));
    setSeekVisual(pct);
    persist(false);
  };

  const waitForMediaEvent = (eventName, timeoutMs = 3000) => new Promise(resolve => {
    let done = false;
    const finish = () => {
      if (done) return;
      done = true;
      clearTimeout(timer);
      audio.removeEventListener(eventName, finish);
      resolve();
    };
    const timer = setTimeout(finish, timeoutMs);
    audio.addEventListener(eventName, finish, { once: true });
  });

  const seekToFraction = async fraction => {
    if (!activePreviewUrl) return;
    fraction = Math.max(0, Math.min(1, Number(fraction) || 0));
    pendingSeekFraction = fraction;

    const d = mediaDuration();
    const target = d * fraction;
    time.textContent = fmt(target);
    seek.value = String(Math.round(fraction * 1000));
    setSeekVisual(fraction * 100);

    if (!canSeekNow()) return;

    const exactTarget = Math.max(0, Math.min(target, Math.max(0, audio.duration - 0.02)));
    if (Math.abs(exactTarget - analyticsLastSeek) >= 2) { sendPreviewAnalytics('seek', {position_seconds: exactTarget, section_seconds: exactTarget}); analyticsLastSeek = exactTarget; }
    const serial = ++seekSerial;
    const wasPlaying = !audio.paused && !audio.ended;
    state.textContent = 'PREPARING SEEK';
    pendingSeekFraction = null;
    lastCommittedSeek = exactTarget;

    try {
      // Initial playback is streamed immediately for fast startup. The complete
      // 90-second preview is cached quietly in the background. The first manual
      // seek waits for that small cache, then all seeking is against the local Blob.
      // This avoids the browser reconnect/reset behaviour we saw with network seeks.
      const switched = activeIsFullTrack ? false : await switchToCachedPreview(exactTarget, wasPlaying);
      if (serial !== seekSerial) return;

      if (!switched) {
        state.textContent = 'SEEKING';
        audio.currentTime = exactTarget;
        await waitForMediaEvent('seeked', 4000);
        if (wasPlaying && audio.paused && !audio.ended) await audio.play();
      }

      updateTimeline();
      state.textContent = audio.paused ? 'PAUSED' : 'PLAYING';
      persist(true);
    } catch (error) {
      if (serial === seekSerial) state.textContent = audio.paused ? 'PAUSED' : 'BUFFERING';
      console.warn('Application seek failed:', error);
    }
  };

  const applyPendingSeek = () => {
    if (pendingSeekFraction !== null && canSeekNow()) seekToFraction(pendingSeekFraction);
  };

  const loadTrack = async button => {
    await ensureAudioGraph();
    const fullTrack = button.dataset.fullTrack === '1' || button.classList.contains('review-preview-button');
    const sourceUrl = new URL(button.dataset.preview, window.location.href);
    if (fullTrack) sourceUrl.searchParams.delete('preview');
    const src = sourceUrl.href;

    if (activePreviewUrl === src) {
      root.hidden = false;
      root.classList.add('is-visible');
      if (audio.paused) await audio.play(); else audio.pause();
      return;
    }

    if (analyticsTrackId && analyticsPlayed && !analyticsCompleted && audio.currentTime > 1) sendPreviewAnalytics('skip');

    activePreviewUrl = src;
    activeIsFullTrack = fullTrack;
    analyticsTrackId = fullTrack ? 0 : Number(button.dataset.trackId || new URL(button.dataset.preview, window.location.href).searchParams.get('id') || 0);
    analyticsPlayed = false;
    analyticsCompleted = false;
    analyticsLastSeek = -1;
    setLoadMode(fullTrack, false);
    if (seek) seek.disabled = true;
    if (chip) chip.textContent = fullTrack ? 'PRIVATE MASTER' : '90 SEC PREVIEW';
    title.textContent = button.dataset.title || 'Preview';
    artist.textContent = button.dataset.artist || document.body?.dataset.appName || '';
    if (button.dataset.art) art.src = button.dataset.art;
    root.hidden = false;
    root.classList.add('is-visible');
    state.textContent = 'LOADING';
    loadRow.classList.remove('is-complete');
    setLoadProgress(0);
    time.textContent = '0:00';
    duration.textContent = '1:30';
    seek.value = '0';
    setSeekVisual(0);
    requestAnimationFrame(resizeCanvas);

    releaseObjectUrl();
    if (previewLoadController) previewLoadController.abort();
    previewCachePromise = null;
    cachedPreviewSource = '';
    usingCachedPreview = false;

    // Stream immediately so slow connections can start listening as soon as the
    // browser has enough data. Cache the same preview in parallel for local seeks.
    audio.src = await loadFullTrack(src, activeIsFullTrack);
    audio.load();
    await waitForMediaEvent('loadedmetadata', 5000);
    updateLoadProgress();
    await audio.play();
    if (seek) seek.disabled = false;
    persist(true);
  };

  close?.addEventListener('click', () => {
    audio.pause();
    root.hidden = true;
    root.classList.remove('is-visible', 'is-playing');
  });

  document.addEventListener('click', async event => {
    const button = closestElement(event.target, '[data-preview]');
    if (!button) return;
    event.preventDefault();
    try { await loadTrack(button); }
    catch (error) { console.warn('Preview playback failed:', error); state.textContent = 'ERROR'; }
  });

  audio.addEventListener('play', async () => {
    await ensureAudioGraph();
    toggle.textContent = '❚❚';
    state.textContent = 'PLAYING';
    root.classList.add('is-playing');
    syncPreviewButtons();
    if (!activeIsFullTrack && !analyticsPlayed) { analyticsPlayed = true; sendPreviewAnalytics('play'); }
    persist(true);
  });
  ['progress', 'loadedmetadata', 'canplay', 'canplaythrough', 'timeupdate', 'durationchange'].forEach(eventName => {
    audio.addEventListener(eventName, updateLoadProgress);
  });
  audio.addEventListener('pause', () => {
    toggle.textContent = '▶';
    state.textContent = audio.currentTime > 0 ? 'PAUSED' : 'READY';
    root.classList.remove('is-playing');
    syncPreviewButtons();
    persist(true);
  });
  audio.addEventListener('playing', () => { state.textContent = 'PLAYING'; });
  audio.addEventListener('waiting', () => { state.textContent = 'BUFFERING'; });
  audio.addEventListener('seeking', () => { state.textContent = 'SEEKING'; });
  audio.addEventListener('seeked', () => { state.textContent = audio.paused ? 'PAUSED' : 'PLAYING'; updateTimeline(); });
  audio.addEventListener('loadedmetadata', () => { applyPendingSeek(); updateTimeline(); });
  audio.addEventListener('durationchange', () => { applyPendingSeek(); updateTimeline(); });
  audio.addEventListener('progress', updateTimeline);
  audio.addEventListener('timeupdate', updateTimeline);
  audio.addEventListener('ended', () => {
    toggle.textContent = '▶';
    state.textContent = 'FINISHED';
    root.classList.remove('is-playing');
    syncPreviewButtons();
    if (!activeIsFullTrack && analyticsPlayed && !analyticsCompleted) { analyticsCompleted = true; sendPreviewAnalytics('complete', {position_seconds: audio.duration, duration_seconds: audio.duration}); }
    setSeekVisual(100);
    persist(true);
  });
  audio.addEventListener('error', () => { state.textContent = 'ERROR'; root.classList.remove('is-playing'); });

  toggle.addEventListener('click', async () => {
    if (!activePreviewUrl) return;
    await ensureAudioGraph();
    if (audio.paused) audio.play().catch(() => { state.textContent = 'ERROR'; }); else audio.pause();
  });
  back.addEventListener('click', () => seekToFraction(0));
  mute.addEventListener('click', () => {
    audio.muted = !audio.muted;
    mute.textContent = audio.muted ? '○' : '◕';
    mute.classList.toggle('muted', audio.muted);
    persist(true);
  });

  // Scrubbing must NOT issue a media seek on every pointer movement. Doing that
  // creates a burst of HTTP range requests and causes the exact stutter/restart
  // behaviour seen in browsers. While dragging we update the UI only, then issue
  // one exact seek when the pointer is released.
  const previewScrubPosition = () => {
    const fraction = Math.max(0, Math.min(1, Number(seek.value) / 1000));
    const target = mediaDuration() * fraction;
    time.textContent = fmt(target);
    setSeekVisual(fraction * 100);
  };

  seek.addEventListener('pointerdown', () => {
    isScrubbing = true;
  });
  seek.addEventListener('input', previewScrubPosition);
  seek.addEventListener('pointercancel', () => {
    isScrubbing = false;
    updateTimeline();
  });

  // Range controls already emit one `change` event when a mouse/touch drag is
  // committed. Use that as the ONLY media-seek trigger. Previously pointerup and
  // change both committed, causing two overlapping seeks.
  seek.addEventListener('change', () => {
    isScrubbing = false;
    seekToFraction(Number(seek.value) / 1000);
  });

  // Clicking anywhere in the larger track seeks directly to that position.
  const seekFromElementPointer = (element, event) => {
    const rect = element.getBoundingClientRect();
    if (!rect.width) return;
    seekToFraction((event.clientX - rect.left) / rect.width);
  };

  if (seekTrack) {
    seekTrack.addEventListener('pointerdown', event => {
      if (event.target !== seek) seekFromElementPointer(seekTrack, event);
    });
  }

  // The visualizer is deliberately a second, large seek target. This makes it
  // obvious that a listener can jump around the 90-second montage without
  // having to hit a tiny range thumb.
  if (spectrumWrap) {
    spectrumWrap.addEventListener('pointerdown', event => {
      seekFromElementPointer(spectrumWrap, event);
    });
  }

  const refreshUpdatedPreview = async () => {
    const marker = document.querySelector('[data-preview-updated-id]');
    const id = marker?.dataset.previewUpdatedId || '';
    if (!id || !activePreviewUrl) return;
    const current = new URL(activePreviewUrl, window.location.href);
    if (current.searchParams.get('id') !== id) return;
    const wasPlaying = !audio.paused;
    const fraction = Number.isFinite(audio.duration) && audio.duration > 0 ? audio.currentTime / audio.duration : 0;
    current.searchParams.set('v', String(Date.now()));
    activePreviewUrl = current.href;
    releaseObjectUrl();
    if (previewLoadController) previewLoadController.abort();
    previewCachePromise = null;
    cachedPreviewSource = '';
    usingCachedPreview = false;
    audio.src = activePreviewUrl;
    audio.load();
    try {
      await waitForMediaEvent('loadedmetadata', 5000);
      if (fraction > 0 && audio.duration > 0) audio.currentTime = Math.min(audio.duration - 0.05, audio.duration * fraction);
      if (wasPlaying) await audio.play();
      startPreviewCache(activePreviewUrl);
      persist(true);
    } catch (error) {
      console.warn('Application preview refresh failed:', error);
    }
  };

  document.addEventListener('app:navigated', () => {
    syncPreviewButtons();
    refreshUpdatedPreview();
  });
  window.addEventListener('resize', resizeCanvas, { passive: true });
  window.addEventListener('beforeunload', () => { persist(true); releaseObjectUrl(); });

  // Restore UI/state after an unavoidable hard refresh. Browsers may require a
  // user gesture before autoplay; the position and metadata are still restored.
  try {
    const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    if (saved && saved.src) {
      activePreviewUrl = saved.src;
      title.textContent = saved.title || 'Preview';
      artist.textContent = saved.artist || document.body?.dataset.appName || '';
      if (saved.art) art.src = saved.art;
      audio.muted = !!saved.muted;
      audio.volume = Number.isFinite(saved.volume) ? saved.volume : 1;
      root.hidden = false;
      root.classList.add('is-visible');
      state.textContent = 'LOADING';
      audio.src = saved.src;
      audio.load();
      startPreviewCache(saved.src);
      audio.addEventListener('loadedmetadata', () => {
        if (Number.isFinite(saved.currentTime) && safeDuration() > 0) {
          audio.currentTime = Math.min(saved.currentTime, Math.max(0, safeDuration() - 0.02));
        }
        updateTimeline();
        if (saved.playing) audio.play().catch(() => { state.textContent = 'PAUSED'; });
      }, { once: true });
    }
  } catch (_) {}

  raf = requestAnimationFrame(drawVisualizer);
})();
