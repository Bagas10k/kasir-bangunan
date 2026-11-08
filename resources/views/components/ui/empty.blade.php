@props(['title', 'hint' => null])
<div class="ui-empty" role="status"><strong>{{ $title }}</strong>@if($hint)<span>{{ $hint }}</span>@endif{{ $slot }}</div>
