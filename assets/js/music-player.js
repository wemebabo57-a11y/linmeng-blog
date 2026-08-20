;(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var container = document.querySelector('.music-player');
    if (!container) return;

    var playlist;
    try {
      playlist = JSON.parse(container.getAttribute('data-playlist'));
    } catch (e) {
      console.error('[音乐播放器] 播放列表解析失败:', e);
      return;
    }
    if (!Array.isArray(playlist) || playlist.length === 0) {
      console.error('[音乐播放器] 播放列表为空');
      return;
    }

    var audio = new Audio();
    var currentIndex = 0;
    var isDragging = false;
    var listVisible = false;
    var hasError = false;
    var isPlaying = false;
    var usingProxy = false;       // 当前是否走代理
    var proxyFailed = false;      // 当前歌曲代理是否已失败（用于回退直连）
    var errorCount = 0;           // 连续错误计数，达到 3 次后停止自动跳转
    var autoSkipTimer = null;     // 自动跳转下一首的延时句柄

    audio.preload = 'metadata';
    audio.volume = 0.7;

    // localStorage 在 Safari 隐私模式 / 旧浏览器中访问会抛错，需 try-catch
    var savedIndex = null;
    var savedVol = null;
    try {
      savedIndex = localStorage.getItem('mp_index');
      savedVol = localStorage.getItem('mp_volume');
    } catch (e) {
      console.warn('[音乐播放器] localStorage 不可用，状态不会被保存:', e);
    }
    if (savedIndex !== null) {
      currentIndex = parseInt(savedIndex, 10) || 0;
      if (currentIndex >= playlist.length) currentIndex = 0;
    }
    if (savedVol !== null) {
      audio.volume = Math.max(0, Math.min(1, parseFloat(savedVol)));
    }

    buildUI();

    var coverImg = container.querySelector('.mp-cover-img');
    var titleEl = container.querySelector('.mp-title');
    var artistEl = container.querySelector('.mp-artist');
    var progressBar = container.querySelector('.mp-progress-bar');
    var progressDot = container.querySelector('.mp-progress-dot');
    var progressWrap = container.querySelector('.mp-progress-wrap');
    var curTimeEl = container.querySelector('.mp-time-current');
    var totalTimeEl = container.querySelector('.mp-time-total');
    var btnPlay = container.querySelector('.mp-btn-play');
    var btnPrev = container.querySelector('.mp-btn-prev');
    var btnNext = container.querySelector('.mp-btn-next');
    var volSlider = container.querySelector('.mp-vol-slider');
    var volIcon = container.querySelector('.mp-vol-icon');
    var listToggle = container.querySelector('.mp-list-toggle');
    var listWrap = container.querySelector('.mp-list');
    var errorActions = null;       // 错误态操作按钮容器（重试/跳过）

    function buildUI() {
      container.innerHTML = '';
      container.className = 'music-player mp-modern';

      var coverWrap = el('div', 'mp-cover');
      var coverSpin = el('div', 'mp-cover-disc');
      var coverImg = el('img', 'mp-cover-img');
      coverImg.alt = '封面';
      coverSpin.appendChild(coverImg);
      coverWrap.appendChild(coverSpin);

      var infoWrap = el('div', 'mp-info');
      var titleEl = el('div', 'mp-title');
      var artistEl = el('div', 'mp-artist');
      infoWrap.appendChild(titleEl);
      infoWrap.appendChild(artistEl);

      var progressWrap = el('div', 'mp-progress-wrap');
      progressWrap.setAttribute('role', 'slider');
      progressWrap.setAttribute('aria-label', '播放进度');
      progressWrap.setAttribute('aria-valuemin', '0');
      progressWrap.setAttribute('aria-valuemax', '100');
      progressWrap.setAttribute('aria-valuenow', '0');
      var progressBar = el('div', 'mp-progress-bar');
      var progressDot = el('span', 'mp-progress-dot');
      progressBar.appendChild(progressDot);
      progressWrap.appendChild(progressBar);

      var timeWrap = el('div', 'mp-time');
      var curTimeEl = el('span', 'mp-time-current');
      var totalTimeEl = el('span', 'mp-time-total');
      curTimeEl.textContent = '0:00';
      totalTimeEl.textContent = '0:00';
      timeWrap.appendChild(curTimeEl);
      timeWrap.appendChild(totalTimeEl);

      var controls = el('div', 'mp-controls');
      var btnPrev = el('button', 'mp-btn mp-btn-prev');
      btnPrev.innerHTML = svgIcon('skip-back');
      btnPrev.setAttribute('aria-label', '上一首');
      var btnPlay = el('button', 'mp-btn mp-btn-play');
      btnPlay.innerHTML = svgIcon('play');
      btnPlay.setAttribute('aria-label', '播放');
      btnPlay.setAttribute('aria-pressed', 'false');
      var btnNext = el('button', 'mp-btn mp-btn-next');
      btnNext.innerHTML = svgIcon('skip-forward');
      btnNext.setAttribute('aria-label', '下一首');
      controls.appendChild(btnPrev);
      controls.appendChild(btnPlay);
      controls.appendChild(btnNext);

      // 错误态操作按钮（默认隐藏，连续多次失败后展示）
      errorActions = el('div', 'mp-error-actions');
      errorActions.style.display = 'none';
      var btnRetry = el('button', 'mp-btn mp-btn-retry');
      btnRetry.innerHTML = svgIcon('play') + '<span>重试</span>';
      btnRetry.setAttribute('aria-label', '重试当前歌曲');
      var btnSkipErr = el('button', 'mp-btn mp-btn-skip');
      btnSkipErr.innerHTML = svgIcon('skip-forward') + '<span>跳过</span>';
      btnSkipErr.setAttribute('aria-label', '跳到下一首');
      errorActions.appendChild(btnRetry);
      errorActions.appendChild(btnSkipErr);
      btnRetry.addEventListener('click', function () {
        loadSong(currentIndex);
        var p = audio.play();
        if (p && p.catch) p.catch(function () {});
      });
      btnSkipErr.addEventListener('click', function () {
        nextSong();
      });

      var volumeWrap = el('div', 'mp-volume');
      var volIcon = el('span', 'mp-vol-icon');
      volIcon.innerHTML = svgIcon('volume-2');
      var volSlider = el('input', 'mp-vol-slider');
      volSlider.type = 'range';
      volSlider.min = '0';
      volSlider.max = '1';
      volSlider.step = '0.01';
      volSlider.value = audio.volume;
      volSlider.setAttribute('aria-label', '音量');
      volumeWrap.appendChild(volIcon);
      volumeWrap.appendChild(volSlider);

      var listHeader = el('div', 'mp-list-header');
      var listToggle = el('button', 'mp-list-toggle');
      listToggle.innerHTML = '<span>播放列表</span><span class="mp-list-count">' + playlist.length + '</span>';
      listToggle.setAttribute('aria-label', '展开/收起播放列表');
      listToggle.setAttribute('aria-expanded', 'false');
      listHeader.appendChild(listToggle);

      var listWrap = el('div', 'mp-list');
      playlist.forEach(function (song, i) {
        var item = el('div', 'mp-list-item' + (i === currentIndex ? ' mp-list-active' : ''));
        item.setAttribute('data-index', i);

        var itemNum = el('span', 'mp-list-num');
        itemNum.textContent = (i + 1 < 10 ? '0' : '') + (i + 1);

        var itemInfo = el('div', 'mp-list-info');
        var itemTitle = el('div', 'mp-list-title');
        itemTitle.textContent = song.title || '未知歌曲';
        var itemArtist = el('div', 'mp-list-artist');
        itemArtist.textContent = song.artist || '未知艺术家';
        itemInfo.appendChild(itemTitle);
        itemInfo.appendChild(itemArtist);

        item.appendChild(itemNum);
        item.appendChild(itemInfo);
        listWrap.appendChild(item);
      });

      container.appendChild(coverWrap);
      container.appendChild(infoWrap);
      container.appendChild(progressWrap);
      container.appendChild(timeWrap);
      container.appendChild(controls);
      container.appendChild(errorActions);
      container.appendChild(volumeWrap);
      container.appendChild(listHeader);
      container.appendChild(listWrap);
    }

    function svgIcon(name) {
      var icons = {
        'play': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3"/></svg>',
        'pause': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>',
        'skip-back': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="19 20 9 12 19 4 19 20"/><line x1="5" y1="19" x2="5" y2="5"/></svg>',
        'skip-forward': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 4 15 12 5 20 5 4"/><line x1="19" y1="5" x2="19" y2="19"/></svg>',
        'volume-2': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>',
        'volume-x': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>',
        'music': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
        'list': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>'
      };
      return icons[name] || '';
    }

    function el(tag, className) {
      var node = document.createElement(tag);
      if (className) node.className = className;
      return node;
    }

    function fmtTime(sec) {
      if (isNaN(sec)) return '0:00';
      var m = Math.floor(sec / 60);
      var s = Math.floor(sec % 60);
      return m + ':' + (s < 10 ? '0' : '') + s;
    }

    // 判断 URL 是否需要通过后端代理获取
    // 仅在「需要 Referer / 会跨域拦截」的受限域名时才走代理
    // 以下域名需要 Referer/CORS 绕过，统一通过 music-proxy.php 转发
    function needsProxy(url) {
      if (!url) return false;
      var lower = url.toLowerCase();
      // 这些域名在浏览器直连时会被 Referer/校验拦截或返回 403/404，必须走后端代理
      var proxyDomains = [
        'music.163.com',          // 网易云：外链跳转需 Referer
        'music.126.net',          // 网易云 CDN：缺 Referer 易 403
        'qqmusic.qq.com',         // QQ 音乐
        'tc.qq.com',              // QQ 音乐 CDN
        'y.qq.com',               // QQ 音乐门户
        'kugou.com',              // 酷狗
        'kuwo.cn',                // 酷我
        'xiami.com'               // 虾米
      ];
      for (var i = 0; i < proxyDomains.length; i++) {
        if (lower.indexOf(proxyDomains[i]) !== -1) return true;
      }
      return false;
    }

    function loadSong(index) {
      var song = playlist[index];
      // 清理上一次的自动跳转计时器，避免叠加
      if (autoSkipTimer) { clearTimeout(autoSkipTimer); autoSkipTimer = null; }
      hasError = false;
      proxyFailed = false;
      usingProxy = false;
      showErrorActions(false);
      titleEl.style.color = '';
      titleEl.textContent = song.title || '未知歌曲';
      artistEl.textContent = song.artist || '未知艺术家';

      if (coverImg) {
        coverImg.src = song.cover || '';
        coverImg.onerror = function () {
          coverImg.style.display = 'none';
        };
        coverImg.onload = function () {
          coverImg.style.display = 'block';
        };
      }

      curTimeEl.textContent = '0:00';
      totalTimeEl.textContent = '0:00';
      progressBar.style.width = '0%';

      // 始终更新列表高亮，无论是否有音源
      var items = listWrap.querySelectorAll('.mp-list-item');
      items.forEach(function (item, i) {
        item.classList.toggle('mp-list-active', i === index);
      });

      if (!song.url) {
        console.error('[音乐播放器] 第 ' + index + ' 首歌没有URL');
        titleEl.textContent = '暂无音源';
        titleEl.style.color = 'var(--danger-color)';
        hasError = true;
        try { localStorage.setItem('mp_index', index); } catch (e) {}
        return;
      }

      // 仅受限域名走代理；第三方 / 自托管直链直接播放，避免被白名单 403
      var audioSrc = song.url;
      if (needsProxy(song.url)) {
        audioSrc = '/music-proxy.php?url=' + encodeURIComponent(song.url);
        usingProxy = true;
      }

      // 加载骨架：在音频开始加载前显示，元数据/错误时移除
      container.classList.add('mp-loading');
      audio.src = audioSrc;
      audio.load();

      try { localStorage.setItem('mp_index', index); } catch (e) {}
    }

    function togglePlay() {
      if (hasError) {
        console.warn('[音乐播放器] 当前歌曲有错误，无法播放');
        return;
      }

      if (audio.paused) {
        var promise = audio.play();
        if (promise && promise.catch) {
          promise.catch(function (err) {
            // 自动播放策略被拒时静默处理（NotAllowedError），仅 console 提示
            // 避免用 alert 阻塞 UI，移动端体验更友好
            console.warn('[音乐播放器] 播放失败:', err.name, err.message);
            if (err && err.name === 'NotAllowedError') {
              // 自动播放被拒，UI 保持暂停态，等待用户下次点击
              updatePlayBtn();
            } else {
              titleEl.textContent = '播放失败：' + (err.message || '未知错误');
              titleEl.style.color = 'var(--danger-color)';
            }
          });
        }
      } else {
        audio.pause();
      }
    }

    function updatePlayBtn() {
      isPlaying = !audio.paused;
      btnPlay.innerHTML = isPlaying ? svgIcon('pause') : svgIcon('play');
      btnPlay.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
      btnPlay.setAttribute('aria-label', isPlaying ? '暂停' : '播放');
      container.classList.toggle('mp-playing', isPlaying);
    }

    function prevSong() {
      currentIndex = (currentIndex - 1 + playlist.length) % playlist.length;
      loadSong(currentIndex);
      var promise = audio.play();
      if (promise && promise.catch) {
        promise.catch(function (err) {
          console.error('[音乐播放器] 播放失败:', err);
        });
      }
    }

    function nextSong() {
      currentIndex = (currentIndex + 1) % playlist.length;
      loadSong(currentIndex);
      var promise = audio.play();
      if (promise && promise.catch) {
        promise.catch(function (err) {
          console.error('[音乐播放器] 播放失败:', err);
        });
      }
    }

    // 轻提示 toast：自动 3s 后消失
    function showToast(message) {
      var existing = container.querySelector('.mp-toast');
      if (existing) existing.remove();
      var toast = el('div', 'mp-toast');
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
      toast.textContent = message;
      container.appendChild(toast);
      setTimeout(function () { if (toast.parentNode) toast.remove(); }, 3000);
    }

    // 切换错误态操作按钮（重试 / 跳过）的显隐
    function showErrorActions(show) {
      if (errorActions) errorActions.style.display = show ? 'flex' : 'none';
    }

    function getProgressRatio(e) {
      var rect = progressWrap.getBoundingClientRect();
      var clientX = e.clientX !== undefined ? e.clientX : (e.touches && e.touches[0] ? e.touches[0].clientX : 0);
      var x = clientX - rect.left;
      return Math.max(0, Math.min(1, x / rect.width));
    }

    function seekTo(ratio) {
      if (audio.duration && isFinite(audio.duration)) {
        audio.currentTime = ratio * audio.duration;
      }
    }

    btnPlay.addEventListener('click', togglePlay);
    btnPrev.addEventListener('click', prevSong);
    btnNext.addEventListener('click', nextSong);

    audio.addEventListener('play', function () {
      updatePlayBtn();
    });
    audio.addEventListener('pause', function () {
      updatePlayBtn();
    });
    audio.addEventListener('ended', function () {
      nextSong();
    });

    audio.addEventListener('error', function () {
      var err = audio.error;
      var code = err ? err.code : 0;
      // 代理失败时，自动回退直连原 URL（仅尝试一次，避免循环）
      if (usingProxy && !proxyFailed && code !== 1) {
        proxyFailed = true;
        var song = playlist[currentIndex];
        if (song && song.url) {
          console.warn('[音乐播放器] 代理加载失败，回退直连原 URL');
          usingProxy = false;
          audio.src = song.url;
          audio.load();
          return;
        }
      }
      hasError = true;
      container.classList.remove('mp-loading');
      var msg = '加载失败';
      if (err) {
        switch (err.code) {
          case 1: msg = '加载已中止'; break;
          case 2: msg = '网络错误'; break;
          case 3: msg = '解码失败'; break;
          case 4: msg = '不支持的格式或404'; break;
        }
      }
      console.error('[音乐播放器] 音频错误:', msg, err);
      titleEl.textContent = msg;
      titleEl.style.color = 'var(--danger-color)';

      // 错误自动恢复：仅在「终端错误」时触发（代理已回退失败，或本就直连）
      // code === 1 为用户中止，不处理
      if (code !== 1) {
        errorCount++;
        if (errorCount < 3 && playlist.length > 1) {
          // 短暂展示错误后自动跳到下一首
          if (autoSkipTimer) clearTimeout(autoSkipTimer);
          autoSkipTimer = setTimeout(function () {
            showToast('音源失效，已自动切换');
            nextSong();
          }, 1500);
        } else if (errorCount >= 3) {
          // 连续多首失败，停止自动跳转并展示手动操作按钮
          if (autoSkipTimer) { clearTimeout(autoSkipTimer); autoSkipTimer = null; }
          titleEl.textContent = '多首音源失效，请检查接口配置';
          titleEl.style.color = 'var(--danger-color)';
          showErrorActions(true);
        }
      }
    });

    audio.addEventListener('loadedmetadata', function () {
      hasError = false;
      // 成功加载，重置错误计数并清理自动跳转
      errorCount = 0;
      if (autoSkipTimer) { clearTimeout(autoSkipTimer); autoSkipTimer = null; }
      container.classList.remove('mp-loading');
      titleEl.style.color = '';
      totalTimeEl.textContent = fmtTime(audio.duration);
    });

    audio.addEventListener('timeupdate', function () {
      if (isDragging) return;
      var dur = audio.duration;
      if (dur && isFinite(dur)) {
        var pct = (audio.currentTime / dur) * 100;
        progressBar.style.width = pct + '%';
        curTimeEl.textContent = fmtTime(audio.currentTime);
        progressWrap.setAttribute('aria-valuenow', String(Math.floor(pct)));
      }
    });

    progressWrap.addEventListener('mousedown', function (e) {
      isDragging = true;
      var ratio = getProgressRatio(e);
      progressBar.style.width = (ratio * 100) + '%';
      curTimeEl.textContent = fmtTime(ratio * (audio.duration || 0));
      e.preventDefault();
    });

    document.addEventListener('mousemove', function (e) {
      if (!isDragging) return;
      var ratio = getProgressRatio(e);
      progressBar.style.width = (ratio * 100) + '%';
      curTimeEl.textContent = fmtTime(ratio * (audio.duration || 0));
    });

    document.addEventListener('mouseup', function (e) {
      if (!isDragging) return;
      isDragging = false;
      seekTo(getProgressRatio(e));
    });

    progressWrap.addEventListener('touchstart', function (e) {
      isDragging = true;
      var ratio = getProgressRatio(e);
      progressBar.style.width = (ratio * 100) + '%';
    }, { passive: true });

    document.addEventListener('touchmove', function (e) {
      if (!isDragging) return;
      // 拖动进度条时阻止页面跟随滚动（必须 passive:false 才能 preventDefault）
      e.preventDefault();
      var ratio = getProgressRatio(e);
      progressBar.style.width = (ratio * 100) + '%';
    }, { passive: false });

    document.addEventListener('touchend', function (e) {
      if (!isDragging) return;
      isDragging = false;
      if (e.changedTouches && e.changedTouches.length) {
        var touch = e.changedTouches[0];
        seekTo(getProgressRatio(touch));
      }
    });

    volSlider.addEventListener('input', function () {
      audio.volume = parseFloat(volSlider.value);
      try { localStorage.setItem('mp_volume', audio.volume); } catch (e) {}
      updateVolIcon();
    });

    function updateVolIcon() {
      if (audio.volume === 0) {
        volIcon.innerHTML = svgIcon('volume-x');
      } else {
        volIcon.innerHTML = svgIcon('volume-2');
      }
    }

    volIcon.addEventListener('click', function () {
      if (audio.volume > 0) {
        audio.dataset.prevVol = audio.volume;
        audio.volume = 0;
        volSlider.value = 0;
      } else {
        audio.volume = parseFloat(audio.dataset.prevVol || '0.7');
        volSlider.value = audio.volume;
      }
      try { localStorage.setItem('mp_volume', audio.volume); } catch (e) {}
      updateVolIcon();
    });

    listToggle.addEventListener('click', function () {
      listVisible = !listVisible;
      listWrap.style.display = listVisible ? 'block' : 'none';
      listToggle.classList.toggle('mp-list-open', listVisible);
      listToggle.setAttribute('aria-expanded', listVisible ? 'true' : 'false');
    });

    listWrap.addEventListener('click', function (e) {
      var item = e.target.closest('.mp-list-item');
      if (!item) return;
      var idx = parseInt(item.getAttribute('data-index'), 10);
      if (isNaN(idx)) return;
      currentIndex = idx;
      loadSong(currentIndex);
      var promise = audio.play();
      if (promise && promise.catch) {
        promise.catch(function (err) {
          console.error('[音乐播放器] 播放失败:', err);
        });
      }
    });

    loadSong(currentIndex);
    updateVolIcon();
  });
})();
