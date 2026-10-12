@if(!empty($content['videos']))
<h2>Watch and learn</h2><div class="grid">
@foreach($content['videos'] as $video)
<article class="panel"><div class="video"><button type="button" class="video" data-youtube="{{ $video['youtube_id'] }}" aria-label="Play {{ $video['title'] }}">▶ {{ $video['title'] }}</button></div><h3>{{ $video['title'] }}</h3><p>{{ $video['description'] }}</p><a href="https://www.youtube.com/watch?v={{ $video['youtube_id'] }}">Watch on YouTube</a></article>
@endforeach
</div>
@endif
