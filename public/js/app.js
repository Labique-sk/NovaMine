(() => {
  const $ = (id) => document.getElementById(id);
  const esc = (value) => String(value ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const initials = (name) => String(name || "").trim().split(/\s+/).map((part) => part[0] || "").join("").slice(0, 2).toUpperCase();
  let currentUser = null;
  let historyRows = [];
  let candidatePage = 1;
  let candidateQuery = "";
  let candidateJobId = null;
  let candidateTotal = 0;
  let toastTimer;
  let latestCandidates = [];

  function csrfHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || "";
    return token ? { "X-CSRF-TOKEN": token } : {};
  }
  async function api(url, options = {}) {
    const headers = { Accept: "application/json", "X-Requested-With": "XMLHttpRequest", ...csrfHeaders(), ...(options.headers || {}) };
    let body = options.body;
    if (body && !(body instanceof FormData) && typeof body !== "string") {
      headers["Content-Type"] = "application/json";
      body = JSON.stringify(body);
    }
    const response = await fetch(url, { credentials: "same-origin", ...options, headers, body });
    const raw = await response.text();
    let data = null;
    try { data = raw ? JSON.parse(raw) : null; } catch { data = { message: raw }; }
    if (!response.ok) {
      const errors = data?.errors || {};
      const first = Object.values(errors)[0];
      throw new Error((Array.isArray(first) ? first[0] : first) || data?.message || `Request failed (${response.status})`);
    }
    return data;
  }
  function toast(message) {
    const element = $("toast");
    if (!element) return;
    element.textContent = message;
    element.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => element.classList.remove("show"), 3000);
  }

  // Restore the reference's decorative motion without its demo-account or
  // simulated-mining scripts. Keep the loops paused when the artwork is offscreen.
  function installReferenceMotion() {
    const founderTags = document.querySelector(".founder-tags");
    if (founderTags && !document.querySelector(".social-links")) {
      const links = document.createElement("div");
      links.className = "social-links";
      links.innerHTML = '<a class="btn outline" href="https://github.com/Labique-sk" target="_blank" rel="noopener noreferrer">GitHub ↗</a><a class="btn outline" href="https://www.linkedin.com/in/labique-shaikh-79169b289" target="_blank" rel="noopener noreferrer">LinkedIn ↗</a><a class="btn outline" href="mailto:labiqueshaikh470@gmail.com" title="labiqueshaikh470@gmail.com"><svg class="mail-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3.5 6.5h17v11h-17z"/><path d="m4 7 8 6 8-6"/></svg>Email</a>';
      founderTags.insertAdjacentElement("afterend", links);
    }

    const root = document.documentElement;
    const applyTheme = () => document.querySelectorAll(".theme-toggle").forEach((button) => {
      const dark = root.classList.contains("dark");
      button.textContent = dark ? "☀ Light" : "☾ Dark";
      button.setAttribute("aria-label", `Switch to ${dark ? "light" : "dark"} theme`);
    });
    try { root.classList.toggle("dark", (localStorage.getItem("novamine_theme") || "dark") === "dark"); }
    catch { root.classList.add("dark"); }
    [".public-actions", ".app-top-right"].forEach((selector) => {
      const host = document.querySelector(selector);
      if (!host || host.querySelector(".theme-toggle")) return;
      const button = document.createElement("button");
      button.type = "button";
      button.className = "theme-toggle";
      button.addEventListener("click", () => {
        root.classList.toggle("dark");
        try { localStorage.setItem("novamine_theme", root.classList.contains("dark") ? "dark" : "light"); } catch { /* Theme still changes for this page. */ }
        applyTheme();
      });
      host.insertBefore(button, host.firstChild);
    });
    applyTheme();

    const application = $("application");
    const topLeft = document.querySelector(".app-top-left");
    if (application && topLeft && !topLeft.querySelector(".sb-toggle")) {
      const toggle = document.createElement("button");
      toggle.type = "button";
      toggle.className = "sb-toggle";
      toggle.setAttribute("aria-label", "Toggle sidebar");
      toggle.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"></path></svg>';
      toggle.addEventListener("click", () => {
        const collapsed = application.classList.toggle("sb-collapsed");
        toggle.setAttribute("aria-expanded", String(!collapsed));
      });
      topLeft.insertBefore(toggle, topLeft.firstChild);
      if (window.innerWidth <= 760) application.classList.add("sb-collapsed");
      toggle.setAttribute("aria-expanded", String(!application.classList.contains("sb-collapsed")));
    }
    const founder = document.querySelector(".founder-avatar");
    if (founder && !founder.querySelector(".atom")) {
      const skills = ["Software Development", "Data & Analytics", "Product Building", "Backend", "Frontend", "Databases"];
      [{ angle: 0, duration: 9, direction: "normal" }, { angle: 60, duration: 13, direction: "reverse" }, { angle: 120, duration: 17, direction: "normal" }].forEach((orbit, index) => {
        const atom = document.createElement("span");
        atom.className = "atom";
        atom.style.setProperty("--a", `${orbit.angle}deg`);
        const track = document.createElement("span");
        track.className = "atom-track";
        atom.append(track);
        [0, 1].forEach((dotIndex) => {
          const dot = document.createElement("span");
          dot.className = "atom-e";
          dot.title = skills[index * 2 + dotIndex];
          dot.setAttribute("aria-label", dot.title);
          dot.style.animationDuration = `${orbit.duration}s`;
          dot.style.animationDirection = orbit.direction;
          dot.style.animationDelay = `${-orbit.duration * dotIndex / 2}s`;
          atom.append(dot);
        });
        founder.append(atom);
      });
    }

    {
      const orbitSettings = [
        { radius: 66, duration: 14, angle: 0, direction: "normal" },
        { radius: 78, duration: 20, angle: 200, direction: "reverse" },
        { radius: 66, duration: 14, angle: 180, direction: "normal" },
      ];
      document.querySelectorAll(".banner-orbit").forEach((orbit) => {
        if (orbit.querySelector(".bo-track")) return;
        [...orbit.querySelectorAll(":scope > span")].forEach((icon, index) => {
          const track = document.createElement("span");
          track.className = "bo-track";
          const setting = orbitSettings[index % orbitSettings.length];
          const delay = -(setting.angle / 360) * setting.duration;
          track.style.animationDuration = `${setting.duration}s`;
          track.style.animationDirection = setting.direction;
          track.style.animationDelay = `${delay}s`;
          icon.style.cssText = `position:absolute;left:calc(50% - 9px);top:calc(50% - ${setting.radius}px - 9px);animation:spinBack ${setting.duration}s linear infinite;animation-direction:${setting.direction === "normal" ? "reverse" : "normal"};animation-delay:${delay}s;`;
          track.append(icon);
          orbit.insertBefore(track, orbit.querySelector("b"));
        });
      });
    }

    // Match the source's pointer-following glass highlight, with one RAF update
    // per frame so pointer movement does not cause repeated layout work.
    let pointerFrame = 0;
    let pointerTarget = null;
    let pointerX = 0;
    let pointerY = 0;
    if (window.matchMedia?.("(pointer: fine)").matches) {
      document.addEventListener("pointermove", (event) => {
        const target = event.target.closest?.(".glass,.stat,.feature,.how-step,.compat-card,.feature-card,.table-card,.form-card,.home-banner,.about-story,.benefits-banner,.wbc-banner,.company-banner,.hero-stats,.founder-card,.home-cta");
        if (!target) return;
        pointerTarget = target;
        pointerX = event.clientX;
        pointerY = event.clientY;
        if (pointerFrame) return;
        pointerFrame = requestAnimationFrame(() => {
          pointerFrame = 0;
          if (!pointerTarget?.isConnected) return;
          const bounds = pointerTarget.getBoundingClientRect();
          pointerTarget.style.setProperty("--mx", `${pointerX - bounds.left}px`);
          pointerTarget.style.setProperty("--my", `${pointerY - bounds.top}px`);
        });
      }, { passive: true });
    }

    if ("IntersectionObserver" in window) {
      const setArtworkMotion = (element, playing) => {
        const state = playing ? "running" : "paused";
        const selector = ".bo-track,.bo-track > span,.banner-orbit,.atom-e";
        if (element.matches(selector)) element.style.animationPlayState = state;
        element.querySelectorAll(selector).forEach((node) => { node.style.animationPlayState = state; });
      };
      const observer = new IntersectionObserver((entries) => entries.forEach(({ target, isIntersecting }) => {
        setArtworkMotion(target, isIntersecting && !document.hidden);
      }));
      document.querySelectorAll(".banner-orbit,.founder-avatar").forEach((element) => observer.observe(element));
      document.addEventListener("visibilitychange", () => {
        document.querySelectorAll(".banner-orbit,.founder-avatar").forEach((element) => {
          const visible = !document.hidden && element.getBoundingClientRect().bottom > 0 && element.getBoundingClientRect().top < innerHeight;
          setArtworkMotion(element, visible);
        });
      });
    }
  }
  window.toast = toast;
  window.togglePass = (id, button) => {
    const input = $(id);
    if (!input) return;
    input.type = input.type === "password" ? "text" : "password";
    if (button) button.setAttribute("aria-label", input.type === "password" ? "Show password" : "Hide password");
  };

  const publicViews = ["homePublic", "aboutPublic", "contactPublic", "privacyPublic", "termsPublic", "login", "register", "forgotPassword", "resetPassword", "application"];
  function showPublic(id) {
    publicViews.forEach((view) => $(view)?.classList.add("hidden"));
    $("publicNav")?.classList.remove("hidden");
    const target = id === "home" ? "homePublic" : id;
    $(target)?.classList.remove("hidden");
    document.querySelectorAll("[data-public]").forEach((link) => link.classList.toggle("active", link.dataset.public === target));
    window.scrollTo({ top: 0, behavior: "smooth" });
  }
  window.showPublic = showPublic;

  const sideLabels = { overview: "Overview", mineAnalytics: "Candidate Mining", resultSet: "Mining History", result: "Candidates", profile: "Profile", settings: "Settings", about: "About Us" };
  const topPages = { home: "overview", about: "about", dashboard: "overview", search: "resultSet", analytics: "mineAnalytics", settings: "settings" };
  function goApp(page) {
    if (!$(page)) page = "overview";
    document.querySelectorAll(".page").forEach((node) => node.classList.toggle("active", node.id === page));
    document.querySelectorAll("[data-side]").forEach((button) => button.classList.toggle("active", button.dataset.side === page));
    document.querySelectorAll("[data-top]").forEach((button) => button.classList.toggle("active", topPages[button.dataset.top] === page));
    window.scrollTo({ top: 0, behavior: "smooth" });
    if (page === "overview") loadDashboard();
    if (page === "resultSet") loadHistory();
    if (page === "result") loadCandidates();
  }
  window.goApp = goApp;

  function showApp(user) {
    currentUser = user;
    ["homePublic", "aboutPublic", "contactPublic", "privacyPublic", "termsPublic", "login", "register", "forgotPassword", "resetPassword"].forEach((view) => $(view)?.classList.add("hidden"));
    $("publicNav")?.classList.add("hidden");
    $("application")?.classList.remove("hidden");
    const name = user?.name || "Workspace user";
    const first = name.trim().split(/\s+/)[0] || "there";
    if ($("topName")) $("topName").textContent = name;
    if ($("topInitial")) $("topInitial").textContent = initials(name);
    if ($("welcomeName")) $("welcomeName").textContent = first;
    if ($("profileInitial")) $("profileInitial").textContent = initials(name);
    if ($("profileName")) $("profileName").textContent = name;
    if ($("profileNameInput")) $("profileNameInput").value = name;
    if ($("profileEmailInput")) $("profileEmailInput").value = user?.email || "";
    if ($("profileResetEmail")) $("profileResetEmail").textContent = user?.email || "your account email";
    $("adminAccessCard")?.classList.toggle("hidden", !user?.admin_allowed);
    const secure = document.querySelector(".secure");
    if (secure) secure.innerHTML = "<b>✦ Platform secure</b>NovaMine workspace · live account";
    goApp("overview");
  }
  window.logout = async () => {
    try { await api("/api/auth/logout", { method: "POST" }); }
    catch (error) { toast(error.message); return; }
    currentUser = null;
    showPublic("login");
    toast("You have been signed out.");
  };

  function dataArray(result) { return Array.isArray(result?.data) ? result.data : []; }
  function metricCard(value, label) { return `<div class="glass metric"><b>${esc(value)}</b><span>${esc(label)}</span></div>`; }
  async function loadDashboard() {
    try {
      const [data, analytics, shortlists, searches] = await Promise.all([
        api("/api/dashboard"), api("/api/analytics"), api("/api/shortlists"), api("/api/searches"),
      ]);
      const totals = data.totals || {};
      const cards = document.querySelectorAll("#overview .stat-grid .stat");
      const shortlistCount = dataArray(shortlists).reduce((sum, list) => sum + (list.candidates || []).length, 0);
      const locationCount = Object.keys(analytics.locations || {}).length;
      const values = [totals.candidates ?? 0, shortlistCount, searches.meta?.total ?? dataArray(searches).length, locationCount];
      const labels = ["Total candidates", "Shortlisted", "Mining runs", "Locations"];
      const notes = ["Saved in your workspace", "Saved to candidate shortlists", "Completed and active searches", "Across saved profiles"];
      cards.forEach((card, index) => {
        const label = card.querySelector("label"), strong = card.querySelector("strong"), trend = card.querySelector(".trend");
        if (label) label.textContent = labels[index];
        if (strong) strong.textContent = Number.isFinite(values[index]) ? values[index].toLocaleString() : values[index];
        if (trend) trend.textContent = notes[index];
      });
      const activity = document.querySelector("#overview .activity");
      if (activity) {
        const items = data.activity || [];
        activity.innerHTML = items.length ? items.slice(0, 4).map((item) => `<div class="activity-row"><span class="activity-icon">✦</span><div><b>${esc(String(item.action || "Workspace activity").replaceAll(".", " "))}</b><small>${esc(item.created_at || "Recently")}</small></div></div>`).join("") : `<div class="activity-row"><span class="activity-icon">✦</span><div><b>No mining activity yet</b><small>Run a search to begin</small></div></div>`;
      }
      const recent = data.recent_candidates || [];
      const tbody = $("overviewRows");
      const tableHead = document.querySelector("#overview table thead");
      if (tableHead) tableHead.innerHTML = "<tr><th>CANDIDATE</th><th>ROLE</th><th>LOCATION</th><th>EXPERIENCE</th><th>STAGE</th></tr>";
      if (tbody) tbody.innerHTML = recent.slice(0, 6).map((candidate) => `<tr data-candidate="${Number(candidate.id)}"><td><b>${esc(candidate.name)}</b><br><span class="sub">${esc(candidate.email || "")}</span></td><td>${esc(candidate.role || "—")}</td><td>${esc(candidate.location || "—")}</td><td>${candidate.experience == null ? "—" : `${Number(candidate.experience)} yrs`}</td><td><span class="badge purple">${esc(candidate.status || "Review")}</span></td></tr>`).join("") || `<tr><td colspan="5" style="text-align:center;padding:28px">No candidates saved yet. Start a search to find records.</td></tr>`;
      const chart = document.querySelector("#overview .chartbox");
      const series = data.acquisition?.data || [];
      if (chart) {
        if (!series.some((value) => Number(value) > 0)) {
          chart.innerHTML = `<div class="sub" style="padding:48px 12px;text-align:center">Candidate acquisition will appear here after your first completed search.</div>`;
        } else {
          const max = Math.max(1, ...series.map(Number));
          const points = series.map((value, index) => [series.length <= 1 ? 300 : index * 600 / (series.length - 1), 145 - Number(value) / max * 120]);
          const line = points.map(([x, y], index) => `${index ? "L" : "M"}${x.toFixed(1)} ${y.toFixed(1)}`).join(" ");
          const area = `${line} L600 160 L0 160 Z`;
          chart.innerHTML = `<svg viewBox="0 0 600 180" preserveAspectRatio="none" role="img" aria-label="Candidate acquisition over the last 14 days"><defs><linearGradient id="liveArea" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#a855f7" stop-opacity=".3"/><stop offset="1" stop-color="#a855f7" stop-opacity="0"/></linearGradient></defs><g stroke="#ffffff16" stroke-dasharray="4 5"><path d="M0 25H600M0 65H600M0 105H600M0 145H600"/></g><path d="${area}" fill="url(#liveArea)"/><path d="${line}" fill="none" stroke="#c084fc" stroke-width="3"/><g fill="#9da4bd" font-size="9"><text x="0" y="176">14 days ago</text><text x="270" y="176">7 days ago</text><text x="555" y="176">Today</text></g></svg>`;
        }
      }
    } catch (error) { toast(error.message); }
  }

  const roles = ["Software Engineer", "Frontend Developer", "Backend Developer", "Full Stack Developer", "Data Scientist", "Data Engineer", "Cloud Engineer", "Cybersecurity Analyst", "Product Manager", "UX Designer", "DevOps Engineer", "Marketing Manager", "Recruiter"];
  const locations = ["India", "United States", "United Kingdom", "Canada", "Australia", "Germany", "France", "Singapore", "Netherlands", "Ireland", "Remote"];
  const industries = ["Software", "Information Technology", "Cybersecurity", "Finance", "Healthcare", "Education", "E-commerce", "Telecommunications", "Manufacturing", "Any industry"];
  function options(id, items, placeholder) {
    const select = $(id);
    if (select) select.innerHTML = `<option value="">${placeholder}</option>` + items.map((item) => `<option value="${esc(item)}">${esc(item)}</option>`).join("");
  }
  function mountView(sectionId, html) {
    const section = $(sectionId);
    if (!section) return null;
    const view = document.createElement("div");
    view.className = "cd-view";
    view.innerHTML = html;
    section.insertBefore(view, section.firstChild);
    [...section.children].forEach((child) => { if (child !== view) child.classList.add("hidden"); });
    return view;
  }
  function installCandidateViews() {
    const names = { overview: "Overview", mineAnalytics: "Candidate Mining", resultSet: "Mining History", result: "Candidates", profile: "Profile", settings: "Settings", about: "About Us" };
    document.querySelectorAll("[data-side]").forEach((button) => { if (names[button.dataset.side]) button.innerHTML = `<span class="si">${button.dataset.side === "overview" ? "⌂" : button.dataset.side === "mineAnalytics" ? "▥" : button.dataset.side === "resultSet" ? "◷" : button.dataset.side === "result" ? "♙" : "⚙"}</span>${names[button.dataset.side]}`; });
    document.querySelectorAll("[data-top]").forEach((button) => { const label = { dashboard: "Dashboard", analytics: "Mining", search: "History" }[button.dataset.top]; if (label) button.textContent = label; });
    const mine = mountView("mineAnalytics", `<div class="page-head"><div><div class="eyebrow">CANDIDATE MINING</div><h1>Find candidates</h1><p>Describe the role you are hiring for. Each search uses configured data sources and saves real results to your workspace.</p></div></div><div class="glass form-card"><div class="form-grid"><div class="field full"><label for="cdTitle">Search title</label><input id="cdTitle" maxlength="120" placeholder="e.g. Senior React developers — London"></div><div class="field"><label for="cdRole">Job role</label><select id="cdRole"></select></div><div class="field"><label for="cdCountry">Location</label><select id="cdCountry"></select></div><div class="field"><label for="cdIndustry">Industry</label><select id="cdIndustry"></select></div><div class="field"><label for="cdExp">Minimum experience</label><select id="cdExp"><option value="">Any experience</option><option value="1">1+ years</option><option value="3">3+ years</option><option value="5">5+ years</option><option value="8">8+ years</option></select></div><div class="field full"><label for="cdKw">Skills / keywords (comma separated)</label><input id="cdKw" maxlength="255" placeholder="e.g. React, TypeScript, Node.js"></div></div><div class="mining-bottom"><span class="sub">Searches use the data sources configured for your NovaMine workspace.</span><button class="btn" id="cdStart" type="button">✧ Start mining</button></div><div id="cdProg" style="display:none;margin-top:14px"><div class="sub" id="cdProgText">Preparing search…</div><div style="height:6px;border-radius:6px;background:rgba(124,92,255,.15);margin-top:8px;overflow:hidden"><div id="cdProgBar" style="height:100%;width:0;background:linear-gradient(90deg,#a78bfa,#6746d8);transition:width .4s"></div></div></div></div>`);
    options("cdRole", roles, "Choose a role"); options("cdCountry", locations, "Any location"); options("cdIndustry", industries, "Any industry");
    const history = mountView("resultSet", `<div class="page-head"><div><div class="eyebrow">MINING HISTORY</div><h1>Saved searches</h1><p>Review completed searches and open the candidates found in each run.</p></div><button class="btn outline" id="cdNew" type="button">✧ New search</button></div><div class="metric-cards" id="histMetrics"></div><div class="glass table-card"><div class="table-scroll" style="max-height:none"><table><thead><tr><th>SEARCH</th><th>CRITERIA</th><th>RECORDS</th><th>DATE</th><th>STATUS</th><th></th></tr></thead><tbody id="histRows"></tbody></table></div></div>`);
    const candidates = mountView("result", `<div class="page-head"><div><div class="eyebrow">CANDIDATES</div><h1 id="cdRunTitle">Candidate records</h1><p id="cdRunMeta"></p></div><div style="display:flex;gap:9px;flex-wrap:wrap"><button class="btn outline" id="cdBack" type="button">← Mining history</button><button class="btn outline" id="cdExport" type="button">⇩ Export CSV</button></div></div><div class="metric-cards" id="candMetrics"></div><div class="glass table-card"><div class="card-head"><div><h3>Candidate records</h3><div class="sub">Review the saved profiles associated with your search.</div></div><div class="table-tools"><input id="candSearch" class="searchbox" placeholder="Search candidates…"></div></div><div class="table-scroll" style="max-height:none"><table><thead><tr><th>NAME</th><th>ROLE</th><th>COMPANY</th><th>LOCATION</th><th>EXPERIENCE</th><th>SKILLS</th><th>VERIFICATION</th><th></th></tr></thead><tbody id="candRows"></tbody></table></div><div class="pagination"><span id="candSummary"></span><div class="pages"><button id="candPrev" type="button">‹</button><button id="candNext" type="button">›</button></div></div></div>`);
    $("cdNew").onclick = () => goApp("mineAnalytics");
    $("cdBack").onclick = () => goApp("resultSet");
    $("cdStart").onclick = startRealMining;
    $("cdExport").onclick = createRealExport;
    $("candSearch").oninput = (event) => { clearTimeout(window.__candidateSearchTimer); window.__candidateSearchTimer = setTimeout(() => { candidateQuery = event.target.value.trim(); candidatePage = 1; loadCandidates(); }, 250); };
    $("candPrev").onclick = () => { if (candidatePage > 1) { candidatePage--; loadCandidates(); } };
    $("candNext").onclick = () => { if (candidatePage * 10 < candidateTotal) { candidatePage++; loadCandidates(); } };
    history.addEventListener("click", (event) => {
      const remine = event.target.closest("[data-remine]");
      if (remine) { event.stopPropagation(); remineSearch(Number(remine.dataset.remine)); return; }
      const row = event.target.closest("tr[data-search]");
      if (row) { candidateJobId = row.dataset.job || null; candidateQuery = ""; candidatePage = 1; if ($("candSearch")) $("candSearch").value = ""; $("cdRunTitle").textContent = row.dataset.title || "Candidate records"; $("cdRunMeta").textContent = `${row.dataset.count} results · ${row.dataset.date}`; goApp("result"); }
    });
    candidates.addEventListener("click", async (event) => {
      if (event.target.closest("#candidateBack")) { $("candidateDetailPanel")?.remove(); return; }
      const row = event.target.closest("tr[data-candidate]");
      if (!row || event.target.closest("button")) return;
      const id = row.dataset.candidate;
      const detail = await api(`/api/candidates/${id}`);
      renderCandidateDetail(detail.candidate || detail);
    });
  }

  async function startRealMining() {
    const button = $("cdStart");
    const role = $("cdRole").value.trim();
    const title = $("cdTitle").value.trim();
    const keywords = $("cdKw").value.trim();
    if (!role) { toast("Choose a job role before starting a search."); return; }
    button.disabled = true;
    $("cdProg").style.display = "block";
    $("cdProgText").textContent = "Searching configured data sources…";
    $("cdProgBar").style.width = "25%";
    try {
      const result = await api("/api/mining", { method: "POST", body: { role, location: $("cdCountry").value, industry: $("cdIndustry").value, experience: $("cdExp").value, keywords, skills: keywords, sources: ["people_data_labs", "tavily"], include_demo: false } });
      const job = result.job || {};
      $("cdProgBar").style.width = "100%";
      $("cdProgText").textContent = job.status === "completed" ? `Mining complete · ${job.processed || 0} candidate records saved.` : job.error_message || "Search finished.";
      toast(job.status === "completed" ? `Mining complete: ${job.processed || 0} candidates saved.` : job.error_message || "Mining did not complete.");
      candidateJobId = job.id || null;
      setTimeout(() => { $("cdProg").style.display = "none"; $("cdProgBar").style.width = "0"; }, 4500);
      await Promise.all([loadDashboard(), loadHistory()]);
      if (job.status === "completed") { candidatePage = 1; goApp("result"); }
    } catch (error) {
      $("cdProgText").textContent = error.message;
      $("cdProgBar").style.width = "100%";
      toast(error.message);
    } finally { button.disabled = false; }
  }

  async function remineSearch(searchId) {
    const search = historyRows.find((row) => Number(row.id) === searchId);
    if (!search) return;
    const filters = search.filters || {};
    try {
      const result = await api("/api/mining", { method: "POST", body: { role: search.query || "Software Engineer", location: filters.location || "", industry: filters.industry || "", experience: filters.experience || "", skills: filters.skills || filters.keywords || "", keywords: filters.keywords || "", sources: ["people_data_labs", "tavily"], include_demo: false } });
      const job = result.job || {};
      toast(job.status === "completed" ? `Re-mining complete: ${job.processed || 0} candidates saved.` : job.error_message || "Re-mining did not complete.");
      candidateJobId = job.id || null;
      await Promise.all([loadHistory(), loadDashboard()]);
      if (job.status === "completed") { candidatePage = 1; goApp("result"); }
    } catch (error) { toast(error.message); }
  }

  async function loadHistory() {
    try {
      const result = await api("/api/searches?per_page=100");
      historyRows = dataArray(result);
      const totalCandidates = historyRows.reduce((sum, row) => sum + Number(row.result_count || 0), 0);
      const completed = historyRows.filter((row) => row.status === "completed").length;
      $("histMetrics").innerHTML = metricCard(historyRows.length, "Saved searches") + metricCard(totalCandidates, "Candidates found") + metricCard(completed, "Completed searches");
      $("histRows").innerHTML = historyRows.map((row) => {
        const filters = row.filters || {};
        const criteria = [row.query, filters.location, filters.industry, filters.keywords].filter(Boolean).join(" · ") || "Search criteria";
        return `<tr data-search="${Number(row.id)}" data-job="${Number(row.mining_job_id) || ""}" data-title="${esc(row.query || "Candidate search")}" data-count="${Number(row.result_count || 0)}" data-date="${esc(row.created_at || "")}" style="cursor:pointer"><td><b>${esc(row.query || "Candidate search")}</b></td><td class="muted">${esc(criteria)}</td><td><span class="badge purple">${Number(row.result_count || 0)}</span></td><td>${esc(row.created_at || "")}</td><td>${esc(row.status || "—")}</td><td><button type="button" class="btn outline cd-remine" data-remine="${Number(row.id)}">↻ Re-mine</button></td></tr>`;
      }).join("") || `<tr><td colspan="5" style="text-align:center;padding:30px">No searches yet. Start a candidate search to see results here.</td></tr>`;
    } catch (error) { toast(error.message); }
  }

  async function loadCandidates() {
    const params = new URLSearchParams({ q: candidateQuery, page: String(candidatePage), per_page: "10", sort: "created_at", dir: "desc" });
    if (candidateJobId) params.set("mining_job_id", candidateJobId);
    $("candRows").innerHTML = `<tr><td colspan="8" style="text-align:center;padding:28px">Loading saved candidates…</td></tr>`;
    try {
      const result = await api("/api/candidates?" + params.toString());
      const rows = dataArray(result);
      const meta = result.meta || {};
      candidateTotal = Number(meta.total || 0);
      latestCandidates = rows;
      const verified = rows.filter((candidate) => candidate.status === "verified").length;
      const avg = rows.length ? Math.round(rows.reduce((sum, candidate) => sum + Number(candidate.score || 0), 0) / rows.length) : 0;
      $("candMetrics").innerHTML = metricCard(candidateTotal, "Candidates found") + metricCard(verified, "Verified on this page") + metricCard(`${avg}%`, "Average match score") + metricCard(meta.last_page || 1, "Result pages");
      $("candRows").innerHTML = rows.map((candidate) => `<tr data-candidate="${Number(candidate.id)}"><td><b>${esc(candidate.name)}</b><div class="sub">${esc(candidate.email || "")}</div></td><td>${esc(candidate.role || "—")}</td><td>${esc(candidate.company || "—")}</td><td>${esc(candidate.location || "—")}</td><td>${candidate.experience == null ? "—" : `${Number(candidate.experience)} yrs`}</td><td>${(candidate.skills || []).slice(0, 4).map((skill) => `<span class="cd-chip">${esc(skill)}</span>`).join("") || "—"}</td><td><span class="badge ${candidate.status === "verified" ? "" : "purple"}">${esc(candidate.status || "Review")}</span></td><td><button type="button" class="btn outline cd-save" data-id="${Number(candidate.id)}">Save</button></td></tr>`).join("") || `<tr><td colspan="8" style="text-align:center;padding:30px">No candidates match this search.</td></tr>`;
      $("candSummary").textContent = `Showing ${candidateTotal ? ((candidatePage - 1) * 10) + 1 : 0}–${Math.min(candidatePage * 10, candidateTotal)} of ${candidateTotal} candidates`;
      $("candPrev").disabled = candidatePage <= 1;
      $("candNext").disabled = candidatePage * 10 >= candidateTotal;
    } catch (error) { $("candRows").innerHTML = `<tr><td colspan="8" style="text-align:center;padding:28px">${esc(error.message)}</td></tr>`; }
  }
  function renderCandidateDetail(candidate) {
    goApp("result");
    const fields = [["Role", candidate.designation], ["Company", candidate.company], ["Location", candidate.location], ["Experience", candidate.years_experience == null ? "—" : `${candidate.years_experience} years`], ["Industry", candidate.industry], ["Email", candidate.professional_email], ["Phone", candidate.phone], ["Source", candidate.source_name]];
    const view = document.querySelector("#result .cd-view");
    if (!view) return;
    let panel = $("candidateDetailPanel");
    if (!panel) { panel = document.createElement("section"); panel.id = "candidateDetailPanel"; panel.className = "glass"; panel.style.cssText = "padding:22px;margin-top:16px"; view.appendChild(panel); }
    panel.innerHTML = `<div class="page-head"><div><div class="eyebrow">CANDIDATE PROFILE</div><h2 style="font-size:22px;margin:8px 0">${esc(candidate.name)}</h2><p>Review the information saved from its public source.</p></div><button class="btn outline" id="candidateBack" type="button">Close</button></div><div class="form-grid">${fields.map(([label, value]) => `<div class="field"><label>${esc(label)}</label><input readonly value="${esc(value || "—")}"></div>`).join("")}</div><p class="sub" style="margin-top:15px">Match score: ${Number(candidate.match_score || 0)}% · ${esc(candidate.verification_status || "unverified")}</p>${candidate.source_url ? `<a class="btn outline" href="${esc(candidate.source_url)}" target="_blank" rel="noopener">Open source</a>` : ""}`;
    panel.scrollIntoView({ behavior: "smooth", block: "start" });
  }
  async function createRealExport() {
    try {
      const { export: record } = await api("/api/exports", { method: "POST", body: { format: "csv", ...(candidateJobId ? { mining_job_id: candidateJobId } : {}) } });
      window.location.href = `/api/exports/${record.id}/download`;
      toast("Candidate CSV export is ready.");
    } catch (error) { toast(error.message); }
  }
  window.exportRows = createRealExport;

  async function loadPreferences() {
    try {
      const settings = await api("/api/settings");
      const preferences = settings.user?.preferences || {};
      const controls = document.querySelectorAll("#settings .toggle");
      ["dark", "mining_notifications", "email_updates", "compact_tables"].forEach((key, index) => {
        if (controls[index] && Object.hasOwn(preferences, key)) controls[index].checked = Boolean(preferences[key]);
      });
      if (Object.hasOwn(preferences, "dark")) document.documentElement.classList.toggle("dark", Boolean(preferences.dark));
    } catch (error) { toast(error.message); }
  }
  async function savePreferences(button) {
    const controls = [...document.querySelectorAll("#settings .toggle")];
    const preferences = Object.fromEntries(["dark", "mining_notifications", "email_updates", "compact_tables"].map((key, index) => [key, Boolean(controls[index]?.checked)]));
    button.disabled = true;
    try {
      await api("/api/settings", { method: "PUT", body: { preferences } });
      document.documentElement.classList.toggle("dark", preferences.dark);
      toast("Preferences saved.");
    } catch (error) { toast(error.message); }
    finally { button.disabled = false; }
  }

  function validateEmail(input, errorElement) {
    const email = input.value.trim();
    let message = "";
    if (!email) message = "Enter your email address.";
    else if (email.length > 30) message = "Email must be 30 characters or fewer.";
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) message = "Enter a valid email address.";
    errorElement.textContent = message;
    input.setAttribute("aria-invalid", String(Boolean(message)));
    return !message;
  }

  function validatePassword(input, errorElement) {
    const password = input.value;
    let message = "";
    if (password.length < 8) message = "Password must be at least 8 characters.";
    else if (password.length > 11) message = "Password must be 11 characters or fewer.";
    errorElement.textContent = message;
    input.setAttribute("aria-invalid", String(Boolean(message)));
    return !message;
  }

  function bindCredentialValidation(inputId, errorId, validator) {
    const input = $(inputId), errorElement = $(errorId);
    if (!input || !errorElement) return;
    input.addEventListener("input", () => {
      if (!input.value) { errorElement.textContent = ""; input.removeAttribute("aria-invalid"); return; }
      validator(input, errorElement);
    });
  }

  async function requestPasswordReset(email, button) {
    const address = String(email || "").trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(address) || address.length > 30) {
      toast(address.length > 30 ? "Email must be 30 characters or fewer." : "Enter a valid email address.");
      return;
    }
    if (button) button.disabled = true;
    try {
      await api("/api/auth/forgot-password", { method: "POST", body: { email: address } });
      toast("If that address belongs to an account, a password reset link is on its way.");
    } catch (error) { toast(error.message); }
    finally { if (button) button.disabled = false; }
  }

  function renderAdminDashboard(data) {
    const summary = data.summary || {};
    $("adminTotalUsers").textContent = summary.users ?? 0;
    $("adminActiveUsers").textContent = summary.active_users ?? 0;
    $("adminDeactivatedUsers").textContent = summary.deactivated_users ?? 0;
    $("adminMiningRuns").textContent = summary.mining_runs ?? 0;
    $("adminCandidateTotal").textContent = summary.candidates_mined ?? 0;
    const rows = data.users || [];
    $("adminUserRows").innerHTML = rows.length ? rows.map((user) => {
      const activeSince = user.active_since ? new Date(user.active_since).toLocaleDateString() : "—";
      return `<tr><td><strong>${esc(user.name)}</strong><br><span class="sub">${esc(user.email)}</span></td><td>${user.mining_runs}</td><td>${user.candidates_mined}</td><td>${activeSince}</td><td><span class="admin-status ${user.active ? "is-active" : "is-deactivated"}">${user.active ? "Active" : "Deactivated"}</span></td><td><button class="btn ${user.active ? "outline" : ""} admin-status-toggle" data-user-id="${user.id}" data-active="${user.active ? "false" : "true"}" type="button">${user.active ? "Deactivate" : "Reactivate"}</button></td></tr>`;
    }).join("") : '<tr><td colspan="6">No users found.</td></tr>';
  }

  async function loadAdminDashboard() {
    const data = await api("/api/admin/users");
    renderAdminDashboard(data);
    $("adminUnlockForm")?.classList.add("hidden");
    $("adminDashboard")?.classList.remove("hidden");
  }

  async function login(event) {
    event.preventDefault();
    if (!validateEmail($("loginEmail"), $("loginEmailErr")) || !validatePassword($("loginPassword"), $("loginPassErr"))) return;
    const button = $("loginForm").querySelector('button[type="submit"]');
    button.disabled = true;
    try {
      const { user } = await api("/api/auth/login", { method: "POST", body: { email: $("loginEmail").value.trim(), password: $("loginPassword").value, remember: $("loginRemember")?.checked || false } });
      showApp(user);
      toast(`Welcome back, ${user.name}.`);
    } catch (error) { toast(error.message); }
    finally { button.disabled = false; }
  }
  async function register(event) {
    event.preventDefault();
    const validEmail = validateEmail($("regEmail"), $("regEmailErr"));
    const validPassword = validatePassword($("regPassword"), $("regPasswordErr"));
    if (!validEmail || !validPassword) return;
    if ($("regPassword").value !== $("regConfirm").value) { toast("The passwords do not match."); return; }
    if ($("terms") && !$("terms").checked) { toast("Please accept the terms before creating your account."); return; }
    const button = $("registerForm").querySelector('button[type="submit"]');
    button.disabled = true;
    try {
      const { user } = await api("/api/auth/register", { method: "POST", body: { name: $("regName").value.trim(), email: $("regEmail").value.trim(), password: $("regPassword").value, password_confirmation: $("regConfirm").value } });
      showApp(user);
      toast("Your NovaMine account is ready.");
    } catch (error) { toast(error.message); }
    finally { button.disabled = false; }
  }

  function connectInteractions() {
    document.querySelectorAll("[data-public]").forEach((link) => link.addEventListener("click", (event) => { event.preventDefault(); showPublic(link.dataset.public); }));
    document.querySelectorAll("[data-side]").forEach((button) => button.addEventListener("click", () => {
      if (button.dataset.side === "result") { candidateJobId = null; candidateQuery = ""; candidatePage = 1; }
      goApp(button.dataset.side);
    }));
    document.querySelectorAll("[data-top]").forEach((button) => button.addEventListener("click", () => goApp(topPages[button.dataset.top] || "overview")));
    $("loginForm")?.addEventListener("submit", login);
    $("registerForm")?.addEventListener("submit", register);
    bindCredentialValidation("loginEmail", "loginEmailErr", validateEmail);
    bindCredentialValidation("loginPassword", "loginPassErr", validatePassword);
    bindCredentialValidation("regEmail", "regEmailErr", validateEmail);
    bindCredentialValidation("regPassword", "regPasswordErr", validatePassword);
    bindCredentialValidation("forgotEmail", "forgotEmailErr", validateEmail);
    bindCredentialValidation("resetEmail", "resetEmailErr", validateEmail);
    bindCredentialValidation("resetNewPassword", "resetPasswordErr", validatePassword);
    bindCredentialValidation("resetConfirmPassword", "resetConfirmErr", validatePassword);
    $("forgotPasswordForm")?.addEventListener("submit", (event) => {
      event.preventDefault();
      if (!validateEmail($("forgotEmail"), $("forgotEmailErr"))) return;
      requestPasswordReset($("forgotEmail").value, $("forgotPasswordForm").querySelector('button[type="submit"]'));
    });
    $("profilePasswordReset")?.addEventListener("click", (event) => requestPasswordReset($("profileEmailInput")?.value, event.currentTarget));
    $("adminUnlockForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const error = $("adminPasswordError");
      error.textContent = "";
      const button = $("adminUnlockForm").querySelector('button[type="submit"]');
      button.disabled = true;
      try {
        const data = await api("/api/admin/unlock", { method: "POST", body: { password: $("adminPassword").value } });
        $("adminPassword").value = "";
        renderAdminDashboard(data);
        $("adminUnlockForm").classList.add("hidden");
        $("adminDashboard").classList.remove("hidden");
      } catch (requestError) {
        error.textContent = requestError.message;
      } finally { button.disabled = false; }
    });
    $("adminLock")?.addEventListener("click", async () => {
      try { await api("/api/admin/lock", { method: "POST" }); }
      finally {
        $("adminDashboard")?.classList.add("hidden");
        $("adminUnlockForm")?.classList.remove("hidden");
        if ($("adminUserRows")) $("adminUserRows").innerHTML = '<tr><td colspan="6">Unlock to load users.</td></tr>';
      }
    });
    $("adminUserRows")?.addEventListener("click", async (event) => {
      const button = event.target.closest(".admin-status-toggle");
      if (!button) return;
      button.disabled = true;
      try {
        const data = await api(`/api/admin/users/${button.dataset.userId}/status`, { method: "PUT", body: { active: button.dataset.active === "true" } });
        renderAdminDashboard(data);
        toast("Account status updated.");
      } catch (requestError) { toast(requestError.message); button.disabled = false; }
    });
    $("resetPasswordForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const validEmail = validateEmail($("resetEmail"), $("resetEmailErr"));
      const validPassword = validatePassword($("resetNewPassword"), $("resetPasswordErr"));
      const confirmation = $("resetConfirmPassword").value;
      const confirmationError = $("resetConfirmErr");
      const passwordsMatch = $("resetNewPassword").value === confirmation;
      confirmationError.textContent = passwordsMatch ? "" : "The passwords do not match.";
      if (!validEmail || !validPassword || !passwordsMatch) return;
      const button = $("resetPasswordForm").querySelector('button[type="submit"]');
      button.disabled = true;
      try {
        await api("/api/auth/reset-password", { method: "POST", body: { token: $("resetToken").value, email: $("resetEmail").value.trim(), password: $("resetNewPassword").value, password_confirmation: confirmation } });
        showPublic("login");
        toast("Your password has been reset. Sign in with your new password.");
      } catch (error) { toast(error.message); }
      finally { button.disabled = false; }
    });
    $("logout")?.addEventListener("click", window.logout);
    const preferencesSave = document.querySelector("#settings .settings-grid .btn:not(.outline)");
    preferencesSave?.addEventListener("click", () => savePreferences(preferencesSave));
    document.querySelectorAll("#mineAnalytics input[type=checkbox]").forEach((checkbox) => checkbox.addEventListener("change", () => { const count = document.querySelectorAll("#mineAnalytics input[type=checkbox]:checked").length; if ($("pointCount")) $("pointCount").textContent = count; }));
    const loginForm = $("loginForm");
    if (loginForm) {
      const remember = loginForm.querySelector('input[type="checkbox"]'); if (remember) remember.id = "loginRemember";
      const pwdLabel = loginForm.querySelector('label[for="regName"]'); if (pwdLabel) pwdLabel.htmlFor = "loginPassword";
    }
    $("googleSignIn")?.addEventListener("click", () => { window.location.href = "/auth/google/redirect"; });
    $("linkedinSignIn")?.addEventListener("click", () => { window.location.href = "/auth/linkedin/redirect"; });
    document.querySelectorAll("#overview .stat .iconbox").forEach((element, index) => { element.textContent = ["♙", "✓", "▥", "✦"][index]; });
    $("overviewRows")?.addEventListener("click", (event) => { const row = event.target.closest("tr[data-candidate]"); if (row) { api(`/api/candidates/${row.dataset.candidate}`).then((data) => renderCandidateDetail(data.candidate || data)).catch((error) => toast(error.message)); } });
    $("candRows")?.addEventListener("click", async (event) => {
      const button = event.target.closest(".cd-save");
      if (!button) return;
      button.disabled = true;
      try { await api(`/api/candidates/${button.dataset.id}/shortlist`, { method: "POST", body: {} }); button.textContent = "Saved"; toast("Candidate added to Saved candidates."); }
      catch (error) { toast(error.message); button.disabled = false; }
    });
  }

  async function boot() {
    installReferenceMotion();
    installCandidateViews();
    connectInteractions();
    const params = new URLSearchParams(location.search);
    const authMessages = { google_success: "Signed in with Google.", google_not_configured: "Google sign-in needs GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in Render.", google_cancelled: "Google sign-in was cancelled.", google_email_unverified: "Google did not return a verified email address.", google_account_conflict: "Sign in with the existing email account before linking Google.", google_failed: "Google sign-in failed. Check the OAuth configuration and callback URL." };
    Object.assign(authMessages, { linkedin_success: "Signed in with LinkedIn.", linkedin_not_configured: "LinkedIn sign-in needs LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET in Render.", linkedin_cancelled: "LinkedIn sign-in was cancelled.", linkedin_email_unverified: "LinkedIn did not provide a verified email address.", linkedin_account_conflict: "Sign in with the existing account before linking LinkedIn.", linkedin_failed: "LinkedIn sign-in failed. Check the OAuth configuration and callback URL.", account_deactivated: "This account has been deactivated. Contact NovaMine support for help." });
    if (authMessages[params.get("auth")]) toast(authMessages[params.get("auth")]);
    if (location.pathname.startsWith("/password/reset/") && $("resetToken")?.value) { showPublic("resetPassword"); return; }
    try {
      const { user } = await api("/api/auth/me");
      if (user) { showApp(user); await loadPreferences(); } else showPublic("homePublic");
    } catch { showPublic("homePublic"); }
  }
  boot();
})();
