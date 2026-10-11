<div class="prose-sida max-w-none">
    @foreach ($blocks as $block)
        @if ($block['type'] === 'heading')
            @if ($block['level'] === 2)
                <h2>{{ $block['text'] }}</h2>
            @elseif ($block['level'] === 3)
                <h3>{{ $block['text'] }}</h3>
            @else
                <h4>{{ $block['text'] }}</h4>
            @endif
        @elseif ($block['type'] === 'paragraph')
            <p class="whitespace-pre-line">{{ $block['text'] }}</p>
        @elseif ($block['type'] === 'button')
            <p class="not-prose my-8">
                <a href="{{ $block['url'] }}"
                   class="btn btn-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">
                    {{ $block['text'] }}
                </a>
            </p>
        @endif
    @endforeach
</div>
