@extends('layouts.admin')

@section('page-title', 'Dashboard')
@section('page-desc', 'Tổng quan hệ thống AI+ Admin')

@section('content')
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value">{{ $stats['prompts'] }}</div>
        <div class="stat-label">Prompt Library</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $stats['templates'] }}</div>
        <div class="stat-label">Agent Templates</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $stats['showcases'] }}</div>
        <div class="stat-label">Showcase Posts</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $stats['faqs'] }}</div>
        <div class="stat-label">FAQs</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $stats['tickets_open'] }} / {{ $stats['tickets_total'] }}</div>
        <div class="stat-label">Tickets (Open / Total)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $stats['users'] }}</div>
        <div class="stat-label">Users</div>
    </div>
</div>

<div class="table-section">
    <div class="table-toolbar">
        <h3 style="font-family:'Fraunces',serif;font-size:18px;margin:0;">Quick Actions</h3>
    </div>
    <div style="padding:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
        <a href="{{ route('admin.prompts.create') }}" class="btn-primary" style="justify-content:center;text-align:center;">+ Add Prompt</a>
        <a href="{{ route('admin.templates.create') }}" class="btn-primary" style="justify-content:center;text-align:center;">+ Add Template</a>
        <a href="{{ route('admin.showcases.create') }}" class="btn-primary" style="justify-content:center;text-align:center;">+ Add Showcase</a>
        <a href="{{ route('admin.faqs.create') }}" class="btn-primary" style="justify-content:center;text-align:center;">+ Add FAQ</a>
        <a href="{{ route('admin.tickets.index') }}" class="btn-primary" style="justify-content:center;text-align:center;">View Tickets</a>
        <a href="{{ route('admin.users.create') }}" class="btn-primary" style="justify-content:center;text-align:center;">+ Add User</a>
    </div>
</div>
@endsection