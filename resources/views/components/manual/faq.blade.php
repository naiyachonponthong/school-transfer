@props(['q'])
<details class="manual-faq">
    <summary>{{ $q }}</summary>
    <div class="mf-body">{{ $slot }}</div>
</details>
