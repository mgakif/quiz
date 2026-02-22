# SYSTEM_MAP — Quiz / Assessment OS (Laravel 12 + Filament 5)

Bu repo bir “quiz app” değil; **soru bankası + assessment engine + ölçme-değerlendirme + gradebook** platformu.

---

## 0) Yüzeyler (Uygulama yüzleri)

### A) Teacher Panel (Filament)
Öğretmenin tüm yönetimi burada:
- **Question Bank**
  - Questions + QuestionVersions (versionlı)
  - Draft/Active/Deprecated/Archived durumları
  - Reviewer Gate sonuçları (pass/fail + issues)
- **Sandbox / Preview**
  - QuestionVersion’ı “öğrenci gibi çöz”
  - MCQ/Matching auto-check
  - Short/Essay rubrik ile puanla
  - AI önerisini gör (ai_gradings geçmişi)
  - Publish (reviewer pass) / Override publish (note + audit)
- **Exams**
  - Exam CRUD
  - Exam ↔ Assessment binding (otomatik)
  - Assessment metadata: term/category/weight/published
- **Grading**
  - Open-ended grading queue (manual/AI öneri + teacher onay/override)
- **Appeals / Regrade**
  - İtirazları gör, karar ver (partial/void/key/rubric change)
  - Regrade preview + toplu regrade
  - Audit kaydı
- **Analytics**
  - Tag / Learning Objective bazlı zayıflık haritası
  - question_stats (version bazlı)
- **Leaderboard Admin**
  - nickname/opt-out yönetimi
  - Recompute

### B) Student UI (Blade)
Öğrenci tarafı:
- Öğrenci sonuç API/ekranları (release kilidi ile)
- Sonuçlar (release kilidi ile)
- İtiraz (appeal window açıksa)
- Leaderboard (rumuzlu)
- Gradebook / My Grades (term bazlı)

### C) Public Access (Token + Guest)
Tokenlı umuma açık test akışı:
- Public exam link üretimi/yönetimi (teacher)
- Guest start → attempt oluşturma
- Submit / Result endpointleri
- Result endpointinde release gate
- Guest attempt’lar gradebook/leaderboard dışında

### D) System (Jobs / Events / Schedulers)
Arka planda çalışan işler:
- ReleaseDueGradesJob → attempt released + downstream recompute
- Regrade jobs → scores update + downstream recompute
- UpdateQuestionStatsJob → version bazlı stats
- ComputeLeaderboardJob → leaderboard snapshot/cache
- ComputeTermGradesJob / ComputeStudentTermGradeJob → gradebook

---

## 1) Ana kavramlar (Mental model)

### Question vs QuestionVersion
- **Question**: soyut soru varlığı (kimlik, status, tags vs)
- **QuestionVersion**: soru metni/şık/rubrik/anahtar gibi içerik. Değiştikçe yeni version oluşur.
- İstatistikler **version bazlı** (question_stats).

### Exam vs Assessment
- **Exam**: sınav/quiz runtime varlığı (mevcutta: id, title, class_id, scheduled_at)
- **Assessment**: gradebook’a giren kayıt (term, category, weight, published)
- Uyum:
  - attempts.exam_id **legacy** kalır.
  - assessments.legacy_exam_id → exam.id bağlanır.
  - Exam create/update/delete → assessment otomatik bind edilir.

### Attempt / AttemptItem / Response
- **Attempt**: öğrencinin bir sınav oturumu.
- **AttemptItem**: attempt içindeki her soru örneği (randomization burada sabitlenir).
- **Response**: AttemptItem cevabı (JSON payload).

### RubricScores vs AIGradings
- **rubric_scores**: final/uygulanmış puan (teacher/manual veya apply edilmiş öneri).
- **ai_gradings**: AI’nin önerisi (final değil). Teacher uygular/override eder.

### Appeals / Regrade / Audit
- **Appeal**: öğrenci itiraz kaydı
- **RegradeDecision**: öğretmen kararı (partial/void/key change/rubric change)
- **AuditEvent**: kim-ne yaptı (denetlenebilirlik)

### Gradebook (Term / Scheme)
- **Term**: dönem (start/end, is_active)
- **Grade Scheme**: term bazlı kategori ağırlıkları (quiz/exam/assignment/participation)
- **StudentTermGrade**: computed + optional overridden final not

---

## 2) Veri akışı (En kritik 7 akış)

### A) Soru üretimi ve kalite
1) Blueprint → AI Question Generation → QuestionVersion(DRAFT)
2) Reviewer Gate → pass/fail + issues
3) Teacher Sandbox’ta çözer
4) Publish (pass) veya Override publish (note + audit)
5) Active soru bankaya girer → exams/attempts’te kullanılabilir

### B) Attempt → Not görünürlüğü (Grade Release)
- Attempt submitted olsa bile:
  - **released değilse** sonuç ekranında **puan/feedback yok**.
- ReleaseDueGradesJob:
  - release_at gelince attempt’ı released yapar.

### C) Open-ended puanlama
- Öğrenci cevap verir → teacher queue
- Teacher manual rubric scoring veya AI önerisini uygular
- Final puan rubric_scores’a yazılır (draft/final ayrımı projedeki kurala göre)

### D) İtiraz ve yeniden değerlendirme
- Student appeal açar (sadece own item + released + window açık)
- Teacher karar verir:
  - partial_credit / void_question / answer_key_change / rubric_change
- Regrade jobs çalışır
- Audit event’ler yazılır

### E) Analytics / stats
- released attempt’lardan:
  - question_stats (version bazlı)
  - student/class weakness (tag / LO)
- drop_from_total void_question paydadan çıkarılır.

### F) Gradebook & otomatik güncelleme
- Attempt released → ComputeStudentTermGradeJob(term+student)
- Regrade finish → etkilenen term+student recompute
- Grade Scheme, final hesapta devrededir.

### G) Public token flow
- Teacher PublicExamLink oluşturur (enable/expire/max_attempts/require_name)
- Guest `/public/{token}` ile sınav bilgisine ulaşır
- Guest start ile attempt açılır (`guest_id`, `student_id=null`)
- Guest submit sonrası release kuralı geçilmeden puan/feedback görünmez
- Guest attempt:
  - gradebook’a girmez
  - leaderboard’a girmez
  - analytics tarafında config’e bağlıdır (`analytics.include_guests`, default false)

---

## 3) “Tek kaynak” kuralları (çok önemli)

### ScoreExtractor
Tüm puan/percent hesaplamaları **ScoreExtractor** üzerinden yürür.
- Analytics
- Leaderboard
- Gradebook
- Regrade etkileri (partial/void) aynı yerde uygulanır.

### Release Gate
Unreleased attempt:
- sonuç API/UI: puan/feedback döndürmez
- gradebook/leaderboard: **dahil edilmez**
- analytics: release filtresi uygulanır, guest dahil edilmesi config’e bağlıdır

---

## 4) Modellerin rolleri (AI ne yapıyor?)
Bu repo model isimlerinden bağımsız “rol” esaslıdır:
- **Generator**: soru üretir (schema-locked JSON)
- **Reviewer**: kalite kapısı (pass/fail/issues)
- **Grader**: open-ended rubric önerisi (confidence + evidence + flags)
> Final karar öğretmende; AI öneri konumunda.

Şemalar:
- /schemas/question_generate.schema.json
- /schemas/reviewer.schema.json
- /schemas/openended_grade.schema.json

---

## 5) Zamanlayıcılar / Trigger noktaları

### Trigger’lar
- ReleaseDueGradesJob:
  - attempt released
  - leaderboard recompute
  - gradebook incremental recompute
- Regrade jobs:
  - leaderboard recompute
  - gradebook incremental recompute

### Locks / Dedup
- gradebook:compute:{term}:{student} (cache lock)
- leaderboard cache key:
  - leaderboard:{classId|global}:{period}:{start}:{end}

---

## 6) Panik anında “Nereye bakacağım?”

### “Not niye görünmüyor?”
- Attempt: grade_state / release_at
- GradeRelease policy/service

### “Puan yanlış hesaplanıyor”
- ScoreExtractor
- void_question / partial_credit kuralı
- rubric_scores (final) vs ai_gradings (öneri)

### “Gradebook yanlış”
- Assessment binding (exam ↔ assessment, term_id)
- Grade Scheme weights/strategy
- released filtre
- incremental recompute job

### “Leaderboard yanlış”
- released filtre
- student_profiles (nickname, opt-out)
- cache key / snapshot payload

---

## 7) Çalıştırma (bu repoda standart)

Komutlar (Sail YOK):
- docker compose exec quiz php artisan test
- docker compose exec quiz php vendor/bin/pint --dirty --format=agent
- docker compose exec quiz php artisan demo:seed --fresh
