<div class="blockstudio-test__block" id="{{ str_replace('/', '-', $b['name']) }}">
  <h1>ID: {{ str_replace('/', '-', $b['name']) }}</h1>
  @if(isset($a['text']))
    <p>Text: {{ $a['text'] }}</p>
  @endif
  <code>Attributes: {!! json_encode($a) !!}</code>
</div>
