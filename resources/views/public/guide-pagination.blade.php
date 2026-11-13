@if($pages->lastPage() > 1)<nav class="guide-pager" aria-label="{{ $pages->getPageName() === 'how_page' ? 'How-to' : 'Sample' }} video pages">
  @if($pages->onFirstPage())<span>Previous</span>@else<a href="{{ $pages->previousPageUrl() }}#{{ $pages->getPageName() === 'how_page' ? 'how-to' : 'samples' }}">Previous</a>@endif
  @foreach(range(max(1,$pages->currentPage()-2),min($pages->lastPage(),$pages->currentPage()+2)) as $page)
    <a href="{{ $pages->url($page) }}#{{ $pages->getPageName() === 'how_page' ? 'how-to' : 'samples' }}" @if($page === $pages->currentPage()) aria-current="page" @endif>{{ $page }}</a>
  @endforeach
  @if($pages->hasMorePages())<a href="{{ $pages->nextPageUrl() }}#{{ $pages->getPageName() === 'how_page' ? 'how-to' : 'samples' }}">Next</a>@else<span>Next</span>@endif
</nav>@endif
