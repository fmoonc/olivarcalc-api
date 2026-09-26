{{--
    Diff of a parameter set against the previous one (spec §9.1).

    Publishing changes what every live calculator produces, so the owner needs
    to see exactly which values moved before they commit to it.
--}}
<div class="space-y-4 text-sm">
    @if ($rows->isEmpty())
        <p class="text-gray-500">
            @if ($set->isPublished())
                No hay un conjunto anterior de la misma campaña con el que comparar.
            @else
                Este conjunto todavía no se ha modificado respecto al anterior.
            @endif
        </p>
    @else
        <p class="text-gray-500">
            {{ $rows->count() }} valor(es) han cambiado respecto al conjunto anterior de la campaña
            {{ $set->campaign }}.
        </p>

        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-gray-200">
                    <th class="py-2 pr-4 font-medium">Parámetro</th>
                    <th class="py-2 pr-4 font-medium">Antes</th>
                    <th class="py-2 font-medium">Ahora</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $pair)
                    <tr class="border-b border-gray-100 align-top">
                        <td class="py-2 pr-4 font-mono text-xs">{{ $pair["key"] }}</td>
                        <td class="py-2 pr-4 text-gray-500 line-through">{{ $pair['before'] }}</td>
                        <td class="py-2 font-medium">{{ $pair['after'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
