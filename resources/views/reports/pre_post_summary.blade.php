@php
    $fmt = function ($v, $suffix = '', $sign = false) {
        if ($v === null || $v === '') return '—';
        $n = (float) $v;
        return ($sign && $n > 0 ? '+' : '') . number_format($n, 1) . $suffix;
    };
    $gainClass = function ($v) {
        if ($v === null || (float) $v == 0) return 'neutral';
        return (float) $v > 0 ? 'up' : 'down';
    };
    $mv = $summary['movement'];
@endphp
<!DOCTYPE html>
<html lang="fil">
<head>
<meta charset="utf-8">
<title>Pre vs Post - {{ $class->name }}</title>
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10px; color: #222; margin: 0; }
    h1 { font-size: 18px; margin: 0 0 2px 0; color: #9B0505; }
    h2 { font-size: 13px; margin: 18px 0 6px 0; color: #9B0505; border-bottom: 2px solid #9B0505; padding-bottom: 3px; }
    .sub { color: #666; font-size: 9px; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #9B0505; color: #fff; padding: 5px 6px; font-size: 9px; text-align: center; }
    td { border: 1px solid #ccc; padding: 4px 6px; text-align: center; }
    td.left, th.left { text-align: left; }
    tr:nth-child(even) td { background: #faf4f4; }
    .up { color: #2e7d32; font-weight: bold; }
    .down { color: #c62828; font-weight: bold; }
    .neutral { color: #666; }
    .cards { width: 100%; margin-top: 6px; }
    .cards td { border: 1px solid #ccc; padding: 8px; width: 33%; background: #fff; }
    .big { font-size: 20px; font-weight: bold; }
    .note { font-size: 8px; color: #666; font-style: italic; margin-top: 4px; }
    .pagebreak { page-break-before: always; }
</style>
</head>
<body>

<h1>Pre-Test vs Post-Test Summary</h1>
<div class="sub">
    {{ $class->name }} @if($class->section) — {{ $class->section }} @endif
    &nbsp;•&nbsp; Generated {{ $generated }}
</div>

<h2>Class Overview</h2>
<div>
    {{ $summary['total_students'] }} students •
    {{ $summary['with_pre'] }} with pre-test •
    {{ $summary['with_post'] }} with post-test •
    {{ $summary['paired_students'] }} with both
</div>

<table class="cards">
    <tr>
        <td><div class="big up">{{ $mv['improved'] }}</div>Improved (Instructional grade)</td>
        <td><div class="big neutral">{{ $mv['same'] }}</div>Same</td>
        <td><div class="big down">{{ $mv['declined'] }}</div>Declined</td>
    </tr>
</table>

<h2>Average Pre vs Post (paired students only)</h2>
<table>
    <thead>
        <tr>
            <th class="left">Measure</th>
            <th>n</th>
            <th>Pre</th>
            <th>Post</th>
            <th>Mean gain</th>
            <th>SD</th>
            <th>t (df)</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($labels as $key => [$label, $suffix])
        @php $m = $summary['metrics'][$key] ?? []; @endphp
        <tr>
            <td class="left">{{ $label }}</td>
            <td>{{ $m['n'] ?? 0 }}</td>
            <td>{{ $fmt($m['pre_mean'] ?? null, $suffix) }}</td>
            <td>{{ $fmt($m['post_mean'] ?? null, $suffix) }}</td>
            <td class="{{ $gainClass($m['mean_gain'] ?? null) }}">{{ $fmt($m['mean_gain'] ?? null, $suffix, true) }}</td>
            <td>{{ $fmt($m['sd_gain'] ?? null) }}</td>
            <td>{{ isset($m['t']) ? $m['t'] . ' (' . $m['df'] . ')' : '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
<div class="note">
    Mean gain = average of (post − pre) per student. Only students with both a pre and a post value
    are counted for each measure. t is the paired t-statistic.
</div>

<div class="pagebreak"></div>
<h2>Per-Student Results</h2>
<table>
    <thead>
        <tr>
            <th class="left">Student</th>
            <th>Set<br>(pre / post)</th>
            <th>Instructional Gr.<br>(pre → post)</th>
            <th>Gain</th>
            <th>WR %<br>(pre → post)</th>
            <th>Comp %<br>(pre → post)</th>
            <th>WPM<br>(pre → post)</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($rows as $r)
        <tr>
            <td class="left">{{ $r['name'] }}</td>
            <td>{{ $r['pre_set'] ?? '—' }} / {{ $r['post_set'] ?? '—' }}</td>
            <td>{{ $fmt($r['pre_instructional']) }} → {{ $fmt($r['post_instructional']) }}</td>
            <td class="{{ $gainClass($r['gain_instructional']) }}">{{ $fmt($r['gain_instructional'], '', true) }}</td>
            <td>{{ $fmt($r['pre_wr'], '%') }} → {{ $fmt($r['post_wr'], '%') }}</td>
            <td>{{ $fmt($r['pre_comp'], '%') }} → {{ $fmt($r['post_comp'], '%') }}</td>
            <td>{{ $fmt($r['pre_wpm']) }} → {{ $fmt($r['post_wpm']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

</body>
</html>