@props(['item'])
<a href="{{ $item['url'] }}" class="app-tile">
    <span class="app-ico {{ $item['color'] !== 'primary' ? 'ico-'.$item['color'] : '' }}">
        <i class="bi {{ $item['icon'] }}"></i>
        @if ($item['badge'])<span class="ab">{{ $item['badge'] }}</span>@endif
    </span>
    <span>{{ $item['label'] }}</span>
</a>
