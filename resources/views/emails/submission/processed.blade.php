<h1>{{ $title ?? 'Untitled' }}</h1>
<h2>@markdown ($author['name'] ?? 'Unknown')</h2>

@isset ($description)
    <h3>Message</h3>
    <p>{{ $description }}</p>
@endisset

@isset ($fields)
    <h3>Fields</h3>
    <table>
        <tr>
            <th>Field</th>
            <th>Value</th>
        </tr>
        @foreach ($fields as $field => $value)
            @if ($value !== null)
                <tr>
                    <td>{{ $field }}</td>
                    <td>{{ $value }}</td>
                </tr>
            @endif
        @endforeach
    </table>
@endisset

@isset ($details)
    <h3>Scoring Details</h3>
    @foreach ($details as $detail)
        {{ $detail }}<br>
    @endforeach
@endisset

@isset ($meta)
    <h3>Meta Data</h3>
    <table>
        <tr>
            <th>Variable</th>
            <th>Data</th>
        </tr>
        @foreach ($meta as $field => $value)
            @if ($value !== null)
                <tr>
                    <td>{{ $field }}</td>
                    <td>{{ $value }}</td>
                </tr>
            @endif
        @endforeach
    </table>
@endisset

@isset ($links)
    <h3>Links / Quick Actions</h3>
    @foreach ($links as $url => $name)
        <p><a href="{{ $url }}">{{ $name }}</a></p>
    @endforeach
@endisset
