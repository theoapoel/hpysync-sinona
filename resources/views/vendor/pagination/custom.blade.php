@if ($paginator->hasPages())
<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
    <span style="font-size:13px;color:var(--text3)">
        {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data
    </span>
    <div style="display:flex;gap:4px;align-items:center;">

        {{-- Prev --}}
        @if ($paginator->onFirstPage())
            <span class="btn btn-ghost btn-sm" style="opacity:.4;cursor:default;">
                <i class="fas fa-chevron-left"></i>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-ghost btn-sm">
                <i class="fas fa-chevron-left"></i>
            </a>
        @endif

        {{-- Page numbers --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span style="padding:6px 4px;font-size:13px;color:var(--text3);">…</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="btn btn-primary btn-sm"
                            style="cursor:default;min-width:34px;justify-content:center;border-radius:8px;">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}" class="btn btn-ghost btn-sm"
                            style="min-width:34px;justify-content:center;border-radius:8px;">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-ghost btn-sm">
                <i class="fas fa-chevron-right"></i>
            </a>
        @else
            <span class="btn btn-ghost btn-sm" style="opacity:.4;cursor:default;">
                <i class="fas fa-chevron-right"></i>
            </span>
        @endif

    </div>
</div>
@endif
