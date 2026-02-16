<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Leaderboard</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; background: #f7fafc; color: #111827; }
        .panel { background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; max-width: 900px; }
        .controls { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        select { padding: 0.4rem 0.6rem; border: 1px solid #d1d5db; border-radius: 0.4rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; text-align: left; padding: 0.6rem 0.4rem; }
        th { font-size: 0.75rem; color: #6b7280; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="panel">
        <h1>Class Leaderboard</h1>
        <p>Only aliases are shown.</p>
        <div class="controls">
            <label>
                Class
                <select id="classId">
                    @foreach ($classOptions as $classOption)
                        <option value="{{ $classOption }}" @selected($defaultClassId === $classOption)>Class {{ $classOption }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Period
                <select id="period">
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="all_time">All Time</option>
                </select>
            </label>
            <button id="refresh">Refresh</button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Nickname</th>
                    <th>Percent</th>
                    <th>Attempts</th>
                </tr>
            </thead>
            <tbody id="rows"></tbody>
        </table>
    </div>

    <script>
        const rowsEl = document.getElementById('rows');
        const classEl = document.getElementById('classId');
        const periodEl = document.getElementById('period');
        const refreshEl = document.getElementById('refresh');

        async function loadLeaderboard() {
            if (!classEl.value) {
                rowsEl.innerHTML = '<tr><td colspan="4">No classes found.</td></tr>';
                return;
            }

            const url = `/api/student/leaderboard?class_id=${encodeURIComponent(classEl.value)}&period=${encodeURIComponent(periodEl.value)}`;
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (!response.ok) {
                rowsEl.innerHTML = '<tr><td colspan="4">Leaderboard could not be loaded.</td></tr>';
                return;
            }

            const data = await response.json();
            const entries = Array.isArray(data.entries) ? data.entries : [];

            if (entries.length === 0) {
                rowsEl.innerHTML = '<tr><td colspan="4">No released attempts yet.</td></tr>';
                return;
            }

            rowsEl.innerHTML = entries.map((entry) => `
                <tr>
                    <td>${entry.rank}</td>
                    <td>${entry.nickname}</td>
                    <td>${Number(entry.percent ?? 0).toFixed(2)}%</td>
                    <td>${entry.attempts_count ?? 0}</td>
                </tr>
            `).join('');
        }

        refreshEl.addEventListener('click', loadLeaderboard);
        classEl.addEventListener('change', loadLeaderboard);
        periodEl.addEventListener('change', loadLeaderboard);
        loadLeaderboard();
    </script>
</body>
</html>
