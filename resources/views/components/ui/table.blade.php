@props([
    'headers' => [],
])

<div {{ $attributes->merge(['class' => 'overflow-x-auto border border-hairline bg-cream rounded-none']) }}>
    <table class="w-full text-left border-collapse">
        @if(!empty($headers))
            <thead class="bg-creamDark border-b border-hairline">
                <tr>
                    @foreach($headers as $header)
                        <th class="px-4 py-3 font-mono text-[11px] font-bold uppercase tracking-wider text-ink border-r last:border-r-0 border-hairline">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @elseif(isset($head))
            <thead class="bg-creamDark border-b border-hairline">
                {{ $head }}
            </thead>
        @endif
        <tbody class="divide-y divide-hairline bg-cream text-xs font-mono text-ink">
            {{ $slot }}
        </tbody>
    </table>
</div>
