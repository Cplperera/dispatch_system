/* ═══════════════════════════════════════════════════════════
   IDCMS USER MANUAL — Shared Scripts
   ═══════════════════════════════════════════════════════════ */

// Theme (shared with dashboard)
(function () {
    const saved = localStorage.getItem('dispatch_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', saved);
})();

function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('theme-icon');
    if (!icon) return;
    const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    html.setAttribute('data-theme', next);
    localStorage.setItem('dispatch_theme', next);
    icon.classList.toggle('fa-moon', next === 'dark');
    icon.classList.toggle('fa-sun', next === 'light');
}

function printManual() { window.print(); }

// Reading progress bar
function initReadingProgress() {
    const bar = document.getElementById('readingProgress');
    if (!bar) return;
    window.addEventListener('scroll', () => {
        const h = document.documentElement;
        const scrolled = (h.scrollTop / (h.scrollHeight - h.clientHeight)) * 100;
        bar.style.width = Math.min(scrolled, 100) + '%';
    }, { passive: true });
}

// Mobile sidebar
function initMobileToggle() {
    const btn = document.getElementById('mobileToggle');
    const sidebar = document.querySelector('.m-sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!btn || !sidebar) return;

    btn.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('show');
        btn.innerHTML = sidebar.classList.contains('open')
            ? '<i class="fa-solid fa-xmark"></i>'
            : '<i class="fa-solid fa-bars"></i>';
    });

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            btn.innerHTML = '<i class="fa-solid fa-bars"></i>';
        });
    }
}

// Copy code blocks
function initCopyButtons() {
    document.querySelectorAll('.m-code').forEach(block => {
        if (block.querySelector('.copy-btn')) return;
        const btn = document.createElement('button');
        btn.className = 'copy-btn';
        btn.type = 'button';
        btn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy';
        block.appendChild(btn);
        btn.addEventListener('click', () => {
            const text = block.innerText.replace(/^Copy\s*/i, '').trim();
            navigator.clipboard.writeText(text).then(() => {
                btn.classList.add('copied');
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy';
                }, 1800);
            });
        });
    });
}

// Mark as read
function initMarkRead() {
    const btn = document.getElementById('markRead');
    if (!btn) return;
    const key = 'manual_read_' + (btn.dataset.page || location.pathname);

    if (localStorage.getItem(key) === '1') {
        btn.classList.add('done');
        btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Marked as Read';
    }

    btn.addEventListener('click', () => {
        const isRead = localStorage.getItem(key) === '1';
        if (isRead) {
            localStorage.removeItem(key);
            btn.classList.remove('done');
            btn.innerHTML = '<i class="fa-regular fa-circle-check"></i> Mark as Read';
        } else {
            localStorage.setItem(key, '1');
            btn.classList.add('done');
            btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Marked as Read';
        }
    });
}

// Sidebar search
function initSidebarSearch() {
    const input = document.getElementById('sidebarSearch');
    if (!input) return;
    input.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.m-nav a').forEach(link => {
            const text = link.textContent.toLowerCase();
            link.style.display = (!q || text.includes(q)) ? '' : 'none';
        });
    });
}

// Keyboard shortcuts
function initKeyboardShortcuts() {
    document.addEventListener('keydown', e => {
        // Ignore if typing in an input
        if (['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return;

        // '/' focuses sidebar search
        if (e.key === '/') {
            e.preventDefault();
            const s = document.getElementById('sidebarSearch');
            if (s) s.focus();
        }
        // 't' toggles theme
        if (e.key === 't' || e.key === 'T') toggleTheme();
        // 'p' prints
        if (e.key === 'p' || e.key === 'P') { e.preventDefault(); printManual(); }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const icon = document.getElementById('theme-icon');
    if (icon) {
        const saved = localStorage.getItem('dispatch_theme') || 'dark';
        icon.classList.toggle('fa-moon', saved === 'dark');
        icon.classList.toggle('fa-sun', saved === 'light');
    }
    initReadingProgress();
    initMobileToggle();
    initCopyButtons();
    initMarkRead();
    initSidebarSearch();
    initKeyboardShortcuts();
});