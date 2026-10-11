<?php
// manual_advanced.php - Advanced User Manual with Interactive Features
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced User Manual - Internal Letters Registry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #f8f9fc;
            --text-color: #1e293b;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --toc-bg: #ffffff;
            --code-bg: #f1f5f9;
            --accent: #4318FF;
            --accent-hover: #7551FF;
            --header-bg: linear-gradient(135deg, #4318FF 0%, #7551FF 100%);
        }
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --text-color: #e2e8f0;
            --card-bg: #1e293b;
            --border-color: #334155;
            --toc-bg: #1e293b;
            --code-bg: #0f172a;
            --accent: #818cf8;
            --accent-hover: #a5b4fc;
            --header-bg: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        }
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            transition: background-color 0.3s, color 0.3s;
            scroll-behavior: smooth;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--border-color); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 10px; }
        .manual-wrapper {
            display: flex;
            gap: 2rem;
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        /* Sticky TOC */
        .toc-container {
            width: 280px;
            position: sticky;
            top: 2rem;
            height: calc(100vh - 4rem);
            overflow-y: auto;
            background: var(--toc-bg);
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 1.25rem;
            transition: all 0.3s;
        }
        .toc-container h5 {
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--accent);
            border-left: 3px solid var(--accent);
            padding-left: 0.75rem;
        }
        .toc-list {
            list-style: none;
            padding-left: 0;
        }
        .toc-list li {
            margin-bottom: 0.5rem;
        }
        .toc-list a {
            text-decoration: none;
            color: var(--text-color);
            font-size: 0.9rem;
            display: block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }
        .toc-list a:hover, .toc-list a.active {
            background-color: var(--accent);
            color: white;
        }
        /* Main content */
        .main-content {
            flex: 1;
            min-width: 0;
        }
        /* Card style */
        .manual-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            margin-bottom: 2rem;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .manual-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        .card-header {
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            cursor: pointer;
            user-select: none;
        }
        .card-header h2 {
            font-size: 1.5rem;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-header i {
            transition: transform 0.2s;
        }
        .card-body {
            padding: 1.5rem;
            transition: padding 0.2s;
        }
        .collapse:not(.show) + .card-body {
            display: none;
        }
        /* Code snippets */
        code {
            background: var(--code-bg);
            padding: 0.2rem 0.4rem;
            border-radius: 0.25rem;
            font-family: 'Fira Code', monospace;
            font-size: 0.85rem;
        }
        .copy-btn {
            float: right;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        .copy-btn:hover { opacity: 1; }
        /* Tables */
        .table {
            color: var(--text-color);
            border-color: var(--border-color);
        }
        .table th, .table td {
            border-color: var(--border-color);
        }
        /* Search bar */
        .search-container {
            position: relative;
            margin-bottom: 1rem;
        }
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            background: var(--card-bg);
            color: var(--text-color);
        }
        .search-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px var(--accent-hover);
        }
        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-color);
            opacity: 0.6;
        }
        .search-clear {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-color);
            opacity: 0.6;
        }
        .highlight {
            background-color: #fde047;
            color: #1e293b;
            border-radius: 0.2rem;
            padding: 0 0.1rem;
        }
        /* Theme toggle */
        .theme-toggle {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--accent);
            color: white;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000;
            transition: all 0.2s;
        }
        .theme-toggle:hover {
            transform: scale(1.05);
        }
        /* Print styles */
        @media print {
            .toc-container, .theme-toggle, .search-container, .card-header i, .copy-btn {
                display: none !important;
            }
            .manual-wrapper {
                display: block;
                padding: 0;
            }
            .manual-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ccc;
            }
            .card-body {
                display: block !important;
            }
            body {
                background: white;
                color: black;
            }
        }
        /* Responsive */
        @media (max-width: 768px) {
            .manual-wrapper {
                flex-direction: column;
            }
            .toc-container {
                width: 100%;
                position: relative;
                top: 0;
                height: auto;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
<div class="manual-wrapper">
    <!-- Table of Contents (Sticky) -->
    <div class="toc-container">
        <h5><i class="fas fa-list-ul me-2"></i>Contents</h5>
        <div class="search-container mb-3">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Search manual...">
            <i class="fas fa-times search-clear" id="clearSearch" style="display: none;"></i>
        </div>
        <ul class="toc-list" id="tocList">
            <li><a href="#overview">1. Overview</a></li>
            <li><a href="#access">2. Access & Permissions</a></li>
            <li><a href="#navigation">3. Sidebar Navigation</a></li>
            <li><a href="#filters">4. Filters & Search</a></li>
            <li><a href="#stats">5. Statistics Cards</a></li>
            <li><a href="#table">6. Table Columns</a></li>
            <li><a href="#actions">7. Row Actions</a></li>
            <li><a href="#preview">8. Quick Preview Modal</a></li>
            <li><a href="#new">9. New Letter Highlighting</a></li>
            <li><a href="#permissions">10. Permissions Reference</a></li>
            <li><a href="#tips">11. Usage Tips</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Card sections with collapsible functionality -->
        <div class="manual-card" id="overview">
            <div class="card-header" data-target="overviewBody">
                <h2>1. Overview <i class="fas fa-chevron-down"></i></h2>
            </div>
            <div class="card-body collapse show" id="overviewBody">
                <p>The <strong>Internal Letters Registry</strong> is the central dashboard for managing all internal correspondence within the organisation. It allows authorised personnel to view, search, filter, and act upon letters. Key features include a powerful search/filter system, a quick preview modal with an integrated PDF viewer and minute trail, and one‑click access to forwarding, editing, and final disposal.</p>
            </div>
        </div>

        <div class="manual-card" id="access">
            <div class="card-header" data-target="accessBody">
                <h2>2. Access & Permissions <i class="fas fa-chevron-down"></i></h2>
            </div>
            <div class="card-body collapse show" id="accessBody">
                <p>To access this page, your user account must have the permission <code>VIEW_INTERNAL_LETTERS</code>. Other permissions control what actions you can perform on each letter.</p>
                <p>Once logged in, navigate to <strong>Dispatching > Internal Letters > View Registry</strong> in the sidebar.</p>
            </div>
        </div>

        <div class="manual-card" id="navigation">
            <div class="card-header" data-target="navigationBody">
                <h2>3. Sidebar Navigation <i class="fas fa-chevron-down"></i></h2>
            </div>
            <div class="card-body collapse show" id="navigationBody">
                <ul class="icon-list">
                    <li><i class="fas fa-home"></i> <strong>Home Page</strong> – Returns to the main dashboard.</li>
                    <li><i class="fas fa-envelope-open-text"></i> <strong>Internal Letters</strong> (expandable)
                        <ul>
                            <li><i class="fas fa-plus-circle"></i> <strong>Add Internal</strong> – Create a new internal letter.</li>
                            <li><i class="fas fa-list-ul"></i> <strong>View Registry</strong> – Current page.</li>
                            <li><i class="fas fa-inbox"></i> <strong>My Inbox</strong> – Letters forwarded to you.</li>
                        </ul>
                    </li>
                    <li><i class="fas fa-sign-out-alt"></i> <strong>Logout</strong> – Ends your session.</li>
                </ul>
            </div>
        </div>

        <div class="manual-card" id="filters">
            <div class="card-header" data-target="filtersBody">
                <h2>4. Filters & Search <i class="fas fa-chevron-down"></i></h2>
            </div>
            <div class="card-body collapse show" id="filtersBody">
                <p>The filter bar provides powerful tools to quickly locate specific letters.</p>
                <ul>
                    <li><strong>Search Box</strong> – Searches across CDR number, reference, title, and other visible text. Type and click <strong>Apply Filters</strong> or press Enter.</li>
                    <li><strong>All Sections</strong> – Filter by the originating regiment or unit.</li>
                    <li><strong>All Priorities</strong> – Filter by priority: Normal, Urgent, Immediately.</li>
                    <li><strong>All Statuses</strong> – Filter by workflow status: Pending, On Desk, On Officer, Completed.</li>
                    <li><strong>Apply Filters</strong> – Execute the current filter settings.</li>
                    <li><strong>Clear</strong> – Reset all filter fields and show all letters.</li>
                </ul>
                <p>When any filter is active, a row of coloured badges appears showing your selections (e.g., “Section: 1 MIC”, “Priority: Urgent”).</p>
            </div>
        </div>

        <div class="manual-card" id="stats">
            <div class="card-header" data-target="statsBody">
                <h2>5. Statistics Cards <i class="fas fa-chevron-down"></i></h2>
            </div>
            <div class="card-body collapse show" id="statsBody">
                <p>Located above the table, these cards give a quick snapshot:</p>
                <ul>
                    <li><strong>URGENT</strong> – Number of non‑completed letters marked “Urgent”.</li>
                    <li><strong>IMMEDIATE</strong> – Number of non‑completed letters marked “Immediately”.</li>
                    <li><strong>TOTAL</strong> – Total letters in the registry (excluding deleted ones).</li>
                </ul>
            </div>
        </div>

        <div class="manual-card" id="table">
            <div class="card-header" data-target="tableBody">
                <h2>6. Table Columns Explained <i class="fas fa-chevron-down"></i></h2>
            </div>
            <div class="card-body collapse show" id="tableBody">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr><th>Column</th><th>Description</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>CDR & Reference</strong></td><td>Displays the unique CDR number (clickable to view detailed page) and the letter reference. A “NEW” badge appears for letters not yet viewed.</td></tr>
                            <tr><td><strong>Title / Date & Routing</strong></td><td>Shows the letter title, the date of the letter, and a badge indicating the current holder (e.g., “With: Cpl Perera”) if applicable.</td></tr>
                            <tr><td><strong>Unit / Source</strong></td><td>Displays the originating regiment, the letter category, and any associated informer code.</td></tr>
                            <tr><td><strong>Priority</strong></td><td>Colour‑coded tag: green (Normal), red (Urgent), maroon (Immediately).</td></tr>
                            <tr><td><strong>Process</strong></td><td>Current workflow status (Pending, On Desk, On Officer, Completed).</td></tr>
                            <tr><td><strong>Action</strong></td><td>Icons for available actions. Hover over each icon to see its purpose.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="manual-card" id="actions">
            <div class="card-header" data-target="actionsBody">
                <h2>7. Row Actions <i class="fas fa-chevron-down"></i></h2>
            </div>
            <div class="card-body collapse show" id="actionsBody">
                <p>Depending on your permissions, the following buttons appear in the Action column:</p>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr><th>Icon</th><th>Permission Required</th><th>Description</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><i class="fas fa-eye text-primary"></i></td><td><code>PREVIEW_INTERNAL_PDF</code></td><td>Opens the Quick Preview Modal with PDF viewer and minute trail.</td></tr>
                            <tr><td><i class="fas fa-pen-to-square text-warning"></i></td><td><code>EDIT_INTERNAL_LETTER</code></td><td>Go to edit page to modify letter details.</td></tr>
                            <tr><td><i class="fas fa-paper-plane text-info"></i></td><td><code>FORWARD_INTERNAL_LETTER</code></td><td>Forward the letter to another officer or desk.</td></tr>
                            <tr><td><i class="fas fa-check-double text-success"></i></td><td><code>FINAL_DISPOSAL</code></td><td>Move the letter to final disposal (filing / archiving).</td></tr>
                            <tr><td><i class="fas fa-trash-can text-danger"></i></td><td><code>DELETE_INTERNAL_LETTER</code></td><td>Delete the letter (confirmation required).</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="manual-card" id="preview">
            <div class="card-header" data-target="previewBody">
                <h2>8. Quick Preview Modal <i class="fas fa-chevron-down"></i></h2>
            </div>
            <div class="card-body collapse show" id="previewBody">
                <p>Clicking the eye icon opens a modal with two panes:</p>
                <ul>
                    <li><strong>Left Pane – PDF Viewer</strong>: Shows the attached document (if any). Toolbar includes “Open in new tab” and “Close”. If no attachment, a placeholder appears.</li>
                    <li><strong>Right Pane – Minute Trail</strong>: Displays a chronological list of all forwarding actions (minutes). Each entry includes sender, recipient, date/time, and comments.</li>
                    <li><strong>Footer Buttons</strong>: “Forward / Reply” and “Final Disposal” (if permissions allow) take you to the corresponding pages.</li>
                </ul>
                <p>Opening the modal automatically marks the letter as read, removing the “NEW” badge.</p>
            </div>
        </div>

        <div class="manual-card" id="new">
            <div class="card-header" data-target="newBody">
                <h2>9. New Letter Highlighting <i class="fas fa-chevron-down"></i></h2>
            </div>
            <div class="card-body collapse show" id="newBody">
                <p>Letters that have never been previewed are highlighted with a blue left border and a “NEW” badge. This information is stored in your browser’s local storage. Once you preview the letter (via the eye icon), it is considered read and the badge disappears.</p>
            </div>
        </div>

        <div class="manual-card" id="permissions">
            <div class="card-header" data-target="permissionsBody">
                <h2>10. Permissions Reference <i class="fas fa-chevron-down"></i></h2>
            </div>
            <div class="card-body collapse show" id="permissionsBody">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr><th>Permission Code</th><th>Feature</th><th style="width: 40px;"></th></tr>
                        </thead>
                        <tbody>
                            <tr><td><code>VIEW_INTERNAL_LETTERS</code></td><td>Access the page.</td><td><i class="fas fa-copy copy-perm" data-code="VIEW_INTERNAL_LETTERS" style="cursor: pointer;"></i></td></tr>
                            <tr><td><code>PREVIEW_INTERNAL_PDF</code></td><td>View the PDF and minute trail modal.</td><td><i class="fas fa-copy copy-perm" data-code="PREVIEW_INTERNAL_PDF" style="cursor: pointer;"></i></td></tr>
                            <tr><td><code>EDIT_INTERNAL_LETTER</code></td><td>Edit button.</td><td><i class="fas fa-copy copy-perm" data-code="EDIT_INTERNAL_LETTER" style="cursor: pointer;"></i></td></tr>
                            <tr><td><code>FORWARD_INTERNAL_LETTER</code></td><td>Forward button and the footer link in modal.</td><td><i class="fas fa-copy copy-perm" data-code="FORWARD_INTERNAL_LETTER" style="cursor: pointer;"></i></td></tr>
                            <tr><td><code>FINAL_DISPOSAL</code></td><td>Final disposal button and footer link.</td><td><i class="fas fa-copy copy-perm" data-code="FINAL_DISPOSAL" style="cursor: pointer;"></i></td></tr>
                            <tr><td><code>DELETE_INTERNAL_LETTER</code></td><td>Delete button.</td><td><i class="fas fa-copy copy-perm" data-code="DELETE_INTERNAL_LETTER" style="cursor: pointer;"></i></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="manual-card" id="tips">
            <div class="card-header" data-target="tipsBody">
                <h2>11. Usage Tips <i class="fas fa-chevron-down"></i></h2>
            </div>
            <div class="card-body collapse show" id="tipsBody">
                <ul>
                    <li><strong>Find quickly:</strong> Use the search box with CDR numbers or keywords, then combine with filters (e.g., “Urgent” + “On Officer”) to narrow down.</li>
                    <li><strong>Review without leaving:</strong> Use the eye icon to preview the PDF and minute trail. From there you can forward or finalise.</li>
                    <li><strong>Manage workflow:</strong> Forward letters to next officers, and use Final Disposal to archive completed ones.</li>
                    <li><strong>Prioritise:</strong> Keep an eye on the urgent/immediate counters to focus on high‑priority items.</li>
                    <li><strong>Reset filters:</strong> Use the Clear button to quickly return to the full list.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Theme Toggle Button -->
<div class="theme-toggle" id="themeToggle">
    <i class="fas fa-moon"></i>
</div>

<script>
    // Theme management
    const themeToggle = document.getElementById('themeToggle');
    const currentTheme = localStorage.getItem('theme') || 'light';
    if (currentTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
    } else {
        document.documentElement.setAttribute('data-theme', 'light');
        themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
    }
    themeToggle.addEventListener('click', () => {
        const newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        themeToggle.innerHTML = newTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
    });

    // Collapsible sections
    document.querySelectorAll('.card-header').forEach(header => {
        header.addEventListener('click', () => {
            const targetId = header.getAttribute('data-target');
            const body = document.getElementById(targetId);
            if (body.classList.contains('show')) {
                body.classList.remove('show');
                header.querySelector('i').style.transform = 'rotate(-90deg)';
            } else {
                body.classList.add('show');
                header.querySelector('i').style.transform = 'rotate(0deg)';
            }
        });
    });

    // Scrollspy: highlight TOC links based on visible section
    const sections = document.querySelectorAll('.manual-card');
    const tocLinks = document.querySelectorAll('.toc-list a');
    function updateActiveLink() {
        let current = '';
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionBottom = sectionTop + section.offsetHeight;
            const scrollPos = window.scrollY + 150;
            if (scrollPos >= sectionTop && scrollPos < sectionBottom) {
                current = section.getAttribute('id');
            }
        });
        tocLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${current}`) {
                link.classList.add('active');
            }
        });
    }
    window.addEventListener('scroll', updateActiveLink);
    window.addEventListener('load', updateActiveLink);

    // Search highlighting
    const searchInput = document.getElementById('searchInput');
    const clearSearch = document.getElementById('clearSearch');
    let currentSearchTerm = '';

    function highlightSearch(term) {
        // Remove existing highlights
        document.querySelectorAll('.highlight').forEach(el => {
            const parent = el.parentNode;
            parent.replaceChild(document.createTextNode(el.textContent), el);
            parent.normalize();
        });
        if (!term) return;

        const regex = new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        const walker = document.createTreeWalker(
            document.getElementById('mainContent'),
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    // Skip script, style, and already highlighted nodes
                    if (node.parentElement.tagName === 'SCRIPT' ||
                        node.parentElement.tagName === 'STYLE' ||
                        node.parentElement.classList.contains('highlight')) {
                        return NodeFilter.FILTER_REJECT;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            }
        );
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(node => {
            const text = node.textContent;
            if (regex.test(text)) {
                const span = document.createElement('span');
                span.innerHTML = text.replace(regex, match => `<span class="highlight">${match}</span>`);
                node.parentNode.replaceChild(span, node);
            }
        });
    }

    searchInput.addEventListener('input', (e) => {
        const term = e.target.value.trim();
        currentSearchTerm = term;
        highlightSearch(term);
        clearSearch.style.display = term ? 'block' : 'none';
    });

    clearSearch.addEventListener('click', () => {
        searchInput.value = '';
        highlightSearch('');
        clearSearch.style.display = 'none';
    });

    // Copy permission codes
    document.querySelectorAll('.copy-perm').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const code = btn.getAttribute('data-code');
            navigator.clipboard.writeText(code).then(() => {
                const originalIcon = btn.className;
                btn.className = 'fas fa-check';
                setTimeout(() => { btn.className = originalIcon; }, 1500);
            });
        });
    });

    // Smooth scroll for TOC links
    tocLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.getAttribute('href').substring(1);
            const target = document.getElementById(targetId);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
                // Expand the section if collapsed
                const body = target.querySelector('.card-body');
                if (body && !body.classList.contains('show')) {
                    const header = target.querySelector('.card-header');
                    header.click();
                }
            }
        });
    });
</script>
</body>
</html>