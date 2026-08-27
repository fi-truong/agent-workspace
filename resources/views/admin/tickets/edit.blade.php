@extends('layouts.admin')

@section('title', 'Edit Support Ticket - AI+ Admin')

@section('page-title', 'Edit Support Ticket')

@section('content')
<div class="admin-wrap">
  <a href="{{ route('admin.tickets.index') }}" class="btn-secondary" style="margin-bottom:24px;display:inline-block;">
    ← Back to Tickets
  </a>

  @include('admin.partials.form-modal', [
    'id' => 'ticketForm',
    'title' => 'Edit Support Ticket',
    'route' => route('admin.tickets.update', $ticket),
    'method' => 'PUT',
    'editMode' => true,
    'editData' => [
      'name' => $ticket->name,
      'email' => $ticket->email,
      'subject' => $ticket->subject,
      'message' => $ticket->message,
      'status' => $ticket->status,
      'assignee_id' => $ticket->assignee_id,
      'admin_notes' => $ticket->admin_notes,
    ],
    'fields' => [
      ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'placeholder' => 'Contact name'],
      ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'placeholder' => 'contact@example.com'],
      ['name' => 'subject', 'label' => 'Subject', 'type' => 'text', 'required' => true, 'placeholder' => 'Ticket subject'],
      ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Describe the issue...'],
      ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['pending' => 'Pending', 'in_progress' => 'In Progress', 'resolved' => 'Resolved'], 'required' => true],
      ['name' => 'assignee_id', 'label' => 'Assignee', 'type' => 'select', 'options' => ['' => '— Unassigned —'] + $staff->pluck('name', 'id')->toArray(), 'required' => false],
      ['name' => 'admin_notes', 'label' => 'Admin Notes', 'type' => 'textarea', 'required' => false, 'placeholder' => 'Internal notes...'],
    ],
    'submitLabel' => 'Update Ticket',
  ])

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    window.openAdminModal('ticketForm');
  });
  </script>
</div>
@endsection