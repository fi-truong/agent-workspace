<svg class="lsts-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
  @switch($name)
    @case('chat')
      <path d="M5 5h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-4 3v-3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/>
      @break
    @case('agents')
      <path d="m12 3 9 5-9 5-9-5 9-5Zm9 9-9 5-9-5m18 4-9 5-9-5"/>
      @break
    @case('image')
      <rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8" cy="9" r="1.5"/><path d="m3 17 5-4 4 3 4-5 5 5"/>
      @break
    @case('edit')
      <path d="m4 16-1 5 5-1L20 8l-4-4L4 16Zm9-9 4 4m-1-7 2-2 4 4-2 2"/>
      @break
    @case('delete')
      <path d="M3 6h18M5 6l1 15h12l1-15M9 6V3h6v3M10 10v7m4-7v7"/>
      @break
    @case('attach')
      <path d="m9 13 6-6a3 3 0 0 1 4 4l-9 9a4 4 0 0 1-6-6l10-10a5 5 0 0 1 7 7l-8 8"/>
      @break
    @case('download')
      <path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>
      @break
    @case('move')
      <path d="M4 8h16m-4-4 4 4-4 4M20 16H4m4-4-4 4 4 4"/>
      @break
    @case('users')
      <path d="M15 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-4m-3-4a4 4 0 0 0 0-8"/><circle cx="8.5" cy="7" r="4"/>
      @break
    @case('book')
      <path d="M12 6a8 8 0 0 0-9-2v15a8 8 0 0 1 9 2 8 8 0 0 1 9-2V4a8 8 0 0 0-9 2Zm0 0v15"/>
      @break
    @case('usage')
      <path d="M4 20h16M7 17v-5m5 5V8m5 9V4"/>
      @break
    @case('policy')
      <path d="M12 22C6 19 3 16 3 10V5l9-3 9 3v5c0 6-3 9-9 12Z"/><path d="m8 12 3 3 5-6"/>
      @break
    @case('search')
      <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>
      @break
    @case('support')
      <circle cx="12" cy="12" r="9"/><path d="M9 9a3 3 0 0 1 6 0c0 2-3 2-3 4m0 4h.01"/>
      @break
    @default
      <path d="M6 3h8l4 4v14H6zM14 3v5h4M9 12h6m-6 4h6"/>
  @endswitch
</svg>
