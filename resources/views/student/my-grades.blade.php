<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>My Grades</title>
    <style>
        body { font-family: sans-serif; margin: 24px; }
        table { border-collapse: collapse; width: 100%; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        .toolbar { display: flex; gap: 12px; align-items: end; flex-wrap: wrap; }
    </style>
</head>
<body>
    <h1>My Grades</h1>

    <form method="get" class="toolbar">
        <label>
            Term
            <select name="term_id">
                @foreach($terms as $term)
                    <option value="{{ $term->id }}" @selected($selectedTermId === $term->id)>
                        {{ $term->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            Class
            <select name="class_id">
                @foreach($classOptions as $classId)
                    <option value="{{ $classId }}" @selected((int) $selectedClassId === (int) $classId)>
                        Class {{ $classId }}
                    </option>
                @endforeach
            </select>
        </label>

        <button type="submit">Load</button>
    </form>

    @if($result)
        <p><strong>Final Grade:</strong> {{ $finalGrade ?? '-' }}</p>

        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Weight</th>
                    <th>Status</th>
                    <th>Percent</th>
                    <th>Released At</th>
                    <th>Info</th>
                </tr>
            </thead>
            <tbody>
                @foreach($result['assessments'] as $row)
                    <tr>
                        <td>{{ $row['title'] }}</td>
                        <td>{{ $row['category'] }}</td>
                        <td>{{ $row['weight'] }}</td>
                        <td>{{ $row['attempt_status'] }}</td>
                        <td>{{ $row['percent'] ?? '-' }}</td>
                        <td>{{ $row['released_at'] ?? '-' }}</td>
                        <td>
                            @if($row['attempt_status'] === 'unreleased')
                                Notlar su tarihte aciklanacak.
                            @else
                                {{ $row['message'] ?? '-' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
