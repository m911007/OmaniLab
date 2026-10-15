# المخبر العماني

منصة بيانات واستخبارات اقتصادية تُحدّث نفسها تلقائيًا من المصادر الرسمية، وتُظهر لكل رقم مصدره وتاريخ آخر فحص وآخر تغيير.

## التشغيل

```bash
cp .env.example .env && php artisan key:generate   # ثم عبّئ كلمات المرور
docker compose up -d --build
```

هذا كل شيء. لا خطوات يدوية بعده: حاوية `migrate` ترحّل القاعدة وتزامن تعريفات المصادر، ثم يبدأ المُجدوِل
فورًا بإرسال كل المجموعات، فتصير القاعدة الفارغة محدّثة بالكامل خلال دقائق (جُرِّب: 14 مجموعة، ~700 ألف قيمة، 3.5 دقيقة).
بعدها يفحص المُجدوِل كل مجموعة حسب دورتها ويجلب ويتحقق ويعتمد وحده، وتُرسل تنبيهات تيليجرام عند أي إخفاق.

- التطبيق: http://localhost:8100 — لوحة القيادة، الاقتصاد العُماني، دول الخليج، دول المقارنة، التجارة الخارجية، الأسواق العالمية، حالة البيانات
- واجهة القراءة `/api/v1`: `datasets`، `observations?indicator=&entities=`، `entities`، `entities/{code}/latest`، `rankings`، `overview`، `series/{indicator}/{entity}`
- الحالة من سطر الأوامر: `php artisan datasets:status`
- الاختبارات: أنشئ مرة واحدة `CREATE DATABASE omanilab_test;` ثم `php artisan test` و `vendor/bin/phpstan analyse`

## خط الجلب

`probe` (فحص رخيص) ← `fetch` (حمولة خام إلى `raw`) ← `parse` (إلى `staging`) ← `validate` ← `promote` (إلى `core`).
فشل التحقق = حجز: لا يُمس `core` وتبقى آخر نسخة سليمة معروضة مع تنبيه تيليجرام.

| الحاوية | دور قاعدة البيانات | يصل للإنترنت |
|---|---|---|
| app | app_reader (قراءة فقط) | لا |
| worker-ingest, worker-ingest-heavy (WITS وGLEIF وشركاء منافذ — طابور مستقل كي لا تحجب المصادر الخفيفة), scheduler | ingest_writer (raw/staging/ops) | نعم |
| worker-promote | promoter (الكاتب الوحيد في core) | فقط للتنبيهات |

## الواجهة الأمامية (React + TypeScript + Vite)

`frontend/`: صفحات معرَّفة بالتكوين فوق مكوّن واحد `<SeriesChart lines=[…]>` يجلب بياناته ويعرضها مع مصدرها وآخر فحص وآخر تغيير
وجدول بديل. لون كل كيان ثابت (لوحة تصور معتمدة)، والوضع الداكن/الفاتح، والعربية RTL، والجوال.
- بناء ونشر في Laravel: `cd frontend && npm run build:laravel` (الحاوية تبنيها تلقائيًا عند `docker compose up --build`)
- تطوير: `cd frontend && npm run dev` (Vite على 5173 ويمرّر `/api` إلى 8100)
- اختبارات: `npm test` و `npm run typecheck`

## المصادر (28 مجموعة، 248 مؤشرًا، أكثر من 1.4 مليون قيمة وعلاقة)

| المصدر | المجموعات | الفحص الرخيص | الدورية |
|---|---|---|---|
| صندوق النقد (IMF) | weo، fiscal_monitor، debt_database، reserves، ai_readiness | `last-modified` لكل مؤشر | أسبوعي |
| البنك الدولي | macro، trade، society، digital، health، environment، tourism، finance، labour، governance (WGI) | `lastupdated` | 3–7 أيام |
| GCC-Stat (SDMX) | economy، society، energy_tourism | لا يوجد → بصمة الحمولة | يومي / 3 أيام |
| NCSI عبر data.gov.om | prices، national_accounts، oil_gas، communication | `lastDataChangedOn` | يومي / 3 أيام |
| منافذ (الجمارك) | totals، partners (مع السنة الجارية YTD) | آخر سنة وشهر منشورين | يومي / أسبوعي |
| WITS (البنك الدولي / Comtrade) | trade: إجماليات وشركاء كل دولة (علاقات ثنائية) | آخر سنة ببيانات كافية | أسبوعان |
| UNDP | hdr: مؤشرات التنمية البشرية (CSV رسمي) | `Last-Modified` | أسبوعان |
| GLEIF | oman: الشركات العُمانية بسماتها؛ lei_counts: أعداد الكيانات لكل دولة | تاريخ النسخة الذهبية | يومي / أسبوعي |

نماذج البيانات: **قياسات** (مؤشر، كيان، فترة، قيمة) و**علاقات ثنائية** (مصدر ← هدف) و**سمات وصفية** للكيانات (الشركات).

### مصادر جُرّبت وتعذّرت (قرارها عائد إليك)
- **البنك المركزي العُماني:** جدار الحماية يرفض الوصول الآلي صراحةً. المطلوب واجهة أو ملفات رسمية منهم.
- **وزارة المالية:** ملف الأداء المالي الفعلي PDF مرسوم صورةً (بلا نص)، يحتاج OCR أو نموذجًا بصريًا.
- **opendata.gov.om:** مؤجل حتى توفر API (robots.txt يمنع الزحف).
- **بورصة مسقط:** بياناتها عبر SignalR داخل الصفحة (مهمة للمتصفح/الوكيل لاحقًا).

## قواعد التحقق ومستويات الشدة

`error` يحجز المجموعة كلها (شكل مكسور، نقص نقاط، مراجعة منهجية، إصدار أقدم). `reject` يستبعد النقطة وحدها ويوثّقها
(خلل وحدة قياس في المصدر، قيمة مستحيلة). `warning` يُسجَّل فقط. أمثلة اكتُشفت فعلًا في المصادر: ناتج قطر 2010 بقيمة ×600،
والناتج الفصلي Q1-2024 لكل دول الخليج بالمليار بدل المليون، وإنتاج النفط والغاز العُماني 2014–2015 بوحدة ×1000.
الاستبعادات كلها في جدول `ops.validation_issues` (severity = reject).

## النسخ الاحتياطي والاستعادة

خدمة `backup` تأخذ نسخة يومية `pg_dump -Fc` إلى الحجم `omanilab_backups` (تُتحقق بـ `pg_restore --list`)، وتحتفظ بآخر 14. صحتها تعتمد على عمر آخر نسخة ناجحة.

```bash
# نسخة الآن
docker compose exec backup sh -c 'PGPASSWORD=$PGPASSWORD pg_dump -h db -U $PGUSER -d $PGDATABASE -Fc -f /backups/manual.dump'
# استعادة إلى قاعدة جديدة (جُرّبت: أعداد مطابقة تمامًا)
docker compose exec db createdb -U omani omanilab_restore
docker compose exec backup sh -c 'PGPASSWORD=$PGPASSWORD pg_restore -h db -U $PGUSER -d omanilab_restore --no-owner /backups/<الملف>.dump'
```

## إضافة مصدر جديد

1. كلاس في `app/Modules/<Section>/Connectors/` يمتد من `ApiConnector` وينفّذ `definition/probe/fetch/parse`.
2. سطر في `config/omanilab.php` تحت `connectors`.
3. رفع `revision()` كلما تغيّر ما ينتجه المحلّل من بيانات المصدر نفسه (فتُعاد المعالجة آليًا)، ثم اختبار بـ fixture في `tests/`.
   (المزامنة تتم وحدها عند التشغيل؛ مجموعة حُذفت من الكود تُعطَّل ولا تُحذف بياناتها.)

## تيليجرام

ضع `TELEGRAM_BOT_TOKEN` و `TELEGRAM_CHAT_ID` في `.env`. بدونهما تُسجَّل التنبيهات في السجل فقط.
