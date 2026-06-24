<?php
if (!function_exists('redirect')) require_once __DIR__ . '/../config.php';
/**
 * auth.php — Role-Based Access Control
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['role'])) {
    redirect('login.php');
    
}

// ── ROLE HELPERS ─────────────────────────────────────────────

/** Is the current user an admin? */
function is_admin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

/** Is the current user a regular user (non-admin)? */
function is_viewer(): bool {
    return ($_SESSION['role'] ?? '') === 'user';
}

/**
 * Can this user add / edit / delete records?
 * Admin only. Regular users have read access plus the ability
 * to submit requests/documents, but cannot modify CSI data.
 */
function can_edit(): bool {
    return is_admin();
}

/**
 * Can this user view financial data (amounts, funding)?
 * Admin only.
 */
function can_view_financials(): bool {
    return is_admin();
}

/** Get the current user's display name */
function current_user_name(): string {
    return htmlspecialchars($_SESSION['name'] ?? 'User');
}

/** Get the current user's role label */
function current_user_role(): string {
    return is_admin() ? 'Administrator' : 'User';
}

/** Get initials from name */
function current_user_initials(): string {
    $name  = $_SESSION['name'] ?? 'U';
    $parts = explode(' ', trim($name));
    $init  = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}

/**
 * Hard-block a page to admins only.
 * Use at the top of admin-only pages: require_admin_role();
 */
function require_admin_role(): void {
    if (!is_admin()) {
        redirect('dashboard.php?error=access_denied');
        
    }
}

/**
 * Render a permission-gated button.
 * If allowed  → renders a real button with your onclick.
 * If blocked  → renders a greyed locked button that shows a toast.
 */
function permission_btn(string $label, bool $allowed, string $icon = '', string $classes = 'btn btn-secondary', string $onclick = ''): void {
    $ic = $icon ? "<i class=\"ti {$icon}\"></i> " : '';
    if ($allowed) {
        $oc = $onclick ? "onclick=\"{$onclick}\"" : '';
        echo "<button class=\"{$classes}\" {$oc}>{$ic}{$label}</button>";
    } else {
        echo "<button class=\"{$classes} btn-locked\" onclick=\"showAccessDenied()\" title=\"Admin only\">{$ic}{$label} <i class=\"ti ti-lock\" style=\"font-size:10px;opacity:.5\"></i></button>";
    }
}

/**
 * Render a table action icon button (edit / delete / view).
 */
function action_btn(string $icon, string $title, bool $allowed, string $onclick = '', string $extra_class = ''): string {
    if ($allowed) {
        return "<button class=\"table-action-btn {$extra_class}\" title=\"{$title}\" onclick=\"{$onclick}\"><i class=\"ti {$icon}\"></i></button>";
    }
    return "<button class=\"table-action-btn btn-locked\" title=\"Admin only\" onclick=\"showAccessDenied()\"><i class=\"ti {$icon}\"></i></button>";
}
?>

<!-- Access Denied Toast — injected once, used everywhere -->
<div id="access-denied-toast" style="
  position:fixed;bottom:28px;right:28px;z-index:9999;
  background:#1a1f2e;color:white;
  padding:14px 20px;border-radius:12px;
  font-family:'Poppins',sans-serif;font-size:13px;
  border-left:3px solid #e53e3e;
  display:none;align-items:center;gap:12px;
  opacity:0;transform:translateY(16px);
  transition:opacity .25s ease,transform .25s ease;
  max-width:300px;pointer-events:none;">
  <i class="ti ti-shield-lock" style="color:#e53e3e;font-size:20px;flex-shrink:0"></i>
  <div>
    <div style="font-weight:600;font-size:13px">Access Denied</div>
    <div style="font-size:11.5px;color:rgba(255,255,255,.55);margin-top:2px">This action requires Admin access.</div>
  </div>
</div>
<script>
function showAccessDenied() {
  const t = document.getElementById('access-denied-toast');
  t.style.display = 'flex';
  requestAnimationFrame(() => { t.style.opacity='1'; t.style.transform='translateY(0)'; });
  setTimeout(() => {
    t.style.opacity='0'; t.style.transform='translateY(16px)';
    setTimeout(() => t.style.display='none', 260);
  }, 3000);
}
</script>