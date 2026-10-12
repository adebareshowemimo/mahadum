@if(!empty($content['phrases']))
<h2>Your first words</h2>
<div class="phrase-table"><table><caption class="skip">Phrases, meanings, pronunciation and native audio</caption><thead><tr><th scope="col">Phrase</th><th scope="col">Meaning</th><th scope="col">Pronunciation</th><th scope="col">Listen</th></tr></thead><tbody>
@foreach($content['phrases'] as $phrase)
<tr><td>{{ $phrase['text'] }}</td><td>{{ $phrase['meaning'] }}</td><td>{{ $phrase['pronunciation'] }}</td><td><audio controls preload="none" aria-label="Listen to {{ $phrase['text'] }}"><source src="{{ $phrase['audio_url'] }}">Your browser does not support audio. <a href="{{ $phrase['audio_url'] }}">Listen to the recording</a></audio></td></tr>
@endforeach
</tbody></table></div>
@endif
