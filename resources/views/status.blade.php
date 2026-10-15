<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>حالة البيانات — المخبر العماني</title>
<style>
:root{--bg:#f6f8fb;--card:#fff;--ink:#14202e;--mute:#5b6b7c;--line:#e2e8f0;--ok:#0f7b45;--warn:#b26a00;--bad:#b42318;--info:#175cd3}
@media (prefers-color-scheme:dark){:root{--bg:#0e1621;--card:#16202d;--ink:#e6edf5;--mute:#9db0c3;--line:#26364a;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171;--info:#7cb2ff}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.7 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif}
main{max-width:1100px;margin:0 auto;padding:24px 16px}
h1{font-size:24px;margin:0 0 4px}p.sub{margin:0 0 20px;color:var(--mute)}
.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px;margin-bottom:16px;overflow-x:auto}
h2{font-size:16px;margin:0 0 12px}
table{width:100%;border-collapse:collapse;min-width:640px}th,td{text-align:right;padding:8px 10px;border-bottom:1px solid var(--line);vertical-align:top}
th{color:var(--mute);font-weight:600;font-size:13px}tr:last-child td{border-bottom:0}
.b{display:inline-block;padding:1px 10px;border-radius:99px;font-size:12px;font-weight:600;border:1px solid currentColor}
.fresh{color:var(--ok)}.stale,.pending,.manual{color:var(--warn)}.failing,.quarantined{color:var(--bad)}.promoted{color:var(--ok)}.unchanged,.staged,.running{color:var(--info)}.failed{color:var(--bad)}
small{color:var(--mute)}
</style>
</head>
<body>
<main>
@php
  $tz = config('omanilab.display_timezone');
  $fmt = fn ($d) => $d ? $d->timezone($tz)->translatedFormat('j F Y — H:i') : '—';
  $ago = fn ($d) => $d ? $d->locale('ar')->diffForHumans() : null;
@endphp
<h1>حالة البيانات</h1>
<p class="sub">المخبر العماني — كل مجموعة بيانات ومتى فُحص مصدرها وتغيّرت بياناتنا آخر مرة (بتوقيت مسقط).</p>

<div class="card">
<h2>مجموعات البيانات</h2>
<table>
<thead><tr><th>المجموعة</th><th>المصدر والإصدار</th><th>الحالة</th><th>آخر فحص</th><th>آخر تغيير</th><th>الفحص القادم</th></tr></thead>
<tbody>
@forelse($datasets as $d)
<tr>
  <td><strong>{{ $d->name_ar }}</strong><br><small>{{ $d->key }}</small></td>
  <td>{{ $d->source->name_ar }}<br><small>{{ $d->source_version ?? '—' }}</small></td>
  <td><span class="b {{ $d->status->value }}">{{ $d->status->labelAr() }}</span>@if($d->last_error)<br><small>{{ \Illuminate\Support\Str::limit($d->last_error, 90) }}</small>@endif</td>
  <td>{{ $fmt($d->last_checked_at) }}<br><small>{{ $ago($d->last_checked_at) }}</small></td>
  <td>{{ $fmt($d->last_changed_at) }}<br><small>{{ $ago($d->last_changed_at) }}</small></td>
  <td>{{ $fmt($d->next_check_at) }}</td>
</tr>
@empty
<tr><td colspan="6">لا توجد مجموعات. شغّل <code>php artisan datasets:sync</code>.</td></tr>
@endforelse
</tbody>
</table>
</div>

<div class="card">
<h2>آخر التشغيلات</h2>
<table>
<thead><tr><th>#</th><th>المجموعة</th><th>النتيجة</th><th>السجلات</th><th>الجديد/المعدّل</th><th>البدء</th></tr></thead>
<tbody>
@foreach($runs as $r)
<tr>
  <td>{{ $r->id }}</td><td>{{ $r->dataset->name_ar }}</td>
  <td><span class="b {{ $r->status }}">{{ ['running'=>'جارٍ','unchanged'=>'دون تغيير','staged'=>'بانتظار الاعتماد','quarantined'=>'محجوز','promoted'=>'معتمد','failed'=>'فشل'][$r->status] ?? $r->status }}</span>@if($r->error)<br><small>{{ \Illuminate\Support\Str::limit($r->error, 90) }}</small>@endif</td>
  <td>{{ number_format($r->records_count) }}</td><td>{{ number_format($r->promoted_count) }}</td><td>{{ $fmt($r->started_at) }}</td>
</tr>
@endforeach
</tbody>
</table>
</div>
</main>
</body>
</html>
