@extends('layouts.ai-plus')

@section('title', 'My Usage — AI+ LSTS')

@section('breadcrumb', 'My Usage')

@section('content')
<header class="page-header">
  <div class="wrap">
    <div class="header-top">
      <div class="user-profile">
        <div class="user-avatar">{{ $userInitials }}</div>
        <div class="user-info">
          <h1>{{ $userName }}</h1>
          <div class="user-role">{{ $userRole }}</div>
        </div>
      </div>
      <div class="date-range">
        <button class="date-btn">Today</button>
        <button class="date-btn active">This Week</button>
        <button class="date-btn">This Month</button>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value">{{ $stats['prompts'] }}</div>
        <div class="stat-label">Prompts Sent</div>
        <div class="stat-change up">↑ 23% vs last week</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ $stats['tokens'] }}</div>
        <div class="stat-label">Tokens Used</div>
        <div class="stat-change up">↑ 18% vs last week</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ $stats['timeSaved'] }}</div>
        <div class="stat-label">Time Saved</div>
        <div class="stat-change up">Estimated</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">{{ $stats['agentsCreated'] }}</div>
        <div class="stat-label">Agents Created</div>
        <div class="stat-change">{{ $stats['agentsShared'] }} shared</div>
      </div>
    </div>
  </div>
</header>

<main class="content">
  <div class="wrap">
    <!-- Quota Section -->
    <div class="quota-section">
      <div class="quota-header">
        <h2 class="quota-title">Daily Quota</h2>
        <span class="quota-remaining">{{ $promptsLimit - $promptsUsed }} of {{ $promptsLimit }} prompts remaining</span>
      </div>
      <div class="quota-bar">
        <div class="quota-fill" style="width: {{ ($promptsUsed / $promptsLimit) * 100 }}%"></div>
      </div>
      <div class="quota-details">
        <span>Resets at <span class="quota-reset">12:00 AM tomorrow</span></span>
        <span>{{ round(($promptsUsed / $promptsLimit) * 100) }}% used today</span>
      </div>
    </div>

    <!-- Two Column -->
    <div class="two-col">
      <!-- Activity -->
      <div class="activity-section">
        <h2 class="section-title">Recent Activity</h2>
        <div class="activity-list">
          @foreach($activities as $activity)
          <div class="activity-item">
            <div class="activity-icon {{ $activity['isTemplate'] ? 'template' : '' }}">{{ $activity['icon'] }}</div>
            <div class="activity-content">
              <div class="activity-title">{{ $activity['title'] }}</div>
              <div class="activity-meta">{{ $activity['source'] }} • {{ $activity['time'] }}</div>
            </div>
            <span class="activity-tokens">{{ $activity['tokens'] }}</span>
          </div>
          @endforeach
        </div>
      </div>

      <!-- Top Tasks -->
      <div class="top-tasks">
        <h2 class="section-title">Top Task Types</h2>
        <div class="task-list">
          @foreach($topTasks as $task)
          <div class="task-item">
            <span class="task-name">{{ $task['icon'] }} {{ $task['name'] }}</span>
            <span class="task-count">{{ $task['count'] }}</span>
          </div>
          @endforeach
        </div>
      </div>
    </div>

    <!-- Chart -->
    <div class="chart-section">
      <div class="chart-header">
        <h2 class="section-title">Usage Over Time</h2>
      </div>
      <div class="chart-placeholder">
        📈 Chart: Prompts per day (last 7 days) — Integration with Chart.js in production
      </div>
    </div>
  </div>
</main>
@endsection

@push('styles')
<style>
  .page-header{background: var(--navy);color:#fff;padding: 40px 0 48px;}
  .header-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:16px;}
  .user-profile{display:flex;align-items:center;gap:16px;}
  .user-avatar{width:64px;height:64px;border-radius:50%;background: var(--gold);color: var(--navy-deep);display:flex;align-items:center;justify-content:center;font-family:'Fraunces', serif;font-size:24px;font-weight:600;}
  .user-info h1{font-family:'Fraunces', serif;font-size:28px;font-weight:600;margin-bottom:4px;}
  .user-role{color:#C8E1DC;font-size:14px;}
  .date-range{display:flex;gap:8px;}
  .date-btn{padding:8px 16px;background: rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.2);border-radius:6px;color:#fff;font-size:13px;cursor:pointer;transition: all 0.15s;}
  .date-btn:hover{background: rgba(255,255,255,0.2);}
  .date-btn.active{background: var(--gold);color: var(--navy-deep);border-color: var(--gold);}

  .stats-grid{display:grid;grid-template-columns: repeat(4, 1fr);gap:16px;margin-top:24px;}
  .stat-card{background: rgba(255,255,255,0.08);border-radius:12px;padding:20px;text-align:center;}
  .stat-value{font-family:'Fraunces', serif;font-size:36px;font-weight:600;color: var(--gold-light);}
  .stat-label{font-size:12px;color:#7EA69E;text-transform:uppercase;letter-spacing:0.05em;margin-top:4px;}
  .stat-change{font-size:12px;margin-top:8px;display:flex;align-items:center;justify-content:center;gap:4px;}
  .stat-change.up{color: var(--success);}

  .content{padding:32px 0 48px;}

  .quota-section{background: var(--card-bg);border:1px solid var(--line);border-radius:14px;padding:24px;margin-bottom:24px;}
  .quota-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
  .quota-title{font-family:'Fraunces', serif;font-size:20px;font-weight:600;color: var(--navy);}
  .quota-remaining{font-family:'IBM Plex Mono', monospace;font-size:14px;color: var(--ink-soft);}
  .quota-bar{height:12px;background: var(--paper);border-radius:6px;overflow:hidden;margin-bottom:12px;}
  .quota-fill{height:100%;background: linear-gradient(90deg, var(--navy) 0%, var(--gold) 100%);border-radius:6px;transition: width 0.3s;}
  .quota-details{display:flex;justify-content:space-between;font-size:13px;color: var(--ink-soft);}
  .quota-reset{color: var(--navy);font-weight:500;}

  .two-col{display:grid;grid-template-columns: 2fr 1fr;gap:24px;}

  .activity-section{background: var(--card-bg);border:1px solid var(--line);border-radius:14px;padding:24px;}
  .section-title{font-family:'Fraunces', serif;font-size:18px;font-weight:600;color: var(--navy);margin-bottom:20px;}
  .activity-list{display:flex;flex-direction:column;gap:12px;}
  .activity-item{display:flex;gap:12px;padding:12px;background: var(--paper);border-radius:8px;align-items:flex-start;}
  .activity-icon{width:36px;height:36px;border-radius:8px;background: var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;}
  .activity-icon.template{background: var(--gold);color: var(--navy-deep);}
  .activity-content{flex:1;min-width:0;}
  .activity-title{font-size:14px;font-weight:500;color: var(--ink);margin-bottom:2px;}
  .activity-meta{font-size:12px;color: var(--ink-soft);}
  .activity-tokens{font-family:'IBM Plex Mono', monospace;font-size:12px;color: var(--navy);background: rgba(31,56,100,0.08);padding:4px 8px;border-radius:4px;}

  .top-tasks{background: var(--card-bg);border:1px solid var(--line);border-radius:14px;padding:24px;}
  .task-list{display:flex;flex-direction:column;gap:12px;}
  .task-item{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--line);}
  .task-item:last-child{border-bottom:none;}
  .task-name{font-size:14px;color: var(--ink);display:flex;align-items:center;gap:8px;}
  .task-count{font-family:'IBM Plex Mono', monospace;font-size:13px;color: var(--ink-soft);}

  .chart-section{background: var(--card-bg);border:1px solid var(--line);border-radius:14px;padding:24px;margin-top:24px;}
  .chart-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
  .chart-placeholder{height:200px;background: linear-gradient(180deg, var(--paper) 0%, rgba(31,56,100,0.05) 100%);border-radius:8px;display:flex;align-items:center;justify-content:center;color: var(--ink-soft);font-size:14px;}

  @media (max-width: 900px){
    .stats-grid{grid-template-columns: repeat(2, 1fr);}
    .two-col{grid-template-columns: 1fr;}
  }
</style>
@endpush
