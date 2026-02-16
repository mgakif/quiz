# Demo Guide

Bu dataset sadece demo/dev icindir. Uretim seed'i olarak kullanilmaz.

## Komut

```bash
php artisan demo:seed --fresh
```

`--fresh` kullanildiginda `migrate:fresh` calisir ve sonra demo veri seti uretilir.

## Demo Hesaplari

- Teacher:
  - Email: `demo.teacher@quiz.local`
  - Password: `password`
- Students (12 adet):
  - Email kalibi: `demo.student01@quiz.local` ... `demo.student12@quiz.local`
  - Password: `password`
  - Class: `9A` (`class_id=901`)

## Teacher Akisi

- Filament panel girisi sonrasi Question/QuestionVersion ekranlarinda her quiz icin 10 soruluk set gorunur:
  - 6 MCQ
  - 2 Matching
  - 1 Short
  - 1 Essay (rubrikli)
- Grading Queue ekraninda open-ended cevaplarin bir kismi `needs_teacher_review` flag ile bekler.
- Appeals ekraninda 2 itiraz kaydi gorunur ve ikisi de cozulmus durumdadir:
  - Bir kayitta `partial_credit` uygulanmis olur.
  - Diger kayitta `void_question` + `drop_from_total` uygulanmis olur.
- Regrade Decisions ekraninda yukaridaki iki karar ve payload detaylari gorulur.
- Analytics / Question Stats ekranlarinda guncel usage ve skor dagilimlari gorulur.

## Student Akisi

- Student attempt sonuc API akisinda bazi denemeler `release_at` gelecekte oldugu icin kilitli gorunur.
- Bazi denemeler release edilmis durumdadir; not detaylari aciktir.
- Leaderboard ogrenci ekraninda ayni class (`class_id=901`) icin siralama gorunur.
- Nickname bazli gorunum (StudentProfile nickname) doludur.

## Leaderboard ve Jobs

Demo seed sonunda asagidaki hesaplamalar otomatik calisir:

- `UpdateQuestionStatsJob`
- `ComputeLeaderboardJob` (`weekly`)
- `ComputeLeaderboardJob` (`all_time`)

Bu sayede demo sonrasi leaderboard/admin ve analytics ekranlari hemen veri gosterir.
