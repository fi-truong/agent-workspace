@if ($errors->any())
<div class="admin-validation-summary" role="alert">
    <strong>Please review the highlighted fields.</strong>
    <ul>
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif
