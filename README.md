# CSI Hub — Setup Guide
## Research Unlimited | VS Code + XAMPP

---

## STEP 1 — Install the tools you need

Download and install these (all free):

| Tool | Link | What it does |
|------|------|--------------|
| **VS Code** | https://code.visualstudio.com | Your code editor |
| **XAMPP** | https://www.apachefriends.org | Runs PHP + MySQL locally |
| **Git** (optional) | https://git-scm.com | Version control |

---

## STEP 2 — Install VS Code Extensions

Open VS Code → press `Ctrl + Shift + X` → search and install:

1. **PHP Intelephense** — PHP autocomplete & error detection
2. **Live Server** — preview HTML with auto-refresh
3. **Prettier** — auto-format your code on save
4. **Path Intellisense** — autocomplete file paths
5. **Auto Rename Tag** — rename HTML open/close tags together
6. **Material Icon Theme** — better file icons (optional but nice)
7. **MySQL** by cweijan — manage your database inside VS Code

---

## STEP 3 — Set up VS Code settings

Press `Ctrl + ,` → click the `{}` icon (top right) to open JSON settings.
Paste this to auto-format on save:

```json
{
  "editor.formatOnSave": true,
  "editor.defaultFormatter": "esbenp.prettier-vscode",
  "editor.tabSize": 2,
  "emmet.includeLanguages": { "php": "html" },
  "files.autoSave": "afterDelay"
}
```

---

## STEP 4 — Put project in XAMPP

1. Open XAMPP Control Panel → click **Start** on Apache and MySQL
2. Open File Explorer → go to: `C:\xampp\htdocs\`
3. Copy the `csi-hub` folder into `htdocs`

Your path should look like:
```
C:\xampp\htdocs\csi-hub\
    index.php
    dashboard.php
    partnerships.php
    ...
```

---

## STEP 5 — Create the database

1. Go to: http://localhost/phpmyadmin in your browser
2. Click **New** (left sidebar)
3. Database name: `csi_hub` → Collation: `utf8mb4_unicode_ci` → click **Create**
4. Click the `csi_hub` database → click **Import** tab
5. Click **Choose File** → select `csi-hub/api/schema.sql`
6. Click **Go** → your tables and sample data are created!

---

## STEP 6 — Open project in VS Code

Two ways:

**Option A:** In VS Code → `File → Open Folder` → select `C:\xampp\htdocs\csi-hub`

**Option B:** Right-click the `csi-hub` folder in File Explorer → "Open with Code"

---

## STEP 7 — Run the project

Open your browser and go to:
```
http://localhost/csi-hub/
```

You should see the Dashboard page! 🎉

---

## FILE STRUCTURE (what each file does)

```
csi-hub/
│
├── index.php           ← Entry point → redirects to dashboard
├── dashboard.php       ← Overview page with stats & activity
├── partnerships.php    ← Cards & timeline view (main page)
├── schools.php         ← Schools directory table
├── companies.php       ← Company partner cards
├── reports.php         ← Charts & analytics
│
├── includes/
│   ├── header.php      ← Top nav bar (add to top of every page)
│   ├── sidebar.php     ← Left sidebar (add to every page)
│   ├── footer.php      ← Closes layout + loads JS (add last)
│   └── db.php          ← MySQL database connection
│
├── assets/
│   ├── css/
│   │   ├── main.css    ← Variables, buttons, layout
│   │   ├── sidebar.css ← Sidebar styles
│   │   ├── cards.css   ← Partnership cards
│   │   ├── modal.css   ← Form modal
│   │   └── tables.css  ← Data tables & timeline
│   │
│   └── js/
│       ├── main.js         ← Toast, utilities (every page)
│       ├── modal.js        ← Open/close/save modal
│       ├── partnerships.js ← Card render, filter, timeline
│       └── charts.js       ← Bar chart, province chart
│
└── api/
    ├── schema.sql              ← Run this first to create DB
    ├── get_partnerships.php    ← GET all partnerships as JSON
    └── save_partnership.php    ← POST new partnership to DB
```

---

## HOW TO ADD A NEW PAGE

1. Create `yourpage.php` in the root folder
2. Paste this template at the top:

```php
<?php
$active_page = 'yourpage'; // matches sidebar key
include 'includes/header.php';
?>

<div class="layout">
<?php include 'includes/sidebar.php'; ?>

<main class="main">

  <!-- YOUR PAGE CONTENT HERE -->
  <div class="page-header">
    <div>
      <h1>Your Page Title</h1>
      <p>Subtitle here</p>
    </div>
  </div>

</main>
</div>

<?php include 'includes/footer.php'; ?>
```

3. Add your page to the `$sidebar_items` array in `includes/sidebar.php`

---

## CONNECT TO REAL DATABASE (when ready)

In `partnerships.js`, uncomment the fetch block in `savePartnership()`:

```js
fetch('api/save_partnership.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(newPartnership)
})
.then(res => res.json())
.then(data => console.log('Saved:', data));
```

In `partnerships.php`, replace the static `$partnerships` array with:

```php
require_once 'includes/db.php';
$stmt = $pdo->query("SELECT * FROM partnerships ...");
$partnerships = $stmt->fetchAll();
```

---

## DEPLOY TO LIVE INTERNET

When you're ready to go live:

| Option | Cost | How |
|--------|------|-----|
| **Netlify** (static) | Free | netlify.com/drop → drag folder |
| **InfinityFree** | Free | Supports PHP + MySQL |
| **Hostinger** | ~R60/month | Full PHP + MySQL hosting |
| **Render.com** | Free tier | PHP supported |

For PHP + MySQL hosting, **Hostinger** is the easiest and cheapest for South Africa.

---

## USEFUL VS CODE SHORTCUTS

| Shortcut | Action |
|----------|--------|
| `Ctrl + P` | Quick open any file |
| `Ctrl + Shift + P` | Command palette |
| `Ctrl + /` | Comment/uncomment line |
| `Alt + Shift + F` | Format document |
| `Ctrl + `` ` `` | Open terminal inside VS Code |
| `Ctrl + D` | Select next occurrence of word |
| `Alt + ↑↓` | Move line up/down |

---

*Built for Research Unlimited CSI Coordination Hub*
*researchunlimitedsa.co.za*
