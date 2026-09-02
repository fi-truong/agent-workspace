<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>@yield('title', 'AI+ Admin - LSTS')</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- Fonts are self-hosted via @font-face in ai-plus.css (loaded by admin.css) -->
<link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@stack('styles')
<meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="admin-body">

<div class="admin-layout">
  <!-- Sidebar -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
      <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
        <span class="brand-icon">⚙️</span>
        <span class="brand-text">AI+ Admin</span>
      </a>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section">
        <span class="nav-section-title">Overview</span>
        <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
          <span class="nav-icon">📊</span>
          <span>Dashboard</span>
        </a>
      </div>

      <div class="nav-section">
        <span class="nav-section-title">Content</span>
        <a href="{{ route('admin.prompts.index') }}" class="nav-item {{ request()->routeIs('admin.prompts.*') ? 'active' : '' }}">
          <span class="nav-icon">📝</span>
          <span>Prompt Library</span>
        </a>
        <a href="{{ route('admin.templates.index') }}" class="nav-item {{ request()->routeIs('admin.templates.*') ? 'active' : '' }}">
          <span class="nav-icon">📋</span>
          <span>Agent Templates</span>
        </a>
        <a href="{{ route('admin.showcases.index') }}" class="nav-item {{ request()->routeIs('admin.showcases.*') ? 'active' : '' }}">
          <span class="nav-icon">🌟</span>
          <span>Showcase</span>
        </a>
        <a href="{{ route('admin.faqs.index') }}" class="nav-item {{ request()->routeIs('admin.faqs.*') ? 'active' : '' }}">
          <span class="nav-icon">❓</span>
          <span>FAQ</span>
        </a>
      </div>

      <div class="nav-section">
        <span class="nav-section-title">Support</span>
        <a href="{{ route('admin.tickets.index') }}" class="nav-item {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}">
          <span class="nav-icon">🎫</span>
          <span>Support Tickets</span>
        </a>
      </div>

      <div class="nav-section">
        <span class="nav-section-title">System</span>
        <a href="{{ route('admin.users.index') }}" class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
          <span class="nav-icon">👥</span>
          <span>Users & Roles</span>
        </a>
      </div>
    </nav>

    <div class="sidebar-footer">
      <a href="{{ route('ai-plus.index') }}" class="nav-item back-to-aiplus">
        <span class="nav-icon">←</span>
        <span>Back to AI+</span>
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <div class="admin-main">
    <!-- Topbar -->
    <header class="admin-topbar">
      <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="3" y1="12" x2="21" y2="12"></line>
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
      </button>

      <div class="topbar-title">
        <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
        @if(isset($pageDescription))
        <p class="page-desc">{{ $pageDescription }}</p>
        @endif
      </div>

      <div class="topbar-actions">
        @yield('topbar-actions')
        <div class="user-menu">
          <span class="user-name">{{ auth()->user()->name ?? 'Admin' }}</span>
          <span class="user-role">{{ auth()->user()->role ?? 'Administrator' }}</span>
        </div>
      </div>
    </header>

    <!-- Content -->
    <main class="admin-content">
      <div class="admin-wrap">
        @yield('content')
      </div>
    </main>
  </div>

  <!-- Mobile sidebar overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
</div>

@stack('scripts')
<script>
// Sidebar toggle for mobile
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('adminSidebar');
  const overlay = document.getElementById('sidebarOverlay');

  if (toggle && sidebar && overlay) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('open');
    });
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
    });
  }
});
</script>
</body>
</html>