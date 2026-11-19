@php
  $sections = [
    ['agent-workspace', 'Agent Workspace', 'chat'],
    ['prompt-library', 'Prompt Library', 'book'],
    ['agent-templates', 'Agent Templates', 'agents'],
    ['sharing-showcase', 'Sharing & Showcase', 'users'],
    ['support', 'Support', 'support'],
    ['ai-policy', 'AI Policy & Guidelines', 'policy'],
    ['my-usage', 'My Usage', 'usage'],
  ];
@endphp
<aside class="lsts-section-sidebar">
  <nav class="lsts-section-nav" aria-label="LSTS AI+ sections">
    @foreach($sections as [$section, $label, $icon])
    <a href="{{ route('ai-plus.'.$section.'.index') }}" @if(request()->routeIs('ai-plus.'.$section.'.*')) aria-current="page" @endif>
      @include('partials.lsts-ai-icon', ['name' => $icon])
      <span>{{ $label }}</span>
    </a>
    @endforeach
  </nav>
</aside>
