# Quiz / Assessment OS

Bu proje, Laravel 12 + Filament 5 ile geliştirilmiş **privacy-first** bir “assessment platformu”dur:
- Version’lı **Soru Bankası**
- AI destekli **soru üretimi** + **reviewer gate**
- **Rubrik tabanlı** (manual + AI öneri) open-ended değerlendirme
- **Release kilidi** (notlar senin belirlediğin zamanda görünür)
- **İtiraz + regrade + audit**
- **Analytics**, **Leaderboard (Hall of Fame)**, **Gradebook (dönem notu)**

## Hızlı Başlatma

> Sail yok. Komutlar container içinde çalıştırılır.

```bash
docker compose up -d
docker compose exec quiz php artisan key:generate
docker compose exec quiz php artisan migrate
docker compose exec quiz php artisan test