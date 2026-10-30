(() => {
  const $ = (id) => document.getElementById(id);
  function csrfHeaders() {
    const raw = document.cookie.split("; ").find((row) => row.startsWith("XSRF-TOKEN="));
    if (raw) {
      try {
        return { "X-XSRF-TOKEN": decodeURIComponent(raw.slice("XSRF-TOKEN=".length)) };
      } catch {
        return { "X-XSRF-TOKEN": raw.slice("XSRF-TOKEN=".length) };
      }
    }
    const meta = document.querySelector('meta[name="csrf-token"]')?.content || "";
    return meta ? { "X-CSRF-TOKEN": meta } : {};
  }
  const reducedMotion = matchMedia("(prefers-reduced-motion: reduce)").matches;

  let currentUser = null;
  let currentPage = "dashboard";
  let sortKey = "score";
  let sortDir = "desc";
  let page = 1;
  // Start with the saved candidate list visible. Mining form values are
  // collection criteria, not active result filters until “Filters” is clicked.
  let showAllCandidates = true;
  let charts = {};
  let miningJobId = null;
  let miningPoll = null;
  let searchTimer = null;
  let activeShortlistId = null;

  async function api(path, options = {}) {
    const headers = Object.assign(
      { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
      csrfHeaders(),
      options.headers || {}
    );
    if (options.body && !(options.body instanceof FormData) && typeof options.body !== "string") {
      headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(options.body);
    }
    const res = await fetch(path, Object.assign({ credentials: "same-origin" }, options, { headers }));
    const text = await res.text();
    let data = null;
    try { data = text ? JSON.parse(text) : null; } catch { data = { message: text }; }
    if (!res.ok) {
      const msg = data?.message || data?.error || firstError(data) || `Request failed (${res.status})`;
      const err = new Error(msg);
      err.status = res.status;
      err.payload = data;
      throw err;
    }
    return data;
  }

  function firstError(data) {
    const errors = data?.errors;
    if (!errors) return null;
    const first = Object.values(errors)[0];
    return Array.isArray(first) ? first[0] : String(first);
  }

  function toast(msg) {
    const el = $("toast");
    el.textContent = msg;
    el.classList.add("show");
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(() => el.classList.remove("show"), 2400);
  }

  function initials(n) {
    return String(n || "").split(" ").map((x) => x[0] || "").join("").slice(0, 2).toUpperCase();
  }

  function pill(status) {
    const verified = status === "verified";
    return `<span class="pill ${verified ? "verified" : "review"}">${verified ? "Verified" : "Review"}</span>`;
  }

  function setLoading(btn, on, label) {
    if (!btn) return;
    btn.disabled = !!on;
    if (label) btn.dataset.label = btn.dataset.label || btn.textContent;
    if (on) btn.textContent = label;
    else if (btn.dataset.label) btn.textContent = btn.dataset.label;
  }

  function showAuth(show) {
    $("auth").classList.toggle("hidden", !show);
  }

  function updateThumb(containerId) {
    const container = $(containerId);
    if (!container) return;
    const active = container.querySelector("[data-page].active");
    const thumb = container.querySelector(".navthumb");
    if (!active || !thumb) return;
    const cr = container.getBoundingClientRect(), ar = active.getBoundingClientRect();
    thumb.style.width = ar.width + "px";
    thumb.style.transform = "translateX(" + (ar.left - cr.left) + "px)";
  }
  function updateThumbs() { updateThumb("nav"); updateThumb("bottomnav"); }

  async function nav(pageName) {
    currentPage = pageName;
    document.querySelectorAll(".page").forEach((x) => x.classList.add("hidden"));
    const target = $(pageName);
    if (target) target.classList.remove("hidden");
    document.querySelectorAll("[data-page]").forEach((x) => x.classList.toggle("active", x.dataset.page === pageName));
    window.scrollTo({ top: 0, behavior: reducedMotion ? "auto" : "smooth" });
    requestAnimationFrame(updateThumbs);
    try {
      if (pageName === "dashboard") await loadDashboard();
      if (pageName === "mining") await loadCandidates();
      if (pageName === "analytics") await loadAnalytics();
      if (pageName === "saved") await loadShortlists();
      if (pageName === "exports") await loadExportsPage();
      if (pageName === "settings") await loadSettings();
    } catch (e) {
      toast(e.message);
    }
  }

  document.querySelectorAll("[data-page]").forEach((x) => x.addEventListener("click", (e) => {
    e.preventDefault();
    nav(x.dataset.page);
  }));

  function chartDefaults() {
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: "rgba(255,255,255,.045)" }, ticks: { color: "#69717e", font: { size: 9 } } },
        y: { grid: { color: "rgba(255,255,255,.045)" }, ticks: { color: "#69717e", font: { size: 9 } } },
      },
    };
  }

  function upsertChart(id, type, payload, extra) {
    const canvas = $(id);
    if (!canvas) return;
    if (charts[id]) charts[id].destroy();
    charts[id] = new Chart(canvas, { type, data: payload, options: extra || chartDefaults() });
  }

  async function loadDashboard() {
    const data = await api("/api/dashboard");
    $("sCandidates").textContent = Number(data.totals.candidates).toLocaleString();
    $("sVerified").textContent = Number(data.totals.verified).toLocaleString();
    $("sJobs").textContent = Number(data.totals.jobs).toLocaleString();
    $("sScore").textContent = (data.totals.average_score || 0) + "%";
    document.querySelector("#sCandidates").nextElementSibling.textContent = data.totals.candidate_trend;
    upsertChart("lineChart", "line", {
      labels: data.acquisition.labels,
      datasets: [{ data: data.acquisition.data, borderColor: "#68aaff", backgroundColor: "rgba(104,170,255,.10)", fill: true, tension: 0.38, pointRadius: 0 }],
    });
    const sourceLabels = Object.keys(data.sources || {});
    upsertChart("sourceChart", "doughnut", {
      labels: sourceLabels.length ? sourceLabels : ["No sources yet"],
      datasets: [{ data: sourceLabels.length ? Object.values(data.sources) : [1], backgroundColor: ["#0a84ff", "#bf5af2", "#30d158", "#ffd60a"], borderWidth: 0 }],
    }, { responsive: true, maintainAspectRatio: false, cutout: "72%", plugins: { legend: { position: "bottom", labels: { color: "#a1a1a6", font: { size: 9 }, boxWidth: 8 } } } });
    const skillLabels = Object.keys(data.skills || {});
    upsertChart("skillChart", "bar", {
      labels: skillLabels.length ? skillLabels : ["None"],
      datasets: [{ data: skillLabels.length ? Object.values(data.skills) : [0], backgroundColor: "rgba(10,132,255,.55)", borderRadius: 6 }],
    }, { ...chartDefaults(), indexAxis: "y" });
    renderRows($("recentBody"), data.recent_candidates || [], 6);
  }

  async function loadCandidates() {
    $("candidateBody").innerHTML = `<tr class="loading-row"><td colspan="7">Loading candidates…</td></tr>`;
    
    // Get mining configuration filters
    const role = $("role")?.value?.trim() || "";
    const location = $("location")?.value || "";
    const experience = $("experience")?.value || "";
    const industry = $("industry")?.value?.trim() || "";
    const skills = $("skills")?.value?.trim() || "";
    
    // Convert experience range to min/max values
    let experience_min = "";
    let experience_max = "";
    if (experience === "0–2 years") { experience_min = "0"; experience_max = "2"; }
    else if (experience === "2–5 years") { experience_min = "2"; experience_max = "5"; }
    else if (experience === "5–8 years") { experience_min = "5"; experience_max = "8"; }
    else if (experience === "8+ years") { experience_min = "8"; }
    
    const params = new URLSearchParams({
      q: $("search").value.trim(),
      page: String(page),
      per_page: "10",
      sort: showAllCandidates ? "created_at" : sortKey,
      dir: showAllCandidates ? "desc" : sortDir,
    });
    
    // Only add filters if not in "All Candidates" mode
    if (!showAllCandidates) {
      params.set("min_score", $("minScore").value);
      if (role) params.set("role", role);
      if (location) params.set("location", location);
      if (experience_min) params.set("experience_min", experience_min);
      if (experience_max) params.set("experience_max", experience_max);
      if (industry) params.set("industry", industry);
      if (skills) params.set("skill", skills);
    }
    
    const data = await api("/api/candidates?" + params.toString());
    const rows = data.data || [];
    $("exportCount").textContent = data.meta?.total ?? 0;
    if (!rows.length) {
      $("candidateBody").innerHTML = `<tr><td colspan="7" style="text-align:center;padding:60px;color:var(--muted)">No candidates match these filters.</td></tr>`;
    } else {
      renderRows($("candidateBody"), rows, 7);
    }
    const meta = data.meta || { total: 0, current_page: 1, last_page: 1 };
    $("pageInfo").textContent = `${meta.total} candidates · page ${meta.current_page} of ${meta.last_page}`;
    const last = Math.max(1, meta.last_page);
    const start = Math.max(1, meta.current_page - 3);
    const buttons = [];
    for (let i = start; i <= Math.min(last, start + 6); i++) {
      buttons.push(`<button class="pagebtn ${i === meta.current_page ? "active" : ""}" data-p="${i}" type="button">${i}</button>`);
    }
    $("pages").innerHTML = buttons.join("");
    document.querySelectorAll(".pagebtn").forEach((b) => b.onclick = () => { page = +b.dataset.p; loadCandidates(); });
  }

  function renderRows(tbody, rows, cols) {
    tbody.innerHTML = rows.map((c) => `<tr data-id="${c.id}">
      <td><div style="display:flex;gap:9px;align-items:center"><span class="miniavatar">${initials(c.name)}</span><span class="name">${escapeHtml(c.name)}<span class="sub">${escapeHtml(c.email || c.source_name || "")}</span></span></div></td>
      <td>${escapeHtml(c.role || "")}</td>
      ${cols > 6 ? `<td>${escapeHtml(c.company || "")}</td>` : ""}
      <td>${escapeHtml(c.location || "")}</td>
      ${cols > 6 ? `<td>${c.experience ?? "—"} yrs</td>` : `<td>${c.experience ?? "—"} yrs</td>`}
      <td class="score">${c.score ?? 0}%</td>
      <td>${pill(c.status)}</td>
    </tr>`).join("");
    tbody.querySelectorAll("tr[data-id]").forEach((r) => r.onclick = () => openProfile(+r.dataset.id));
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }

  async function openProfile(id) {
    $("profile").innerHTML = `<p class="muted">Loading profile…</p>`;
    $("drawer").classList.add("open");
    try {
      const { candidate: c } = await api("/api/candidates/" + id);
      const saved = (c.shortlist_ids || []).length > 0;
      const edu = (c.education || []).map((e) => e.credential || e.institution).filter(Boolean).join(", ") || "—";
      const breakdown = c.score_breakdown || {};
      $("profile").innerHTML = `
        <div class="profilehero"><div class="bigavatar">${initials(c.name)}</div>
          <div><h2>${escapeHtml(c.name)}</h2><p>${escapeHtml(c.designation || "")} · ${escapeHtml(c.company || "")}</p>
          ${pill(c.verification_status === "verified" ? "verified" : "review")}
          ${c.is_synthetic ? `<span class="pill review">DEMO record</span>` : ""}</div>
        </div>
        <div class="scorebox"><div><small class="muted">Overall match</small><div class="meter"><i style="width:${c.match_score || 0}%"></i></div></div><div class="scorebig">${c.match_score || 0}%</div></div>
        <div class="profilegrid">
          <div class="infobox"><small>Location</small><b>${escapeHtml(c.location || "—")}</b></div>
          <div class="infobox"><small>Experience</small><b>${c.years_experience ?? "—"} years</b></div>
          <div class="infobox"><small>Education</small><b>${escapeHtml(edu)}</b></div>
          <div class="infobox"><small>Email</small><b>${escapeHtml(c.professional_email || "Not public")}</b></div>
          <div class="infobox"><small>Phone</small><b>${escapeHtml(c.phone || "Not public")}</b></div>
          <div class="infobox"><small>Source</small><b>${escapeHtml(c.source_name || "—")}</b></div>
        </div>
        ${c.source_snippet ? `<div class="infobox" style="margin-top:12px"><small>Public search excerpt · review before relying on it</small><p style="margin:6px 0 0;line-height:1.5">${escapeHtml(c.source_snippet)}</p></div>` : ""}
        <div class="sectionhead"><h2>Skills</h2></div>
        <div class="taglist">${(c.skills || []).map((s) => `<span class="tag">${escapeHtml(s)}</span>`).join("") || `<span class="muted">None listed</span>`}</div>
        <p class="muted" style="font-size:11px;margin-top:12px">${escapeHtml(breakdown.explanation || "")}</p>
        <div class="draweractions">
          <button class="primary" id="saveProfile" type="button">${saved ? "Remove shortlist" : "Add to shortlist"}</button>
          <button class="ghost" id="openSource" type="button">Open source</button>
        </div>
        <div class="btnrow">
          <button class="ghost" id="exportOne" type="button">Export</button>
          <button class="ghost" id="deleteOne" type="button">Delete</button>
        </div>
        <div class="timeline">
          ${(c.validation || []).map((v) => `<div><span class="dot"></span><span><b>${escapeHtml(v.type)}</b><br><span class="muted">${escapeHtml(v.status)} · ${escapeHtml(v.message || "")}</span></span></div>`).join("")}
        </div>`;
      $("saveProfile").onclick = async () => {
        setLoading($("saveProfile"), true, "Saving shortlist…");
        try {
          if (saved) await api("/api/candidates/" + id + "/shortlist", { method: "DELETE" });
          else await api("/api/candidates/" + id + "/shortlist", { method: "POST", body: {} });
          toast(saved ? "Removed from shortlist" : "Added to shortlist");
          openProfile(id);
          if (currentPage === "saved") loadShortlists();
        } catch (e) { toast(e.message); setLoading($("saveProfile"), false); }
      };
      $("openSource").onclick = () => { if (c.source_url || c.profile_url) window.open(c.source_url || c.profile_url, "_blank", "noopener"); else toast("No public source URL"); };
      $("exportOne").onclick = () => createExport("json", { ids: [id] });
      $("deleteOne").onclick = async () => {
        if (!confirm("Delete this candidate from your workspace?")) return;
        await api("/api/candidates/" + id, { method: "DELETE" });
        $("drawer").classList.remove("open");
        toast("Candidate deleted");
        loadCandidates();
      };
    } catch (e) {
      $("profile").innerHTML = `<p class="muted">${escapeHtml(e.message)}</p>`;
    }
  }

  async function loadAnalytics() {
    const data = await api("/api/analytics");
    $("aValidation").textContent = (data.validation_rate || 0) + "%";
    $("aDuplicates").textContent = (data.duplicate_rate || 0) + "%";
    $("aProcessing").textContent = data.average_processing_ms ? (data.average_processing_ms / 1000).toFixed(1) + "s" : "—";
    $("aExports").textContent = data.exports ?? 0;
    upsertChart("validationChart", "line", { labels: data.validation_quality.labels, datasets: [{ data: data.validation_quality.data, borderColor: "#30d158", tension: 0.4, pointRadius: 3 }] });
    const locLabels = Object.keys(data.locations || {});
    upsertChart("locationChart", "doughnut", {
      labels: locLabels.length ? locLabels : ["None"],
      datasets: [{ data: locLabels.length ? Object.values(data.locations) : [1], backgroundColor: ["#0a84ff", "#bf5af2", "#30d158", "#ffd60a"], borderWidth: 0 }],
    }, { responsive: true, maintainAspectRatio: false, cutout: "72%", plugins: { legend: { position: "bottom", labels: { color: "#a1a1a6", font: { size: 9 }, boxWidth: 8 } } } });
    upsertChart("volumeChart", "bar", { labels: data.volume.labels, datasets: [{ data: data.volume.data, backgroundColor: "rgba(191,90,242,.52)", borderRadius: 6 }] });
  }

  async function loadShortlists() {
    const data = await api("/api/shortlists");
    const lists = data.data || [];
    $("shortlistNav").innerHTML = lists.map((l) => `<button class="chip ${l.id === activeShortlistId ? "on" : ""}" data-sid="${l.id}" type="button">${escapeHtml(l.name)}</button>`).join("") || `<span class="muted">No shortlists yet</span>`;
    $("shortlistNav").querySelectorAll("[data-sid]").forEach((b) => b.onclick = () => { activeShortlistId = +b.dataset.sid; loadShortlists(); });
    const list = lists.find((l) => l.id === activeShortlistId) || lists[0];
    const rows = list ? list.candidates : [];
    $("savedCount").textContent = `${rows.length} saved`;
    $("savedBody").innerHTML = rows.map((c) => `<tr>
      <td class="name">${escapeHtml(c.name)}<span class="sub">${escapeHtml(c.location || "")}</span></td>
      <td>${escapeHtml(c.role || "")}</td><td>${escapeHtml(c.company || "")}</td>
      <td class="score">${c.score ?? 0}%</td>
      <td><button class="ghost" type="button" data-view="${c.id}">View</button></td>
    </tr>`).join("") || `<tr><td colspan="5" style="text-align:center;padding:60px;color:var(--muted)">Your shortlist is empty. Save strong candidates from the Mine workspace.</td></tr>`;
    $("savedBody").querySelectorAll("[data-view]").forEach((b) => b.onclick = (e) => { e.stopPropagation(); openProfile(+b.dataset.view); });
  }

  async function loadExportsPage() {
    const params = new URLSearchParams({ q: $("search").value.trim(), min_score: $("minScore").value, per_page: "1" });
    const data = await api("/api/candidates?" + params.toString());
    $("exportCount").textContent = data.meta?.total ?? 0;
  }

  async function loadSettings() {
    const data = await api("/api/settings");
    currentUser = data.user;
    applyUser(data.user);
    $("settingsName").value = data.user.name;
    $("settingsEmail").value = data.user.email;
    $("searchHistory").innerHTML = (data.searches || []).map((s) => `<div class="historyrow" data-restore='${escapeHtml(JSON.stringify(s.filters || {}))}' data-query="${escapeHtml(s.query)}"><span>${escapeHtml(s.query)} · ${s.result_count} results</span><span class="muted">${escapeHtml(String(s.created_at || "").slice(0, 16))}</span></div>`).join("") || `<p class="muted">No searches yet.</p>`;
    $("searchHistory").querySelectorAll(".historyrow").forEach((row) => row.onclick = () => {
      const filters = JSON.parse(row.dataset.restore || "{}");
      $("role").value = row.dataset.query || $("role").value;
      if (filters.location) $("location").value = filters.location;
      if (filters.experience) $("experience").value = filters.experience;
      if (filters.skills) $("skills").value = Array.isArray(filters.skills) ? filters.skills.join(", ") : filters.skills;
      if (filters.industry) $("industry").value = filters.industry;
      nav("mining");
    });
    $("exportHistory").innerHTML = (data.exports || []).map((e) => `<div class="historyrow"><span>${escapeHtml(e.format.toUpperCase())} · ${e.record_count} records · ${escapeHtml(e.status)}</span>${e.status === "completed" ? `<a class="footerlink" href="/api/exports/${e.id}/download">Download</a>` : ""}</div>`).join("") || `<p class="muted">No exports yet.</p>`;
    try {
      const g = await api("/api/google/status");
      $("googleStatus").textContent = !g.configured
        ? "Google Sheets is not configured. Add OAuth credentials in the environment."
        : (g.connected ? "Google account connected." : "Google Sheets is configured but not authorized yet.");
    } catch {
      $("googleStatus").textContent = "Unable to read Google Sheets status.";
    }
  }

  function applyUser(user) {
    currentUser = user;
    if (!user) return;
    const av = $("openSettings");
    if (av) av.textContent = user.initials || "NM";
  }

  async function createExport(format, extra = {}) {
    try {
      toast("Preparing export…");
      const body = Object.assign({
        format,
        q: $("search").value.trim(),
        min_score: +$("minScore").value,
      }, extra);
      if (currentPage === "saved" && activeShortlistId) body.shortlist_id = activeShortlistId;
      const { export: rec } = await api("/api/exports", { method: "POST", body });
      window.location.href = "/api/exports/" + rec.id + "/download";
      toast(`Exported ${rec.record_count} candidates`);
    } catch (e) {
      toast(e.message || "Export failed. Please try again.");
    }
  }

  const miningSteps = [
    "Initializing",
    "Searching sources",
    "Collecting candidates",
    "Normalizing records",
    "Validating profiles",
    "Checking emails",
    "Checking phone numbers",
    "Removing duplicates",
    "Calculating match scores",
    "Saving candidates",
    "Completed",
  ];

  function renderMineSteps(stage, progress) {
    $("scan").innerHTML = miningSteps.map((s, i) => `<div class="scanrow" id="scan${i}"><span>${s}</span><span>—</span></div>`).join("");
    const idx = Math.max(0, miningSteps.indexOf(stage));
    miningSteps.forEach((s, i) => {
      const row = $("scan" + i);
      if (!row) return;
      if (progress >= 100 || i < idx) row.innerHTML = `<span>${s}</span><span class="check">✓</span>`;
      else if (i === idx) row.classList.add("active");
    });
    $("mineTitle").textContent = stage || "Discovering candidate profiles";
    $("mineSub").textContent = `Backend job progress ${progress}%`;
    $("progress").style.width = progress + "%";
    $("pct").textContent = progress + "%";
    $("eta").textContent = progress >= 100 ? "Done" : "Running on queue";
  }

  async function startMining() {
    const btn = $("mine");
    setLoading(btn, true, "Starting mining…");
    try {
      const payload = {
        role: $("role").value.trim(),
        location: $("location").value,
        experience: $("experience").value,
        skills: $("skills").value,
        industry: $("industry") ? $("industry").value.trim() : "",
        candidate_type: document.querySelector("#types .chip.on")?.textContent || "Professional",
        min_score: +$("minScore").value,
      };
      if (!payload.role) throw new Error("Unable to start mining. Please check your search parameters.");
      const { job } = await api("/api/mining", { method: "POST", body: payload });
      miningJobId = job.id;
      $("mineOverlay").classList.add("open");
      renderMineSteps(job.stage || "Queued", job.progress || 0);
      pollMining();
    } catch (e) {
      toast(e.message || "Unable to start mining. Please check your search parameters.");
      setLoading(btn, false);
    }
  }

  function pollMining() {
    clearInterval(miningPoll);
    miningPoll = setInterval(async () => {
      if (!miningJobId) return;
      try {
        const { job } = await api("/api/mining/" + miningJobId);
        renderMineSteps(job.stage, job.progress || 0);
        if (["completed", "failed", "cancelled"].includes(job.status)) {
          clearInterval(miningPoll);
          miningPoll = null;
          setLoading($("mine"), false);
          $("mineOverlay").classList.remove("open");
          if (job.status === "completed") {
            toast(`Mining complete · ${job.processed} candidate records ready`);
            // Search configuration describes what to collect; it should not hide
            // the records just collected (many public profiles have unconfirmed roles).
            showAllCandidates = true;
            page = 1;
            nav("mining");
          } else if (job.status === "failed") {
            toast(job.error_message || "Mining source temporarily unavailable.");
          } else {
            toast("Mining job cancelled");
          }
        }
      } catch (e) {
        clearInterval(miningPoll);
        setLoading($("mine"), false);
        toast(e.message);
      }
    }, 900);
  }

  /* Auth */
  $("pwToggle").onclick = () => {
    const f = $("password"), show = f.type === "password";
    f.type = show ? "text" : "password";
    $("pwToggle").innerHTML = `<svg class="icon"><use href="#${show ? "i-eye-off" : "i-eye"}"/></svg>`;
  };
  $("demoFill").onclick = () => {
    $("email").value = "demo@novamine.ai";
    $("password").value = "demo1234";
    toast("Demo credentials filled");
  };
  $("showRegister").onclick = () => { $("loginForm").classList.add("hidden"); $("registerForm").classList.remove("hidden"); };
  $("showLogin").onclick = () => { $("registerForm").classList.add("hidden"); $("loginForm").classList.remove("hidden"); };

  $("loginForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const btn = $("login");
    setLoading(btn, true, "Signing in…");
    try {
      const data = await api("/api/auth/login", {
        method: "POST",
        body: { email: $("email").value.trim(), password: $("password").value, remember: $("remember").checked },
      });
      applyUser(data.user);
      showAuth(false);
      toast("Welcome back, " + data.user.name);
      await nav("dashboard");
    } catch (err) {
      $("emailErr").textContent = err.payload?.errors?.email?.[0] || (err.status === 422 ? err.message : "");
      $("passErr").textContent = err.payload?.errors?.password?.[0] || "";
      if (!$("emailErr").textContent && !$("passErr").textContent) toast(err.message);
    } finally {
      setLoading(btn, false);
    }
  });

  $("registerForm").addEventListener("submit", async (e) => {
    e.preventDefault();
    const btn = $("registerBtn");
    setLoading(btn, true, "Creating account…");
    try {
      const data = await api("/api/auth/register", {
        method: "POST",
        body: {
          name: $("regName").value.trim(),
          email: $("regEmail").value.trim(),
          password: $("regPassword").value,
          password_confirmation: $("regPasswordConfirm").value,
        },
      });
      applyUser(data.user);
      showAuth(false);
      toast("Account created");
      await nav("dashboard");
    } catch (err) {
      toast(err.message);
    } finally {
      setLoading(btn, false);
    }
  });

  async function logout() {
    await api("/api/auth/logout", { method: "POST" });
    currentUser = null;
    showAuth(true);
    toast("Signed out");
  }

  $("forgotLink").onclick = (e) => { e.preventDefault(); $("forgotOverlay").classList.add("open"); };
  $("closeForgot").onclick = () => $("forgotOverlay").classList.remove("open");
  $("forgotSubmit").onclick = async () => {
    setLoading($("forgotSubmit"), true, "Sending…");
    try {
      await api("/api/auth/forgot-password", { method: "POST", body: { email: $("forgotEmail").value.trim() || $("email").value.trim() } });
      toast("If that account exists, a reset link was sent.");
      $("forgotOverlay").classList.remove("open");
    } catch (e) { toast(e.message); }
    finally { setLoading($("forgotSubmit"), false); }
  };

  $("resetSubmit")?.addEventListener("click", async () => {
    const params = new URLSearchParams(location.search);
    try {
      await api("/api/auth/reset-password", {
        method: "POST",
        body: { token: params.get("token"), email: params.get("email"), password: $("resetPassword").value, password_confirmation: $("resetPasswordConfirm").value },
      });
      toast("Password updated. You can sign in now.");
      $("resetOverlay").classList.remove("open");
    } catch (e) { toast(e.message); }
  });

  $("openSettings").onclick = () => nav("settings");
  $("logoutSettings").onclick = logout;
  $("saveSettings").onclick = async () => {
    setLoading($("saveSettings"), true, "Saving…");
    try {
      const body = { name: $("settingsName").value.trim(), email: $("settingsEmail").value.trim() };
      if ($("settingsPassword").value) {
        body.current_password = $("settingsCurrentPassword").value;
        body.password = $("settingsPassword").value;
        body.password_confirmation = $("settingsPasswordConfirm").value;
      }
      const data = await api("/api/settings", { method: "PUT", body });
      applyUser(data.user);
      toast("Account updated");
    } catch (e) { toast(e.message); }
    finally { setLoading($("saveSettings"), false); }
  };

  $("googleConnect").onclick = async () => {
    setLoading($("googleConnect"), true, "Connecting to Google…");
    try {
      const data = await api("/api/google/connect", { method: "POST" });
      if (data.url) window.location.href = data.url;
    } catch (e) { toast(e.message); }
    finally { setLoading($("googleConnect"), false); }
  };
  $("googleExport").onclick = async () => {
    setLoading($("googleExport"), true, "Connecting to Google…");
    try {
      await api("/api/google/export", { method: "POST", body: {} });
      toast("Exported to Google Sheets");
    } catch (e) { toast(e.message); }
    finally { setLoading($("googleExport"), false); }
  };

  $("googleBtn").onclick = $("appleBtn").onclick = () => toast("Use email and password, or the seeded demo account.");
  $("refresh").onclick = () => nav(currentPage);
  $("theme").onclick = () => {
    const on = document.body.classList.toggle("reduced-fx");
    toast(on ? "Visual effects reduced for smoother performance" : "Visual effects restored");
  };
  $("mine").onclick = startMining;
  $("cancelMine").onclick = async () => {
    if (!miningJobId) return;
    await api("/api/mining/" + miningJobId + "/cancel", { method: "POST" });
  };
  $("exportBtn").onclick = () => nav("exports");
  $("filter").onclick = () => { 
    showAllCandidates = false; 
    page = 1; 
    loadCandidates(); 
    toast("Filters applied");
  };
  $("allCandidatesBtn").onclick = () => { 
    showAllCandidates = true; 
    page = 1; 
    loadCandidates(); 
    toast("Showing all candidates");
  };
  $("search").addEventListener("input", () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { page = 1; loadCandidates(); }, 280);
  });
  $("minScore").oninput = () => { $("scoreLabel").textContent = $("minScore").value + "%"; page = 1; if (currentPage === "mining") loadCandidates(); };
  $("closeDrawer").onclick = () => $("drawer").classList.remove("open");
  $("drawer").onclick = (e) => { if (e.target === $("drawer")) $("drawer").classList.remove("open"); };
  document.querySelectorAll(".table th[data-sort]").forEach((th) => th.onclick = () => {
    sortKey = th.dataset.sort;
    sortDir = sortDir === "asc" ? "desc" : "asc";
    loadCandidates();
  });
  document.querySelectorAll("#types .chip").forEach((c) => c.onclick = () => {
    document.querySelectorAll("#types .chip").forEach((x) => x.classList.remove("on"));
    c.classList.add("on");
  });
  
  // Search configuration fields are applied when a new mining job starts.
  // Use the explicit Filters button to apply them to the displayed candidate list.
  document.querySelectorAll("[data-export]").forEach((b) => b.onclick = () => createExport(b.dataset.export));
  $("createShortlist").onclick = async () => {
    const name = $("shortlistName").value.trim();
    if (!name) return toast("Enter a shortlist name");
    await api("/api/shortlists", { method: "POST", body: { name } });
    $("shortlistName").value = "";
    toast("Shortlist created");
    loadShortlists();
  };

  function openOverlay(id) { $(id).classList.add("open"); }
  function closeOverlay(id) { $(id).classList.remove("open"); }
  $("openPrivacy").onclick = () => openOverlay("privacyOverlay");
  $("closePrivacy").onclick = () => closeOverlay("privacyOverlay");
  $("privacyOverlay").onclick = (e) => { if (e.target === $("privacyOverlay")) closeOverlay("privacyOverlay"); };
  $("openTerms").onclick = () => openOverlay("termsOverlay");
  $("closeTerms").onclick = () => closeOverlay("termsOverlay");
  $("termsOverlay").onclick = (e) => { if (e.target === $("termsOverlay")) closeOverlay("termsOverlay"); };

  // CSV Import Modal
  $("importBtn").onclick = () => $("importModal").classList.remove("hidden");
  $("closeImportModal").onclick = () => {
    $("importModal").classList.add("hidden");
    $("importResults").classList.add("hidden");
    $("csvFile").value = "";
  };
  $("downloadTemplate").onclick = async () => {
    try {
      const res = await fetch("/api/import/template");
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "novamine_import_template.csv";
      a.click();
      URL.revokeObjectURL(url);
    } catch (e) {
      toast("Failed to download template");
    }
  };
  $("importSubmit").onclick = async () => {
    const fileInput = $("csvFile");
    if (!fileInput.files.length) {
      toast("Please select a CSV file");
      return;
    }
    const file = fileInput.files[0];
    if (file.size > 10 * 1024 * 1024) {
      toast("File size exceeds 10MB limit");
      return;
    }
    const formData = new FormData();
    formData.append("csv_file", file);
    try {
      $("importSubmit").disabled = true;
      $("importSubmit").textContent = "Importing...";
      const result = await api("/api/import", { method: "POST", body: formData });
      $("importTotal").textContent = result.results.total_rows;
      $("importImported").textContent = result.results.imported;
      $("importDuplicates").textContent = result.results.duplicates;
      $("importInvalid").textContent = result.results.invalid;
      const errorsDiv = $("importErrors");
      errorsDiv.innerHTML = "";
      if (result.results.errors.length > 0) {
        result.results.errors.forEach(err => {
          const div = document.createElement("div");
          div.textContent = `Row ${err.row}: ${err.message}`;
          errorsDiv.appendChild(div);
        });
      } else {
        errorsDiv.innerHTML = "<div style='color:var(--green)'>No errors</div>";
      }
      $("importResults").classList.remove("hidden");
      toast(result.message);
      showAllCandidates = true;
      page = 1;
      loadCandidates();
    } catch (e) {
      toast(e.message || "Import failed");
    } finally {
      $("importSubmit").disabled = false;
      $("importSubmit").textContent = "Import CSV";
    }
  };
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeOverlay("privacyOverlay");
      closeOverlay("termsOverlay");
      $("drawer").classList.remove("open");
      $("importModal").classList.add("hidden");
    }
  });

  if (matchMedia("(hover:hover) and (pointer:fine)").matches) {
    document.addEventListener("pointermove", (e) => {
      const el = e.target.closest(".glass");
      if (!el) return;
      const r = el.getBoundingClientRect();
      el.style.setProperty("--mx", (e.clientX - r.left) + "px");
      el.style.setProperty("--my", (e.clientY - r.top) + "px");
    }, { passive: true });
  }
  window.addEventListener("resize", () => requestAnimationFrame(updateThumbs), { passive: true });

  async function boot() {
    Chart.defaults.devicePixelRatio = Math.min(window.devicePixelRatio || 1, 2);
    if (reducedMotion) Chart.defaults.animation = false;
    const params = new URLSearchParams(location.search);
    if (params.get("token") && params.get("email")) $("resetOverlay").classList.add("open");
    if (params.get("google") === "connected") toast("Google Sheets connected");
    if (params.get("google") === "error") toast("Google Sheets authorization required.");
    try {
      const me = await api("/api/auth/me");
      if (me.user) {
        applyUser(me.user);
        showAuth(false);
        await nav("dashboard");
      } else {
        showAuth(true);
      }
    } catch {
      showAuth(true);
    }
    requestAnimationFrame(() => requestAnimationFrame(updateThumbs));
  }

  boot();
})();
