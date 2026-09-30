{{--
  Reusable data table component for admin
  Usage:
  @include('admin.partials.table', [
    'headers' => ['Name', 'Subject', 'Status', 'Created', 'Actions'],
    'rows' => $items,
    'renderRow' => function($item) { ... },
    'emptyMessage' => 'No items found',
    'sortable' => true,
  ])
--}}

@php
  $headers = $headers ?? [];
  $rows = $rows ?? [];
  $renderRow = $renderRow ?? function($row) { return []; };
  $emptyMessage = $emptyMessage ?? 'No items found';
  $sortable = $sortable ?? false;
  $sortColumns = $sortColumns ?? [];
  $tableId = $tableId ?? 'dataTable';
@endphp

<div class="table-section">
  <table class="data-table" id="{{ $tableId }}">
    <thead>
      <tr>
        @foreach($headers as $index => $header)
          @php
            $sortColumn = $sortColumns[$index] ?? null;
            $isSorted = $sortColumn !== null && request('sort_by') === $sortColumn;
            $nextDirection = $isSorted && request('sort_dir') === 'asc' ? 'desc' : 'asc';
            $sortUrl = $sortColumn !== null
              ? url()->current().'?'.http_build_query(array_merge(request()->except('page'), ['sort_by' => $sortColumn, 'sort_dir' => $nextDirection]))
              : null;
          @endphp
          <th
            @if($sortable && $sortColumns === []) data-sort="{{ $index }}" @endif
            @if($sortColumn !== null) aria-sort="{{ $isSorted ? (request('sort_dir') === 'desc' ? 'descending' : 'ascending') : 'none' }}" @endif
            style="width: {{ isset($colWidths[$index]) ? $colWidths[$index] : 'auto' }}"
          >
            @if($sortUrl)
              <a class="table-sort-link" href="{{ $sortUrl }}">{{ $header }} <span class="sort-indicator" aria-hidden="true">{{ $isSorted ? (request('sort_dir') === 'desc' ? '▼' : '▲') : '↕' }}</span></a>
            @else
              {{ $header }}
            @endif
            @if($sortable && $sortColumns === [])
              <span class="sort-indicator">▼</span>
            @endif
          </th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @if($rows->isEmpty())
        <tr>
          <td colspan="{{ count($headers) }}" class="text-center" style="padding: 40px 20px;">
            <div class="empty-state">
              <span class="empty-icon">📭</span>
              <p>{{ $emptyMessage }}</p>
            </div>
          </td>
        </tr>
      @else
        @foreach($rows as $row)
          @php $cells = $renderRow($row); @endphp
          <tr data-id="{{ $row->id ?? '' }}">
            @foreach($cells as $cell)
              <td>{!! $cell !!}</td>
            @endforeach
          </tr>
        @endforeach
      @endif
    </tbody>
  </table>
</div>

@pushOnce('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Sortable table headers
  document.querySelectorAll('.data-table th[data-sort]').forEach(th => {
    th.style.cursor = 'pointer';
    th.addEventListener('click', () => {
      const table = th.closest('.data-table');
      const index = parseInt(th.dataset.sort);
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr[data-id]'));

      // Determine the next direction before clearing the previous state.
      const isAsc = !th.classList.contains('sort-asc');
      table.querySelectorAll('th[data-sort]').forEach(h => {
        h.classList.remove('sort-asc', 'sort-desc');
      });
      th.classList.add(isAsc ? 'sort-asc' : 'sort-desc');

      rows.sort((a, b) => {
        const cellA = a.children[index]?.textContent.trim() || '';
        const cellB = b.children[index]?.textContent.trim() || '';

        // Try numeric
        const numA = parseFloat(cellA.replace(/[^\d.-]/g, ''));
        const numB = parseFloat(cellB.replace(/[^\d.-]/g, ''));

        if (!isNaN(numA) && !isNaN(numB)) {
          return isAsc ? numA - numB : numB - numA;
        }

        return isAsc ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
      });

      rows.forEach(row => tbody.appendChild(row));
    });
  });
});
</script>
@endPushOnce
