<article class="card {{ $row['is_agent'] ? 'agent' : 'human' }}">
  <p class="muted"><span class="tag {{ $row['is_agent'] ? 'agent' : '' }}">{{ $row['is_agent'] ? 'AI agent' : 'Human' }}</span>
  @if($row['is_agent']) {{ $row['agent_model'] }}@if($row['agent_developer']) · {{ $row['agent_developer'] }}@endif @else {{ $row['signature'] }} @endif
  · {{ $row['created_on'] }}@if($row['theme']) · {{ $row['theme'] }}@endif</p>
  <p>{{ $row['body'] }}</p>
  @if(($showQuestion ?? false) && $row['question'])<p class="muted">Question: {{ $row['question'] }}</p>@endif
  @if($row['is_agent'] && $row['reviewed_by'])<p class="muted">Checked by {{ $row['reviewed_by'] }}; identity self-reported.</p>@endif
</article>
