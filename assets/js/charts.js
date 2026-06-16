/**
 * charts.js
 */

"use strict";

// ─── BAR CHART ───────────────────────────────────────
/**
 * Render a simple bar chart into a container element.
 * @param {string} containerId - ID of the container div
 * @param {Array}  data        - [{label, value, color}]
 * @param {number} maxValue    - Value that = 100% height
 */
function renderBarChart(containerId, data, maxValue) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = data
    .map((bar) => {
      const heightPct = ((bar.value / maxValue) * 100).toFixed(0);
      const colorClass = bar.projected ? "navy" : "";
      return `
    <div class="bar-group">
      <div class="bar-val">${bar.label_value}</div>
      <div class="bar ${colorClass}" style="height:${heightPct}%"></div>
      <div class="bar-label">${bar.label}</div>
    </div>`;
    })
    .join("");
}

// ─── PROVINCE PROGRESS BARS ──────────────────────────
function renderProvinceChart(containerId, data) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = data
    .map(
      (item) => `
    <div>
      <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
        <span style="font-weight:500">${item.province}</span>
        <span style="font-weight:700;color:${item.color || "var(--orange)"}">${item.count}</span>
      </div>
      <div class="progress-bar">
        <div class="progress-fill" style="width:${item.pct}%;background:${item.color || "var(--orange)"}"></div>
      </div>
    </div>
  `,
    )
    .join("");
}

// ─── INIT CHARTS ON REPORTS PAGE ─────────────────────
document.addEventListener("DOMContentLoaded", () => {
  // Bar chart — Funding by Quarter
  renderBarChart(
    "bar-chart-container",
    [
      { label: "Q1", label_value: "450k", value: 450, projected: false },
      { label: "Q2", label_value: "620k", value: 620, projected: false },
      { label: "Q3", label_value: "580k", value: 580, projected: false },
      { label: "Q4 (proj)", label_value: "650k", value: 650, projected: true },
    ],
    650,
  );

  // Province chart on dashboard
  renderProvinceChart("province-chart", [
    { province: "Gauteng", count: 5, pct: 83, color: "var(--orange)" },
    { province: "KwaZulu-Natal", count: 3, pct: 50, color: "var(--purple)" },
    { province: "Western Cape", count: 2, pct: 33, color: "var(--gold)" },
    { province: "Mpumalanga", count: 2, pct: 33, color: "var(--teal)" },
  ]);
});
