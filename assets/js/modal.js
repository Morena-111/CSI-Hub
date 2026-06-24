/**
 * modal.js
 */

"use strict";

// ─── OPEN MODAL ─────────────────────────────────────
function openModal() {
  const overlay = document.getElementById("modal-overlay");
  if (!overlay) return;

  // Set default dates
  const today = new Date();
  const nextYear = new Date(today);
  nextYear.setFullYear(today.getFullYear() + 1);

  const fStart = document.getElementById("f-start");
  const fEnd = document.getElementById("f-end");

  if (fStart) fStart.value = today.toISOString().split("T")[0];
  if (fEnd) fEnd.value = nextYear.toISOString().split("T")[0];

  overlay.classList.add("open");
}

// ─── CLOSE MODAL ────────────────────────────────────
function closeModal() {
  const overlay = document.getElementById("modal-overlay");
  if (overlay) overlay.classList.remove("open");
}

// ─── CLOSE IF CLICKING OUTSIDE ──────────────────────
function closeModalOutside(event) {
  if (event.target === document.getElementById("modal-overlay")) {
    closeModal();
  }
}

// ─── SAVE PARTNERSHIP ───────────────────────────────
function savePartnership() {
  // Grab form values
  const company = document.getElementById("f-company")?.value;
  const school = document.getElementById("f-school")?.value;
  const amount = document.getElementById("f-amount")?.value || "0";
  const focus = document.getElementById("f-focus")?.value;
  const start = document.getElementById("f-start")?.value;
  const end = document.getElementById("f-end")?.value;
  const desc =
    document.getElementById("f-desc")?.value || "Details to be confirmed.";
  const status = document.getElementById("f-status")?.value;

  // Basic validation
  if (!company || !school || !start || !end) {
    alert("Please fill in all required fields.");
    return;
  }

  // Format amount to Rand
  const cleanAmount = parseInt(amount.replace(/\D/g, "")) || 0;
  const formatted = "R" + cleanAmount.toLocaleString();

  // Build new partnership object
  const newPartnership = {
    id: Date.now(),
    company,
    school,
    amount: formatted,
    focus,
    focusCls: focusClassMap[focus] || "stem",
    start,
    end,
    status,
    desc,
  };

  // Add to partnerships array (defined in partnerships.js)
  if (typeof partnerships !== "undefined") {
    partnerships.push(newPartnership);
    renderCards(partnerships);

    // Update stat counter
    const statTotal = document.getElementById("stat-total");
    if (statTotal) statTotal.textContent = partnerships.length;
  }

  // ── In production: send to PHP API ──────────────
  // fetch('api/save_partnership.php', {
  //   method: 'POST',
  //   headers: { 'Content-Type': 'application/json' },
  //   body: JSON.stringify(newPartnership)
  // })
  // .then(res => res.json())
  // .then(data => console.log('Saved:', data));

  closeModal();
  showToast(
    "Partnership added: " +
      company +
      " → " +
      school.split(" ").slice(0, 2).join(" "),
  );
}
