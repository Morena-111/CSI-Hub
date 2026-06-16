/**
 * partnerships.js
 */

"use strict";

// ─── SAMPLE DATA ────────────────────────────────────
const partnerships = [
  {
    id: 1,
    company: "TechCorp SA",
    school: "Diepsloot Secondary School",
    amount: "R800k",
    focus: "STEM",
    focusCls: "stem",
    start: "2026-01-01",
    end: "2026-12-31",
    status: "active",
    desc: "Lab equipment and coding workshops for Grade 10–12 learners",
  },
  {
    id: 2,
    company: "TechCorp SA",
    school: "Umlazi Combined School",
    amount: "R600k",
    focus: "Digital Skills",
    focusCls: "digital",
    start: "2026-03-01",
    end: "2027-02-28",
    status: "active",
    desc: "Tablet programme and teacher training in digital literacy",
  },
  {
    id: 3,
    company: "Absa Foundation",
    school: "Tshepiso Primary School",
    amount: "R450k",
    focus: "Literacy",
    focusCls: "literacy",
    start: "2025-07-01",
    end: "2026-06-30",
    status: "active",
    desc: "Reading programme and library stocking for Grades 1–7",
  },
  {
    id: 4,
    company: "Absa Foundation",
    school: "Soweto Arts & Culture School",
    amount: "R300k",
    focus: "Arts",
    focusCls: "arts",
    start: "2024-01-01",
    end: "2025-12-31",
    status: "completed",
    desc: "Creative arts bursaries and studio equipment",
  },
  {
    id: 5,
    company: "Sasol Foundation",
    school: "Secunda Technical High",
    amount: "R550k",
    focus: "Science",
    focusCls: "science",
    start: "2026-02-01",
    end: "2027-01-31",
    status: "active",
    desc: "Science lab upgrade and bursaries for technical learners",
  },
  {
    id: 6,
    company: "Nedbank Foundation",
    school: "Cape Flats STEM Academy",
    amount: "R300k",
    focus: "STEM",
    focusCls: "stem",
    start: "2026-07-01",
    end: "2027-06-30",
    status: "pending",
    desc: "Proposed robotics and coding curriculum development",
  },
];

// ─── RENDER PARTNERSHIP CARDS ────────────────────────
function renderCards(data) {
  const grid = document.getElementById("cards-grid");
  if (!grid) return;

  if (data.length === 0) {
    grid.innerHTML = `
      <div class="empty-state">
        <i class="ti ti-search-off"></i>
        <p>No partnerships match your search.</p>
      </div>`;
    return;
  }

  grid.innerHTML = data
    .map((p) => {
      const pct = p.status === "completed" ? 100 : calcProgress(p.start, p.end);
      return `
    <div class="pcard" onclick="viewPartnership(${p.id})">
      <div class="pcard-head">
        <div class="pcard-company">
          <div class="company-logo" style="${logoStyle(p.company)}">
            ${companyInitials(p.company)}
          </div>
          <div>
            <div class="pcard-name">${p.company}</div>
            <div class="pcard-school">→ ${p.school}</div>
          </div>
        </div>
        <span class="status-badge ${p.status}">${p.status}</span>
      </div>

      <div class="pcard-meta">
        <i class="ti ti-report-money" style="font-size:15px;color:var(--text-muted)"></i>
        <span class="pcard-amount">${p.amount}</span>
        <span class="focus-tag ${p.focusCls}">${p.focus}</span>
      </div>

      <div class="pcard-dates">
        <i class="ti ti-calendar" style="font-size:14px"></i>
        ${p.start} → ${p.end}
        <span style="margin-left:auto;font-size:10px;font-weight:700;color:var(--orange)">${pct}%</span>
      </div>

      <div class="progress-bar">
        <div class="progress-fill" style="width:${pct}%"></div>
      </div>
      <div class="progress-ends">
        <span>${p.start}</span>
        <span>${p.end}</span>
      </div>

      <div class="pcard-desc">${p.desc}</div>
    </div>`;
    })
    .join("");
}

// ─── FILTER CARDS ON SEARCH ──────────────────────────
function filterCards() {
  const query =
    document.getElementById("search-input")?.value.toLowerCase() || "";
  const filtered = partnerships.filter(
    (p) =>
      p.company.toLowerCase().includes(query) ||
      p.school.toLowerCase().includes(query) ||
      p.focus.toLowerCase().includes(query) ||
      p.status.toLowerCase().includes(query),
  );
  renderCards(filtered);
}

// ─── TIMELINE VIEW ───────────────────────────────────
function renderTimeline(data) {
  const container = document.getElementById("timeline-content");
  if (!container) return;

  const minDate = new Date("2024-01-01");
  const maxDate = new Date("2027-12-31");
  const totalMs = maxDate - minDate;
  const barColors = ["orange", "navy", "teal", "purple", "teal", "orange"];

  container.innerHTML = data
    .map((p, i) => {
      const s = new Date(p.start);
      const e = new Date(p.end);
      const left = Math.max(0, ((s - minDate) / totalMs) * 100);
      const width = Math.min(100 - left, ((e - s) / totalMs) * 100);
      const shortSchool = p.school.split(" ").slice(0, 2).join(" ");

      return `
    <div class="tl-row">
      <div>
        <div class="tl-label">${p.company}</div>
        <div class="tl-sub">${shortSchool}</div>
      </div>
      <div class="tl-track">
        <div class="tl-bar ${barColors[i]}" style="left:${left.toFixed(1)}%;width:${width.toFixed(1)}%">
          ${p.amount} · ${p.focus}
        </div>
      </div>
    </div>`;
    })
    .join("");
}

// ─── SWITCH BETWEEN CARDS / TIMELINE ─────────────────
function setView(view) {
  const cardsView = document.getElementById("cards-view");
  const timelineView = document.getElementById("timeline-view");
  const btnCards = document.getElementById("btn-cards");
  const btnTimeline = document.getElementById("btn-timeline");

  if (view === "cards") {
    cardsView.style.display = "block";
    timelineView.style.display = "none";
    btnCards.classList.add("active");
    btnTimeline.classList.remove("active");
  } else {
    cardsView.style.display = "none";
    timelineView.style.display = "block";
    btnCards.classList.remove("active");
    btnTimeline.classList.add("active");
    renderTimeline(partnerships);
  }
}

// ─── VIEW SINGLE PARTNERSHIP ─────────────────────────
function viewPartnership(id) {
  // TODO: open a detail modal or navigate to detail page
  console.log("View partnership:", id);
}

// ─── INIT ────────────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  renderCards(partnerships);
});
