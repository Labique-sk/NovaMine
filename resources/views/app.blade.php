<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#050506">
<meta name="color-scheme" content="dark">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<title>NovaMine — Candidate Intelligence</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body data-authenticated="{{ auth()->check() ? '1' : '0' }}">


<!-- Icon sprite: monochrome SF-Symbols-style strokes, avoids emoji-font inconsistencies on Android -->
<svg style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">
<symbol id="i-mail" viewBox="0 0 24 24"><path d="M3 6.5A2.5 2.5 0 0 1 5.5 4h13A2.5 2.5 0 0 1 21 6.5v11A2.5 2.5 0 0 1 18.5 20h-13A2.5 2.5 0 0 1 3 17.5v-11Z"/><path d="m4 7 8 6 8-6"/></symbol>
<symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="2.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></symbol>
<symbol id="i-eye" viewBox="0 0 24 24"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/></symbol>
<symbol id="i-eye-off" viewBox="0 0 24 24"><path d="M3 3l18 18"/><path d="M10.6 5.7A10.8 10.8 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a15.6 15.6 0 0 1-3.14 3.9M6.5 6.9C4 8.6 2.5 12 2.5 12S6 18.5 12 18.5c1.2 0 2.27-.24 3.22-.63M9.9 9.9a3 3 0 0 0 4.2 4.2"/></symbol>
<symbol id="i-check" viewBox="0 0 24 24"><path d="M4 12.5 9.5 18 20 6.5"/></symbol>
<symbol id="i-google" viewBox="0 0 24 24"><path fill="currentColor" stroke="none" d="M21.35 11.1H12v2.9h5.35c-.25 1.5-1.7 4.4-5.35 4.4-3.2 0-5.85-2.65-5.85-5.9S8.8 6.6 12 6.6c1.85 0 3.1.8 3.8 1.45l2.6-2.5C16.85 4 14.6 3 12 3 6.9 3 2.8 7.05 2.8 12.1S6.9 21.2 12 21.2c5.35 0 9.35-3.75 9.35-9.3 0-.63-.07-1.1-.15-1.55z"/></symbol>
<symbol id="i-apple" viewBox="0 0 24 24"><path fill="currentColor" stroke="none" d="M16.4 12.3c0-2.15 1.75-3.2 1.83-3.25-1-1.45-2.55-1.65-3.1-1.68-1.32-.13-2.58.78-3.25.78-.68 0-1.7-.76-2.8-.74-1.44.02-2.77.84-3.5 2.13-1.5 2.6-.38 6.45 1.08 8.56.71 1.03 1.56 2.19 2.68 2.15 1.08-.04 1.48-.7 2.78-.7 1.3 0 1.66.7 2.79.68 1.15-.02 1.88-1.05 2.58-2.08.82-1.2 1.15-2.36 1.17-2.42-.03-.01-2.24-.86-2.26-3.43ZM14.6 5.85c.6-.72 1-1.72.89-2.72-.86.04-1.9.58-2.52 1.3-.55.63-1.04 1.65-.91 2.62.95.08 1.93-.48 2.54-1.2Z"/></symbol>
<symbol id="i-home" viewBox="0 0 24 24"><path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9h12v-9"/></symbol>
<symbol id="i-spark" viewBox="0 0 24 24"><path d="M12 3v5M12 16v5M3 12h5M16 12h5M6 6l3 3M18 18l-3-3M18 6l-3 3M6 18l3-3"/></symbol>
<symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20V10M11 20V4M18 20v-7"/></symbol>
<symbol id="i-heart" viewBox="0 0 24 24"><path d="M12 20s-7.5-4.6-9.7-9C.6 7.4 2.3 4 5.8 4c2 0 3.4 1.1 4.2 2.3C10.8 5.1 12.2 4 14.2 4c3.5 0 5.2 3.4 3.5 7-2.2 4.4-9.7 9-9.7 9Z"/></symbol>
<symbol id="i-download" viewBox="0 0 24 24"><path d="M12 3v13M7 12l5 5 5-5"/><path d="M4 20h16"/></symbol>
<symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
<symbol id="i-refresh" viewBox="0 0 24 24"><path d="M20 11A8 8 0 1 0 18.5 16"/><path d="M20 5v6h-6"/></symbol>
<symbol id="i-glow" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 3v2M12 19v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M3 12h2M19 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></symbol>
<symbol id="i-close" viewBox="0 0 24 24"><path d="M5 5l14 14M19 5 5 19"/></symbol>
</svg>

<div class="auth" id="auth">
  <div class="authwrap">
    <div class="authbrand glass">
      <a class="brand" href="#"><span class="logo"></span><span>NovaMine</span></a>
      <h1>Candidate intelligence,<br><span class="gradient">without the noise.</span></h1>
      <p>One workspace to mine, validate, score and export candidate data — built for teams who move fast.</p>
      <ul class="authfeatures">
        <li><span class="check"><svg class="icon"><use href="#i-check"/></svg></span>Live mining pipeline with real-time scan status</li>
        <li><span class="check"><svg class="icon"><use href="#i-check"/></svg></span>Automatic verification and duplicate detection</li>
        <li><span class="check"><svg class="icon"><use href="#i-check"/></svg></span>One-click export to CSV, JSON or Excel</li>
      </ul>
      <div class="authstats">
        <div class="authstat"><strong>2,480+</strong><span>Candidates mined</span></div>
        <div class="authstat"><strong id="aValidation">0%</strong><span>Validation rate</span></div>
        <div class="authstat"><strong id="aProcessing">—</strong><span>Avg. processing</span></div>
      </div>
    </div>
    <div class="authbox glass">
      <div class="authbox-head"><h2>Welcome back</h2><p>Sign in to your demo workspace.</p></div>
      <form id="loginForm" novalidate>
        <div class="field">
          <label for="email">Email</label>
          <div class="inputwrap">
            <svg class="icon"><use href="#i-mail"/></svg>
            <input class="input" id="email" type="email" value="demo@novamine.ai" autocomplete="email" inputmode="email">
          </div>
          <span class="fielderr" id="emailErr"></span>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <div class="inputwrap">
            <svg class="icon"><use href="#i-lock"/></svg>
            <input class="input haspw" id="password" type="password" value="demo1234" autocomplete="current-password">
            <button type="button" class="pwtoggle" id="pwToggle" aria-label="Show password"><svg class="icon"><use href="#i-eye"/></svg></button>
          </div>
          <span class="fielderr" id="passErr"></span>
        </div>
        <div class="authrow">
          <label class="remember"><input type="checkbox" id="remember"><span class="checkbox"><svg class="icon"><use href="#i-check"/></svg></span>Remember me</label>
          <a href="#" class="forgot" id="forgotLink">Forgot password?</a>
        </div>
        <button class="primary" id="login" type="submit">Enter workspace <span class="arrow">→</span></button>
      </form>
      <button class="demofill" id="demoFill" type="button"><span class="live-dot"></span>Use demo account — demo@novamine.ai</button>
      <div class="divider"><span>or continue with</span></div>
      <div class="socialrow">
        <button class="ghost social" id="googleBtn" type="button"><svg class="icon"><use href="#i-google"/></svg>Google</button>
        <button class="ghost social" id="appleBtn" type="button"><svg class="icon"><use href="#i-apple"/></svg>Apple</button>
      </div>
      
      <div class="auth-toggle">Need an account? <button type="button" id="showRegister">Create one</button></div>
      <form id="registerForm" class="hidden" novalidate>
        <div class="field"><label for="regName">Name</label><input class="input" id="regName" autocomplete="name"></div>
        <div class="field"><label for="regEmail">Email</label><input class="input" id="regEmail" type="email" autocomplete="email"></div>
        <div class="field"><label for="regPassword">Password</label><input class="input" id="regPassword" type="password" autocomplete="new-password"></div>
        <div class="field"><label for="regPasswordConfirm">Confirm password</label><input class="input" id="regPasswordConfirm" type="password" autocomplete="new-password"></div>
        <button class="primary" id="registerBtn" type="submit">Create account</button>
        <div class="auth-toggle">Already registered? <button type="button" id="showLogin">Sign in</button></div>
      </form>
<div class="demo">Demo mode · <b>demo@novamine.ai / demo1234</b> (seeded in MySQL)</div>
    </div>
  </div>
</div>

<header class="topbar">
  <div style="display:flex;align-items:center">
    <a class="brand" href="#"><span class="logo"></span><span>NovaMine</span></a>
    <nav class="nav" id="nav">
      <span class="navthumb" aria-hidden="true"></span>
      <button class="active" data-page="dashboard">Overview</button><button data-page="mining">Mine</button><button data-page="analytics">Analytics</button><button data-page="saved">Shortlists</button><button data-page="exports">Exports</button>
    </nav>
  </div>
  <div class="actions">
    <button class="iconbtn" id="refresh" title="Refresh" aria-label="Refresh workspace"><svg class="icon"><use href="#i-refresh"/></svg></button>
    <button class="iconbtn" id="theme" title="Reduce visual effects" aria-label="Toggle visual effects"><svg class="icon"><use href="#i-glow"/></svg></button>
    <button class="avatar" id="openSettings" title="Account settings">JD</button>
  </div>
</header>

<main class="shell">
<section id="dashboard" class="page">
  <div class="hero"><div><div class="eyebrow">AI candidate intelligence · live workspace</div><h1>Find signal in your <span class="gradient">candidate universe.</span></h1><p>Configure a search, run a mining job, validate records, score candidates and export a clean dataset — all from one fast workspace.</p></div><button class="primary" type="button" data-page="mining">Start new mining <svg class="icon" style="width:14px;height:14px;display:inline-block;vertical-align:-2px"><use href="#i-spark"/></svg></button></div>
  <div class="stats">
    <div class="stat glass"><small>Total candidates</small><strong id="sCandidates">—</strong><span class="trend">▲ 12.4% this month</span></div>
    <div class="stat glass"><small>Verified profiles</small><strong id="sVerified">—</strong><span class="trend">▲ 8.7% validation rate</span></div>
    <div class="stat glass"><small>Mining jobs</small><strong id="sJobs">—</strong><span class="trend">▲ 5 completed today</span></div>
    <div class="stat glass"><small>Avg. match score</small><strong id="sScore">—</strong><span class="trend">▲ 2.1% relevance</span></div>
  </div>
  <div class="charts">
    <div class="card glass"><div class="cardhead"><div><div class="cardtitle">Candidate acquisition</div><div class="cardsub">Records discovered across the last 14 days</div></div><span class="pill verified">LIVE</span></div><div class="chartbox"><canvas id="lineChart"></canvas></div></div>
    <div class="card glass"><div class="cardhead"><div><div class="cardtitle">Sources</div><div class="cardsub">Where records came from</div></div></div><div class="chartbox"><canvas id="sourceChart"></canvas></div></div>
    <div class="card glass"><div class="cardhead"><div><div class="cardtitle">Top skills</div><div class="cardsub">Most frequent candidate skills</div></div></div><div class="chartbox"><canvas id="skillChart"></canvas></div></div>
  </div>
  <div class="sectionhead"><h2>Recent candidates</h2><span class="muted" style="font-size:11px">Tap a row for intelligence profile</span></div>
  <div class="tablecard glass"><div class="tablewrap"><table class="table"><thead><tr><th>Candidate</th><th>Role</th><th>Location</th><th>Experience</th><th>Match</th><th>Status</th></tr></thead><tbody id="recentBody"></tbody></table></div></div>
</section>

<section id="mining" class="page hidden">
  <div class="hero"><div><div class="eyebrow">Mining studio</div><h1>Build a <span class="gradient">high-signal search.</span></h1><p>Use structured filters to generate a realistic candidate dataset. Start a backend mining job. Permitted public adapters collect, validate, deduplicate and score records.</p></div></div>
  <div class="mining">
    <aside class="config glass">
      <h2>Search configuration</h2><p>Fine-tune the profile you want to discover.</p>
      <div class="field"><label>Job role</label><input class="input" id="role" value="AI Engineer"></div>
      <div class="field"><label>Location</label><select class="select" id="location"><option>Pune, India</option><option>Mumbai, India</option><option>Bengaluru, India</option><option>Hyderabad, India</option><option>Delhi NCR, India</option></select></div>
      <div class="field"><label>Experience</label><select class="select" id="experience"><option>0–2 years</option><option>2–5 years</option><option>5–8 years</option><option>8+ years</option></select></div>
      <div class="field"><label>Candidate type</label><div class="checkrow" id="types"><button class="chip on" type="button">Professional</button><button class="chip" type="button">Student</button><button class="chip" type="button">Intern</button></div></div>
      <div class="field"><label>Industry</label><input class="input" id="industry" value="Technology"></div>
      <div class="field"><label>Skills</label><input class="input" id="skills" value="Python, SQL, Machine Learning"></div>
      <div class="field"><label>Minimum match score</label><input id="minScore" type="range" min="60" max="98" value="78" style="width:100%"><div style="display:flex;justify-content:space-between;color:var(--muted);font-size:10px"><span>60%</span><b id="scoreLabel">78%</b><span>98%</span></div></div>
      <div class="mining-cta">
        <button class="primary" id="mine" type="button" aria-label="Start AI candidate mining">
          <span class="mine-icon"><svg class="icon" style="width:16px;height:16px"><use href="#i-spark"/></svg></span>
          <span>Start AI Mining</span>
          <span class="mine-arrow">→</span>
        </button>
        <div class="mining-cta-caption"><span class="live-dot"></span><span>Ready to scan candidate signals</span></div>
      </div>
    </aside>
    <div class="results">
      <div class="toolbar glass"><div class="search"><svg class="icon"><use href="#i-search"/></svg><input class="input" id="search" placeholder="Search name, role, company, skill, location…"></div><button class="ghost" id="filter" type="button">Filters</button><button class="ghost" id="allCandidatesBtn" type="button">All Candidates</button><button class="ghost" id="importBtn" type="button">Import CSV</button><button class="ghost" id="exportBtn" type="button">Export</button></div>
      <div class="tablecard glass"><div class="tablewrap"><table class="table"><thead><tr><th data-sort="name">Candidate ↕</th><th data-sort="role">Role ↕</th><th data-sort="company">Company ↕</th><th data-sort="location">Location ↕</th><th data-sort="experience">Exp ↕</th><th data-sort="score">Match ↕</th><th>Status</th></tr></thead><tbody id="candidateBody"></tbody></table></div><div class="pagination"><span id="pageInfo">0 candidates</span><div class="pages" id="pages"></div></div></div>
    </div>
  </div>
</section>

<section id="analytics" class="page hidden">
  <div class="hero"><div><div class="eyebrow">Analytics</div><h1>Understand your <span class="gradient">pipeline.</span></h1><p>Measure mining volume, validation quality, source mix and candidate relevance.</p></div></div>
  <div class="stats"><div class="stat glass"><small>Validation rate</small><strong id="aValidation">0%</strong><span class="trend">▲ 3.2%</span></div><div class="stat glass"><small>Duplicate rate</small><strong id="aDuplicates">0%</strong><span class="trend">▼ 1.4%</span></div><div class="stat glass"><small>Avg. processing</small><strong id="aProcessing">—</strong><span class="trend">▼ 12%</span></div><div class="stat glass"><small>Exports</small><strong id="aExports">0</strong><span class="trend">▲ 18 this week</span></div></div>
  <div class="charts"><div class="card glass"><div class="cardtitle">Validation quality</div><div class="chartbox"><canvas id="validationChart"></canvas></div></div><div class="card glass"><div class="cardtitle">Location distribution</div><div class="chartbox"><canvas id="locationChart"></canvas></div></div><div class="card glass"><div class="cardtitle">Mining volume</div><div class="chartbox"><canvas id="volumeChart"></canvas></div></div></div>
</section>

<section id="saved" class="page hidden">
  <div class="hero"><div><div class="eyebrow">Shortlists</div><h1>Your <span class="gradient">high-signal people.</span></h1><p>Keep strong candidates close and move them through your workflow.</p></div></div>
  <div class="card glass" style="margin-bottom:14px"><div class="sectionhead" style="margin-top:0"><h2>Shortlists</h2></div>
  <div class="field"><label>New shortlist</label><input class="input" id="shortlistName" placeholder="Senior Python Engineers"></div>
  <div class="btnrow"><button class="primary" id="createShortlist" type="button">Create shortlist</button></div>
  <div id="shortlistNav" class="taglist" style="margin-top:12px"></div>
</div>
<div class="card glass"><div class="sectionhead" style="margin-top:0"><h2>Saved candidates</h2><span class="muted" id="savedCount">0 saved</span></div><div class="tablewrap"><table class="table"><thead><tr><th>Candidate</th><th>Role</th><th>Company</th><th>Match</th><th>Action</th></tr></thead><tbody id="savedBody"></tbody></table></div></div>
</section>

<section id="exports" class="page hidden">
  <div class="hero"><div><div class="eyebrow">Export center</div><h1>Take your <span class="gradient">dataset anywhere.</span></h1><p>Export the current filtered dataset as CSV, JSON or Excel.</p></div></div>
  <div class="stats"><div class="stat glass"><small>Ready to export</small><strong id="exportCount">0</strong><span class="trend">Current filtered view</span></div><div class="stat glass"><small>Formats</small><strong>3</strong><span class="trend">CSV · JSON · XLSX</span></div></div>
  <div class="card glass"><h2 style="font-size:16px;margin-top:0">Choose format</h2><p class="muted" style="font-size:12px">Exports are generated on the server and stored securely.</p><div style="display:flex;gap:9px;flex-wrap:wrap;margin-top:16px"><button class="primary" data-export="csv" type="button">Export CSV</button><button class="ghost" data-export="xlsx" type="button">Export Excel</button><button class="ghost" data-export="json" type="button">Download JSON</button></div></div>
</section>

<section id="settings" class="page hidden">
  <div class="hero"><div><div class="eyebrow">Account</div><h1>Profile &amp; <span class="gradient">settings.</span></h1><p>Update your account, review search and export history, and manage Google Sheets.</p></div></div>
  <div class="settingsgrid">
    <div class="card glass">
      <h2 style="font-size:16px;margin-top:0">Account information</h2>
      <div class="field"><label>Name</label><input class="input" id="settingsName"></div>
      <div class="field"><label>Email</label><input class="input" id="settingsEmail" type="email"></div>
      <div class="field"><label>Current password</label><input class="input" id="settingsCurrentPassword" type="password"></div>
      <div class="field"><label>New password</label><input class="input" id="settingsPassword" type="password"></div>
      <div class="field"><label>Confirm password</label><input class="input" id="settingsPasswordConfirm" type="password"></div>
      <div class="btnrow"><button class="primary" id="saveSettings" type="button">Save changes</button><button class="ghost" id="logoutSettings" type="button">Sign out</button></div>
    </div>
    <div class="card glass">
      <h2 style="font-size:16px;margin-top:0">Google Sheets</h2>
      <p class="google-status" id="googleStatus">Checking configuration…</p>
      <div class="btnrow"><button class="primary" id="googleConnect" type="button">Connect Google</button><button class="ghost" id="googleExport" type="button">Export to Sheets</button></div>
      <div class="sectionhead"><h2>Search history</h2></div>
      <div id="searchHistory"></div>
      <div class="sectionhead"><h2>Export history</h2></div>
      <div id="exportHistory"></div>
    </div>
  </div>
</section>

<div id="importModal" class="modal hidden">
  <div class="modal-content glass">
    <div class="modal-header">
      <h2>Import Candidates from CSV</h2>
      <button class="close-modal" id="closeImportModal" type="button">✕</button>
    </div>
    <div class="modal-body">
      <p class="muted" style="margin-bottom:16px">Upload a CSV file to import candidates. Maximum file size: 10MB.</p>
      
      <div class="field">
        <label>CSV File</label>
        <input class="input" id="csvFile" type="file" accept=".csv,text/plain">
        <small class="muted">Accepted formats: .csv only</small>
      </div>
      
      <div class="import-actions" style="margin-top:16px">
        <button class="ghost" id="downloadTemplate" type="button">Download Template</button>
        <button class="primary" id="importSubmit" type="button">Import CSV</button>
      </div>
      
      <div id="importResults" class="import-results hidden" style="margin-top:16px">
        <div class="import-stats" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
          <div class="stat small glass"><small>Total Rows</small><strong id="importTotal">0</strong></div>
          <div class="stat small glass"><small>Imported</small><strong id="importImported">0</strong></div>
          <div class="stat small glass"><small>Duplicates</small><strong id="importDuplicates">0</strong></div>
          <div class="stat small glass"><small>Invalid</small><strong id="importInvalid">0</strong></div>
        </div>
        <div id="importErrors" class="import-errors" style="max-height:200px;overflow-y:auto"></div>
      </div>
    </div>
  </div>
</div>

</main>

<footer class="sitefooter">
  <div class="footerinner glass">
    <div class="footerleft">
      <span class="logo"></span>
      <span class="footerbrand">NovaMine</span>
      <span class="footercopy">© 2026 NovaMine. All rights reserved.</span>
    </div>
    <div class="footerlinks">
      <button type="button" class="footerlink" id="openPrivacy">Privacy Policy</button>
      <span class="footerdot">·</span>
      <button type="button" class="footerlink" id="openTerms">Terms &amp; Conditions</button>
    </div>
  </div>
  <p class="footernote">NovaMine collects only publicly available professional information that is legally permissible to access. Synthetic demo records are labelled [DEMO] and are not real people.</p>
</footer>

<nav class="bottomnav" id="bottomnav">
  <span class="navthumb" aria-hidden="true"></span>
  <button class="active" data-page="dashboard"><svg class="icon"><use href="#i-home"/></svg>Home</button>
  <button data-page="mining"><svg class="icon"><use href="#i-spark"/></svg>Mine</button>
  <button data-page="analytics"><svg class="icon"><use href="#i-chart"/></svg>Analytics</button>
  <button data-page="saved"><svg class="icon"><use href="#i-heart"/></svg>Saved</button>
  <button data-page="exports"><svg class="icon"><use href="#i-download"/></svg>Export</button>
</nav>

<div class="drawer" id="drawer"><aside class="drawerpanel"><div class="drawerhead"><div class="eyebrow">Candidate intelligence</div><button class="iconbtn" id="closeDrawer" aria-label="Close"><svg class="icon"><use href="#i-close"/></svg></button></div><div id="profile"></div></aside></div>

<div class="overlay" id="mineOverlay"><div class="modal glass"><div class="eyebrow">Live mining pipeline</div><h2 id="mineTitle" style="margin:8px 0 5px">Discovering candidate profiles</h2><p class="muted" style="font-size:12px" id="mineSub">Initializing search agents…</p><div class="progress"><i id="progress"></i></div><div style="display:flex;justify-content:space-between;font-size:11px;color:var(--muted)"><span id="pct">0%</span><span id="eta">~18 sec</span></div><div class="scan" id="scan"></div><div class="btnrow"><button class="ghost" id="cancelMine" type="button">Cancel job</button></div></div></div>
<div class="overlay" id="privacyOverlay">
  <div class="modal legalmodal glass">
    <div class="legalhead">
      <div><div class="eyebrow">Legal</div><h2>Privacy Policy</h2><p class="muted" style="font-size:11.5px;margin:4px 0 0">Last updated: August 2026</p></div>
      <button class="iconbtn" id="closePrivacy" aria-label="Close"><svg class="icon"><use href="#i-close"/></svg></button>
    </div>
    <div class="legalbody">
      <p class="legalnote">Placeholder policy text prepared for this college mini-project prototype, for demonstration purposes only — it is not legal advice and has not been reviewed by counsel.</p>
      <h3>1. Overview</h3>
      <p>NovaMine is a candidate intelligence workspace that helps recruiters discover, validate and organize professional information that candidates have already made publicly available online. This policy explains what the platform collects and how it is used.</p>
      <h3>2. Information We Collect</h3>
      <p>When a search is run, NovaMine gathers only publicly listed professional details, such as name, current designation, years of experience, key skills, educational qualifications, location, portfolio or professional networking profile links, and any professional email address or phone number a candidate has chosen to make public.</p>
      <h3>3. Sources of Information</h3>
      <p>Records are drawn exclusively from sources a candidate has made publicly accessible. NovaMine does not access private accounts, paywalled data, or information behind a login the candidate controls.</p>
      <h3>4. How We Use Information</h3>
      <ul>
        <li>To power search, filtering and match scoring against a recruiter's criteria</li>
        <li>To validate collected records — checking profile link accessibility and email/phone format</li>
        <li>To detect and remove duplicate candidate entries</li>
        <li>To populate dashboards, shortlists and exported datasets a user generates</li>
      </ul>
      <h3>5. Data Storage &amp; Security</h3>
      <p>Verified records are stored in a centralized, access-controlled database. Only authenticated users of a workspace can view or export its data, and reasonable technical safeguards are used to protect stored information from unauthorized access.</p>
      <h3>6. Data Retention</h3>
      <p>Candidate records are retained only for as long as they support an active search or shortlist. Users can remove records from their workspace at any time.</p>
      <h3>7. Candidate Rights</h3>
      <p>If you are a candidate who appears in results and would like a record corrected or removed, contact us using the details below and we will act on that request.</p>
      <h3>8. Cookies &amp; Local Storage</h3>
      <p>NovaMine may keep session information in your browser so you stay signed in between visits. This demo does not use advertising or cross-site tracking cookies.</p>
      <h3>9. Third-Party Services</h3>
      <p>Exports can optionally be synced to Google Sheets. This only happens when a user explicitly triggers an export, and only the filtered dataset shown at that time is shared.</p>
      <h3>10. Changes to This Policy</h3>
      <p>This policy may be updated as the prototype evolves. Continued use of the workspace after a change constitutes acceptance of the revised policy.</p>
      <h3>11. Contact</h3>
      <p>Questions about this policy can be directed to the project team through the university's School of Commerce, Management and Computer Studies.</p>
    </div>
  </div>
</div>

<div class="overlay" id="termsOverlay">
  <div class="modal legalmodal glass">
    <div class="legalhead">
      <div><div class="eyebrow">Legal</div><h2>Terms &amp; Conditions</h2><p class="muted" style="font-size:11.5px;margin:4px 0 0">Last updated: August 2026</p></div>
      <button class="iconbtn" id="closeTerms" aria-label="Close"><svg class="icon"><use href="#i-close"/></svg></button>
    </div>
    <div class="legalbody">
      <p class="legalnote">Placeholder terms prepared for this college mini-project prototype, for demonstration purposes only — it is not legal advice and has not been reviewed by counsel.</p>
      <h3>1. Acceptance of Terms</h3>
      <p>By signing in and using NovaMine, you agree to these Terms &amp; Conditions. If you do not agree, please do not use the workspace.</p>
      <h3>2. Description of Service</h3>
      <p>NovaMine automates the collection, validation and organization of publicly available candidate information for recruitment, talent sourcing and workforce-planning purposes.</p>
      <h3>3. Eligibility &amp; Accounts</h3>
      <p>Access is limited to registered users. You are responsible for keeping your login credentials confidential and for activity carried out under your account.</p>
      <h3>4. Acceptable Use</h3>
      <ul>
        <li>Use the platform only for legitimate recruitment and talent-sourcing purposes</li>
        <li>Respect the terms of service of any source platform and applicable data-protection laws</li>
        <li>Do not use collected information to harass, discriminate against, or otherwise misuse any candidate's public data</li>
      </ul>
      <h3>5. Data Accuracy Disclaimer</h3>
      <p>Candidate records reflect publicly listed information at the time it was gathered. Because source pages change and some candidates may not publish complete details, NovaMine cannot guarantee that every record is complete, current or fully accurate.</p>
      <h3>6. Intellectual Property</h3>
      <p>The platform's design, code and branding belong to the project team. Datasets you export remain your responsibility to handle in line with applicable privacy laws.</p>
      <h3>7. Limitation of Liability</h3>
      <p>NovaMine is provided as a prototype "as is," without warranties of any kind. The project team is not liable for decisions made using data sourced through the platform.</p>
      <h3>8. Termination of Access</h3>
      <p>Access may be suspended or terminated for use that violates these terms or applicable law.</p>
      <h3>9. Changes to These Terms</h3>
      <p>These terms may be updated as the prototype evolves. Continued use after a change constitutes acceptance of the revised terms.</p>
      <h3>10. Governing Law</h3>
      <p>These terms are governed by the applicable laws of the jurisdiction in which the platform is operated.</p>
      <h3>11. Contact</h3>
      <p>Questions about these terms can be directed to the project team through the university's School of Commerce, Management and Computer Studies.</p>
    </div>
  </div>
</div>


<div class="overlay" id="forgotOverlay">
  <div class="modal glass">
    <div class="eyebrow">Account recovery</div>
    <h2 style="margin:8px 0 5px">Reset password</h2>
    <p class="muted" style="font-size:12px">Enter your email and we will send a reset link if the account exists.</p>
    <div class="field"><label>Email</label><input class="input" id="forgotEmail" type="email"></div>
    <div class="btnrow"><button class="primary" id="forgotSubmit" type="button">Send reset link</button><button class="ghost" id="closeForgot" type="button">Cancel</button></div>
  </div>
</div>
<div class="overlay" id="resetOverlay">
  <div class="modal glass">
    <div class="eyebrow">Account recovery</div>
    <h2 style="margin:8px 0 5px">Choose a new password</h2>
    <div class="field"><label>New password</label><input class="input" id="resetPassword" type="password"></div>
    <div class="field"><label>Confirm password</label><input class="input" id="resetPasswordConfirm" type="password"></div>
    <div class="btnrow"><button class="primary" id="resetSubmit" type="button">Update password</button></div>
  </div>
</div>
<div class="toast" id="toast"></div>


<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
