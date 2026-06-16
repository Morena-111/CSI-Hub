/**
 * main.js
 */

"use strict";

// ─── TOAST NOTIFICATION ─────────────────────────────
/**
 * Show a toast message at bottom-right of screen.
 * @param {string} message - Text to show
 * @param {number} duration - How long to show (ms), default 3500
 */
function showToast(message, duration = 3500) {
  const toast = document.getElementById("toast");
  const toastText = document.getElementById("toast-text");

  if (!toast || !toastText) return;

  toastText.textContent = message;
  toast.classList.add("show");

  setTimeout(() => toast.classList.remove("show"), duration);
}

// ─── FORMAT CURRENCY ────────────────────────────────
/**
 * Format a number as South African Rand.
 * @param {number} amount
 * @returns {string} e.g. "R 1,200,000"
 */
function formatRand(amount) {
  return "R " + parseInt(amount).toLocaleString("en-ZA");
}

// ─── CALCULATE PROGRESS ─────────────────────────────
/**
 * Calculate % progress between start and end dates.
 * @param {string} start - ISO date string
 * @param {string} end   - ISO date string
 * @returns {number} 0–100
 */
function calcProgress(start, end) {
  const s = new Date(start);
  const e = new Date(end);
  const now = new Date();
  const total = e - s;
  const elapsed = Math.min(Math.max(now - s, 0), total);
  return Math.round((elapsed / total) * 100);
}

// ─── COMPANY LOGO INITIALS ──────────────────────────
function companyInitials(name) {
  return name
    .split(" ")
    .map((w) => w[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
}

// ─── COMPANY LOGO STYLE ─────────────────────────────
function logoStyle(company) {
  const styles = {
    "TechCorp SA": "background:var(--navy);color:white",
    "Absa Foundation": "background:#fff8ec;color:#9a6700",
    "Sasol Foundation": "background:#f0eeff;color:#7c6af5",
    "Nedbank Foundation": "background:#fde9f1;color:#f06292",
  };
  return styles[company] || "background:var(--surface);color:var(--navy)";
}

// ─── FOCUS CLASS MAP ────────────────────────────────
const focusClassMap = {
  STEM: "stem",
  "Digital Skills": "digital",
  Literacy: "literacy",
  "Arts & Culture": "arts",
  Science: "science",
};

// ─── ON PAGE LOAD ────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  console.log("CSI Hub loaded ✓");
});
